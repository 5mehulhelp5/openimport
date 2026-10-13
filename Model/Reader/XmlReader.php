<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Reader;

/**
 * Streams an XML file record by record with PHP's XMLReader (constant memory for any file size).
 *
 * Record path: "/catalog/product" (from the root), "//product" (that element at any depth) or '' (the children of
 * the root element). Each record is flattened: child elements become columns ("price", "stock.qty"), attributes
 * become "@attr" / "price.@currency", repeated children are joined with a comma.
 *
 * Safety: documents with a DOCTYPE are refused (no entity expansion, no external entities), and the parser never
 * fetches anything from the network.
 */
class XmlReader implements ReaderInterface
{
    /**
     * @var string[]|null
     */
    private ?array $columns = null;

    private Flattener $flattener;

    /**
     * @param string $file absolute path
     * @param ReaderOptions $options
     */
    public function __construct(
        private readonly string $file,
        private readonly ReaderOptions $options = new ReaderOptions()
    ) {
        $info = new \SplFileInfo($file);
        if (!$info->isFile() || !$info->isReadable()) {
            throw new ReaderException(sprintf('The file "%s" cannot be read.', $info->getFilename()));
        }
        $this->flattener = new Flattener();
    }

    /**
     * @inheritDoc
     */
    public function columns(): array
    {
        if ($this->columns === null) {
            $names = [];
            $count = 0;
            foreach ($this->rows() as $row) {
                foreach (array_keys($row) as $name) {
                    $names[$name] = true;
                }
                if (++$count >= $this->options->sampleSize) {
                    break;
                }
            }
            $this->columns = array_map('strval', array_keys($names));
        }

        return $this->columns;
    }

    /**
     * @inheritDoc
     */
    public function detected(): array
    {
        return ['record_path' => $this->options->recordPath === '' ? '(children of the root element)' : $this->options->recordPath];
    }

    /**
     * @inheritDoc
     */
    public function rows(): \Generator
    {
        [$anyDepth, $path] = $this->parsePath();
        $reader = new \XMLReader();
        $previous = libxml_use_internal_errors(true);
        if (!$reader->open($this->file, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS)) {
            libxml_use_internal_errors($previous);
            throw new ReaderException('The XML file cannot be opened.');
        }
        $stack = [];
        $number = 0;
        try {
            $moved = $this->read($reader);
            while ($moved) {
                if ($reader->nodeType === \XMLReader::DOC_TYPE) {
                    throw new ReaderException('XML files with a DOCTYPE are not accepted (they can carry entities). Remove the DOCTYPE line.');
                }
                if ($reader->nodeType === \XMLReader::END_ELEMENT) {
                    array_pop($stack);
                    $moved = $this->read($reader);
                    continue;
                }
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    $moved = $this->read($reader);
                    continue;
                }
                $isEmpty = $reader->isEmptyElement;
                $stack[] = $reader->localName;
                if ($this->matches($stack, $anyDepth, $path)) {
                    $xml = $reader->readOuterXml();
                    $number++;
                    yield $number => $this->toRow($xml, $number);
                    array_pop($stack);
                    // next() skips the record's subtree (already read) and lands on the following node.
                    $moved = $isEmpty ? $this->read($reader) : $reader->next();
                    continue;
                }
                if ($isEmpty) {
                    array_pop($stack);
                }
                $moved = $this->read($reader);
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function read(\XMLReader $reader): bool
    {
        $result = $reader->read();
        if (!$result) {
            $error = libxml_get_last_error();
            if ($error !== false && $error->level >= LIBXML_ERR_ERROR) {
                throw new ReaderException(sprintf('The XML file is not valid: %s (line %d).', trim($error->message), $error->line));
            }
        }

        return $result;
    }

    /**
     * @return array{0: bool, 1: string[]}
     */
    private function parsePath(): array
    {
        $raw = trim($this->options->recordPath);
        if ($raw === '') {
            return [false, []];
        }
        $anyDepth = str_starts_with($raw, '//');
        $parts = array_values(array_filter(explode('/', $raw), static fn (string $part): bool => $part !== ''));
        if ($parts === []) {
            return [false, []];
        }

        return [$anyDepth, $parts];
    }

    /**
     * @param string[] $stack
     * @param bool $anyDepth
     * @param string[] $path
     * @return bool
     */
    private function matches(array $stack, bool $anyDepth, array $path): bool
    {
        if ($path === []) {
            return count($stack) === 2;
        }
        if ($anyDepth) {
            return end($stack) === end($path);
        }

        return $stack === $path;
    }

    /**
     * @param string $xml
     * @param int $number
     * @return array<string, string>
     */
    private function toRow(string $xml, int $number): array
    {
        $element = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($element === false) {
            throw new ReaderException(sprintf('Record %d is not valid XML.', $number));
        }
        $value = $this->convert($element);

        return is_array($value) ? $this->flattener->flatten($value) : ['value' => $value];
    }

    /**
     * @param \SimpleXMLElement $element
     * @return array<string, mixed>|string
     */
    private function convert(\SimpleXMLElement $element): array|string
    {
        $data = [];
        foreach ($element->attributes() ?? [] as $name => $attribute) {
            $data['@' . $name] = (string) $attribute;
        }
        $children = [];
        foreach ($element->children() as $child) {
            $children[$child->getName()][] = $this->convert($child);
        }
        foreach ($children as $name => $values) {
            if (count($values) === 1) {
                $data[$name] = $values[0];
                continue;
            }
            $allText = array_filter($values, 'is_string') === $values;
            $data[$name] = $allText ? implode(',', $values) : $values;
        }
        $text = trim((string) $element);
        if ($children === [] && $data === []) {
            return $text;
        }
        if ($children === [] && $text !== '') {
            return ['' => $text] + $data;
        }

        return $data;
    }
}
