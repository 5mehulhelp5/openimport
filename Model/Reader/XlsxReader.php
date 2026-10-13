<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Reader;

use Iranimij\OpenImport\Model\Value\NumberText;

/**
 * Streams one sheet of an Excel workbook (.xlsx) without any library: the workbook is a ZIP archive of XML parts,
 * read with ZipArchive and XMLReader (both required by Magento).
 *
 * - Sheet: first sheet, a 1-based position, or a name (case-insensitive).
 * - Values: shared and inline strings, numbers (as Excel shows them, without binary rounding noise), booleans
 *   (1 / 0), cached formula results, and dates/times in date-formatted cells as "Y-m-d" / "Y-m-d H:i:s"
 *   (1900 and 1904 date systems). Error cells (#N/A …) become ''.
 * - Row numbers are the sheet's own row numbers, so errors point at the row a merchant sees in Excel.
 * - Archives whose content would unpack to more than 512 MB are refused (zip bombs).
 */
class XlsxReader implements ReaderInterface
{
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MAX_UNPACKED = 536870912;
    private const BUILTIN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36,
        45, 46, 47, 50, 51, 52, 53, 54, 55, 56, 57, 58];

    /**
     * @var string[]|null
     */
    private ?array $sharedStrings = null;

    /**
     * @var array<int, bool>|null style index => is a date format
     */
    private ?array $dateStyles = null;

    private ?string $sheetPart = null;

    private string $sheetName = '';

    private bool $date1904 = false;

    /**
     * @var string[]|null
     */
    private ?array $columns = null;

    private ColumnNames $names;

    private NumberText $numbers;

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
        $this->names = new ColumnNames();
        $this->numbers = new NumberText();
    }

    /**
     * @inheritDoc
     */
    public function columns(): array
    {
        if ($this->columns === null) {
            foreach ($this->sheetRows() as $cells) {
                $this->columns = $this->options->hasHeader
                    ? $this->names->normalize($this->dense($cells))
                    : $this->names->generated($this->width($cells));
                break;
            }
            $this->columns ??= [];
        }

        return $this->columns;
    }

    /**
     * @inheritDoc
     */
    public function rows(): \Generator
    {
        $columns = null;
        foreach ($this->sheetRows() as $number => $cells) {
            if ($columns === null) {
                $columns = $this->options->hasHeader
                    ? $this->names->normalize($this->dense($cells))
                    : $this->names->generated($this->width($cells));
                $this->columns = $columns;
                if ($this->options->hasHeader) {
                    continue;
                }
            }
            yield $number => $this->names->combine($columns, $this->dense($cells, count($columns)));
        }
    }

    /**
     * @inheritDoc
     */
    public function detected(): array
    {
        $this->sheetPart();

        return ['sheet' => $this->sheetName];
    }

    /**
     * Names of all sheets in workbook order (for the profile form).
     *
     * @return string[]
     */
    public function sheetNames(): array
    {
        return array_keys($this->sheets());
    }

    /**
     * Non-empty rows as [column index => value], keyed by sheet row number.
     *
     * @return \Generator<int, array<int, string>>
     */
    private function sheetRows(): \Generator
    {
        $part = $this->sheetPart();
        $reader = new \XMLReader();
        if (!$reader->open($this->zipUrl($part), null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new ReaderException('The sheet cannot be opened.');
        }
        $rowNumber = 0;
        $cells = [];
        $column = -1;
        $type = '';
        $style = -1;
        $value = null;
        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::DOC_TYPE) {
                    throw new ReaderException('The workbook contains a DOCTYPE and is refused.');
                }
                if ($reader->nodeType === \XMLReader::ELEMENT) {
                    switch ($reader->localName) {
                        case 'row':
                            $rowNumber = (int) ($reader->getAttribute('r') ?? 0) ?: $rowNumber + 1;
                            $cells = [];
                            $column = -1;
                            if ($reader->isEmptyElement) {
                                continue 2;
                            }
                            break;
                        case 'c':
                            $reference = (string) $reader->getAttribute('r');
                            $column = $reference !== '' ? $this->columnIndex($reference) : $column + 1;
                            $type = (string) $reader->getAttribute('t');
                            $style = (int) ($reader->getAttribute('s') ?? -1);
                            $value = null;
                            if ($reader->isEmptyElement) {
                                $value = null;
                            }
                            break;
                        case 'v':
                            $value = $reader->readString();
                            break;
                        case 'is':
                            $value = $this->inlineText($reader);
                            break;
                    }
                    continue;
                }
                if ($reader->nodeType === \XMLReader::END_ELEMENT) {
                    if ($reader->localName === 'c') {
                        $cell = $this->cellValue($type, $style, $value);
                        if ($cell !== '') {
                            $cells[$column] = $cell;
                        }
                    } elseif ($reader->localName === 'row') {
                        if ($cells !== []) {
                            yield $rowNumber => $cells;
                        }
                    }
                }
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Text of an inline string (<is>), all runs, without phonetic hints.
     */
    private function inlineText(\XMLReader $reader): string
    {
        $text = '';
        $depth = $reader->depth;
        $phonetic = false;
        while ($reader->read() && $reader->depth > $depth) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'rPh') {
                $phonetic = true;
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'rPh') {
                $phonetic = false;
            } elseif (!$phonetic && ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::SIGNIFICANT_WHITESPACE)) {
                $text .= $reader->value;
            }
        }

        return $text;
    }

    private function cellValue(string $type, int $style, ?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            's' => $this->sharedStrings()[(int) $value] ?? '',
            'inlineStr', 'str' => $value,
            'b' => $value === '1' ? '1' : '0',
            'e' => '',
            default => $this->number($value, $style),
        };
    }

    private function number(string $value, int $style): string
    {
        if (!is_numeric($value)) {
            return $value;
        }
        if ($style >= 0 && ($this->dateStyles()[$style] ?? false)) {
            return $this->date((float) $value);
        }
        if (preg_match('/^-?\d+$/', $value) === 1) {
            return $value;
        }
        return $this->numbers->fromFloat((float) $value);
    }

    /**
     * Excel serial date to text.
     */
    private function date(float $serial): string
    {
        $days = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);
        if ($seconds === 86400) {
            $days++;
            $seconds = 0;
        }
        $base = new \DateTimeImmutable($this->date1904 ? '1904-01-01' : '1899-12-30', new \DateTimeZone('UTC'));
        $date = $base->modify(sprintf('%+d days', $days))->modify(sprintf('+%d seconds', $seconds));

        return $seconds === 0 && $serial === floor($serial) ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s');
    }

    /**
     * "BC12" => 54 (0-based).
     */
    private function columnIndex(string $reference): int
    {
        $letters = strtoupper((string) preg_replace('/\d+$/', '', $reference));
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * @param array<int, string> $cells
     * @return int
     */
    private function width(array $cells): int
    {
        return $cells === [] ? 0 : max(array_keys($cells)) + 1;
    }

    /**
     * @param array<int, string> $cells
     * @param int|null $width
     * @return string[]
     */
    private function dense(array $cells, ?int $width = null): array
    {
        $width ??= $this->width($cells);
        $list = [];
        for ($i = 0; $i < $width; $i++) {
            $list[] = $cells[$i] ?? '';
        }

        return $list;
    }

    /**
     * @return string[]
     */
    private function sharedStrings(): array
    {
        if ($this->sharedStrings !== null) {
            return $this->sharedStrings;
        }
        $this->sharedStrings = [];
        $part = $this->findPart('xl/sharedStrings.xml');
        if ($part === null) {
            return $this->sharedStrings;
        }
        $reader = new \XMLReader();
        $reader->open($this->zipUrl($part), null, LIBXML_NONET | LIBXML_COMPACT);
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $this->sharedStrings[] = $reader->isEmptyElement ? '' : $this->inlineText($reader);
            }
        }
        $reader->close();

        return $this->sharedStrings;
    }

    /**
     * @return array<int, bool>
     */
    private function dateStyles(): array
    {
        if ($this->dateStyles !== null) {
            return $this->dateStyles;
        }
        $this->dateStyles = [];
        $xml = $this->partXml('xl/styles.xml');
        if ($xml === null) {
            return $this->dateStyles;
        }
        $custom = [];
        foreach ($xml->numFmts->numFmt ?? [] as $format) {
            $custom[(int) $format['numFmtId']] = (string) $format['formatCode'];
        }
        $index = 0;
        foreach ($xml->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            $this->dateStyles[$index++] = in_array($id, self::BUILTIN_DATE_FORMATS, true)
                || (isset($custom[$id]) && $this->isDateFormat($custom[$id]));
        }

        return $this->dateStyles;
    }

    private function isDateFormat(string $code): bool
    {
        // Ignore quoted text, escaped characters and [colour]/[locale] sections, then look for date/time tokens.
        $plain = (string) preg_replace(['/"[^"]*"/', '/\\\\./', '/\[[^\]]*\]/'], '', $code);

        return preg_match('/[dmyhs]/i', $plain) === 1 && stripos($plain, 'General') === false;
    }

    /**
     * Sheet name => part path, in workbook order.
     *
     * @return array<string, string>
     */
    private function sheets(): array
    {
        $workbook = $this->partXml('xl/workbook.xml');
        if ($workbook === null) {
            throw new ReaderException('The file is not an Excel workbook (.xlsx).');
        }
        $this->date1904 = in_array(strtolower((string) ($workbook->workbookPr['date1904'] ?? '')), ['1', 'true'], true);
        $targets = [];
        $rels = $this->partXml('xl/_rels/workbook.xml.rels');
        foreach ($rels->Relationship ?? [] as $relationship) {
            $target = ltrim((string) $relationship['Target'], '/');
            $targets[(string) $relationship['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        }
        $sheets = [];
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $id = (string) $sheet->attributes(self::NS_REL)['id'];
            if (isset($targets[$id])) {
                $sheets[(string) $sheet['name']] = $targets[$id];
            }
        }
        if ($sheets === []) {
            throw new ReaderException('The workbook has no sheets.');
        }

        return $sheets;
    }

    private function sheetPart(): string
    {
        if ($this->sheetPart !== null) {
            return $this->sheetPart;
        }
        $this->checkArchive();
        $sheets = $this->sheets();
        $wanted = trim($this->options->sheet);
        $names = array_keys($sheets);
        if ($wanted === '') {
            $name = $names[0];
        } elseif (ctype_digit($wanted)) {
            $name = $names[(int) $wanted - 1] ?? null;
        } else {
            $name = null;
            foreach ($names as $candidate) {
                if (mb_strtolower($candidate) === mb_strtolower($wanted)) {
                    $name = $candidate;
                }
            }
        }
        if ($name === null) {
            throw new ReaderException(sprintf('The sheet "%s" does not exist. Sheets: %s.', $wanted, implode(', ', $names)));
        }
        $part = $this->findPart($sheets[$name]);
        if ($part === null) {
            throw new ReaderException(sprintf('The sheet "%s" is missing from the workbook.', $name));
        }
        $this->sheetName = $name;

        return $this->sheetPart = $part;
    }

    private function checkArchive(): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->file, \ZipArchive::RDONLY) !== true) {
            throw new ReaderException('The file is not an Excel workbook (.xlsx).');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $total += (int) ($stat['size'] ?? 0);
        }
        $zip->close();
        if ($total > self::MAX_UNPACKED) {
            throw new ReaderException('The workbook would unpack to more than 512 MB and is refused.');
        }
    }

    /**
     * The archive's entry name for a part (entry names are case-sensitive, some writers vary the case).
     */
    private function findPart(string $part): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->file, \ZipArchive::RDONLY) !== true) {
            throw new ReaderException('The file is not an Excel workbook (.xlsx).');
        }
        $found = $zip->locateName($part) !== false ? $part : null;
        if ($found === null) {
            $index = $zip->locateName($part, \ZipArchive::FL_NOCASE);
            $found = $index !== false ? (string) $zip->getNameIndex($index) : null;
        }
        $zip->close();

        return $found;
    }

    private function partXml(string $part): ?\SimpleXMLElement
    {
        $name = $this->findPart($part);
        if ($name === null) {
            return null;
        }
        $zip = new \ZipArchive();
        $zip->open($this->file, \ZipArchive::RDONLY);
        $content = $zip->getFromName($name);
        $zip->close();
        if ($content === false || stripos($content, '<!DOCTYPE') !== false) {
            throw new ReaderException('The workbook part ' . $part . ' cannot be read.');
        }
        $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);

        return $xml === false ? null : $xml;
    }

    private function zipUrl(string $part): string
    {
        return 'zip://' . $this->file . '#' . $part;
    }
}
