<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Filter;

/**
 * Decides per source row whether it is imported. Built by RowFilterCompiler.
 */
class RowFilter
{
    /**
     * @param \Closure[] $conditions fn (array $row): bool
     * @param bool $include true: import only matching rows; false: skip matching rows
     * @param bool $all true: a row matches when all conditions hold; false: when any holds
     */
    public function __construct(
        private readonly array $conditions,
        private readonly bool $include,
        private readonly bool $all
    ) {
    }

    /**
     * @param array<string, string> $row
     * @return bool
     * @throws FilterException when a filter expression fails for this row
     */
    public function keep(array $row): bool
    {
        if ($this->conditions === []) {
            return true;
        }
        $matches = $this->all;
        foreach ($this->conditions as $condition) {
            $result = $condition($row);
            if ($this->all && !$result) {
                $matches = false;
                break;
            }
            if (!$this->all && $result) {
                $matches = true;
                break;
            }
        }

        return $this->include ? $matches : !$matches;
    }

    public function isEmpty(): bool
    {
        return $this->conditions === [];
    }
}
