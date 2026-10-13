<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Reader;

/**
 * Reads a JSON file: a list of objects, or an object holding that list at a dot path ("data.items").
 * Without a record path: the document itself when it is a list, else its only list-of-objects property.
 * Records are flattened (see Flattener). The file is read into memory, up to ReaderOptions::$maxBytes.
 */
class JsonReader implements ReaderInterface
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $records = null;

    private string $path = '';

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
            foreach ($this->records() as $record) {
                foreach (array_keys($this->flattener->flatten($record)) as $name) {
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
    public function rows(): \Generator
    {
        foreach ($this->records() as $index => $record) {
            yield $index + 1 => $this->flattener->flatten($record);
        }
    }

    /**
     * @inheritDoc
     */
    public function detected(): array
    {
        $this->records();

        return ['record_path' => $this->path];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function records(): array
    {
        if ($this->records !== null) {
            return $this->records;
        }
        $size = (int) (new \SplFileInfo($this->file))->getSize();
        if ($size > $this->options->maxBytes) {
            throw new ReaderException(sprintf(
                'The JSON file is %s MB; JSON files up to %s MB are read. Use CSV or XML for larger files.',
                round($size / 1048576, 1),
                round($this->options->maxBytes / 1048576, 1)
            ));
        }
        $file = new \SplFileObject($this->file, 'r');
        $json = $size > 0 ? (string) $file->fread($size) : '';
        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $json = substr($json, 3);
        }
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $exception) {
            throw new ReaderException('The file is not valid JSON: ' . $exception->getMessage() . '.');
        }
        [$list, $this->path] = $this->locate($document);
        $records = [];
        foreach ($list as $position => $record) {
            if (!is_array($record) || ($record !== [] && array_is_list($record))) {
                throw new ReaderException(sprintf('Record %d at "%s" is not an object.', $position + 1, $this->path ?: '/'));
            }
            $records[] = $record;
        }

        return $this->records = $records;
    }

    /**
     * Finds the list of records and returns it with its dot path.
     *
     * @param mixed $document
     * @return array{0: array<int, mixed>, 1: string}
     */
    private function locate(mixed $document): array
    {
        $path = trim($this->options->recordPath, ". \t/");
        if ($path !== '') {
            $node = $document;
            foreach (explode('.', str_replace('/', '.', $path)) as $part) {
                if (!is_array($node) || !array_key_exists($part, $node)) {
                    throw new ReaderException(sprintf('The record path "%s" does not exist in the file.', $path));
                }
                $node = $node[$part];
            }
            if (!is_array($node) || !array_is_list($node)) {
                throw new ReaderException(sprintf('The record path "%s" does not point to a list.', $path));
            }
            return [$node, $path];
        }
        if (is_array($document) && array_is_list($document)) {
            return [$document, ''];
        }
        if (is_array($document)) {
            $lists = array_filter(
                $document,
                static fn ($value): bool => is_array($value) && $value !== [] && array_is_list($value) && is_array($value[0])
            );
            if (count($lists) === 1) {
                return [reset($lists), (string) key($lists)];
            }
            if (count($lists) > 1) {
                throw new ReaderException(sprintf(
                    'The file holds several lists (%s). Set the record path to the one to import.',
                    implode(', ', array_keys($lists))
                ));
            }
            return [[$document], ''];
        }
        throw new ReaderException('The JSON file holds no records.');
    }
}
