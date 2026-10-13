<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Reader;

/**
 * How a source file is read. Every option has a safe default; "auto" values are detected from the file.
 */
class ReaderOptions
{
    public const AUTO = 'auto';

    /**
     * @param string $delimiter CSV: auto, or one of , ; | and "\t"
     * @param string $enclosure CSV quote character
     * @param string $encoding CSV: auto, UTF-8, Windows-1252, ISO-8859-1, ISO-8859-15
     * @param string $sheet XLSX: sheet name, 1-based position, or '' for the first sheet
     * @param string $recordPath JSON: dot path to the list of records ('' = the document or its only list);
     *                           XML: element path from the root, e.g. /catalog/product ('' = children of the root)
     * @param bool $hasHeader CSV/XLSX: the first row holds column names
     * @param int $maxBytes JSON: largest file read into memory
     * @param int $sampleSize JSON/XML: records scanned to find the column names
     */
    public function __construct(
        public readonly string $delimiter = self::AUTO,
        public readonly string $enclosure = '"',
        public readonly string $encoding = self::AUTO,
        public readonly string $sheet = '',
        public readonly string $recordPath = '',
        public readonly bool $hasHeader = true,
        public readonly int $maxBytes = 52428800,
        public readonly int $sampleSize = 1000
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'delimiter' => $this->delimiter === "\t" ? '\t' : $this->delimiter,
            'enclosure' => $this->enclosure,
            'encoding' => $this->encoding,
            'sheet' => $this->sheet,
            'record_path' => $this->recordPath,
            'has_header' => $this->hasHeader,
        ];
    }
}
