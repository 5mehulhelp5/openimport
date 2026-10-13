<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Bridge;

/**
 * Outcome of one pass through core's import engine.
 */
class ImportResult
{
    /**
     * @param bool $valid core's validation passed (within the allowed errors)
     * @param bool $imported data was written (false for dry runs and refused imports)
     * @param int $rows rows core checked
     * @param int $entities entities core checked
     * @param int $invalidRows rows core will not import
     * @param int $created entities created (live runs)
     * @param int $updated entities updated (live runs)
     * @param int $deleted entities deleted (live runs)
     * @param RowError[] $errors
     * @param int|null $historyId core import history row of a live run
     * @param float $seconds wall time of validation plus import
     */
    public function __construct(
        public readonly bool $valid,
        public readonly bool $imported,
        public readonly int $rows,
        public readonly int $entities,
        public readonly int $invalidRows,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $deleted,
        public readonly array $errors,
        public readonly ?int $historyId,
        public readonly float $seconds
    ) {
    }

    /**
     * Errors of one 0-based source row.
     *
     * @param int $row
     * @return RowError[]
     */
    public function errorsForRow(int $row): array
    {
        return array_values(array_filter($this->errors, static fn (RowError $error): bool => $error->row === $row));
    }
}
