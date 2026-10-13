<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Storage;

/**
 * One error of a run, pointing at the row of the merchant's file.
 */
class ErrorRecord
{
    /**
     * @param int $sourceRow row in the source file (header = 1 for CSV/XLSX, record number for JSON/XML; 0 = whole run)
     * @param string $message
     * @param string $identifier
     * @param string $field
     * @param array<string, string>|null $sourceData the original row, for the error report
     */
    public function __construct(
        public readonly int $sourceRow,
        public readonly string $message,
        public readonly string $identifier = '',
        public readonly string $field = '',
        public readonly ?array $sourceData = null
    ) {
    }
}
