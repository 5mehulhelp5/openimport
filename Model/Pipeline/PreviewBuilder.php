<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Pipeline;

use Iranimij\OpenImport\Model\Storage\PreviewRecord;
use Iranimij\OpenImport\Model\Value\NumberParser;

/**
 * Compares a prepared core row with what the shop has now, the way core import will treat it: empty cells change
 * nothing, the empty-value constant clears a value, numbers compare as numbers (12.50 = 12.5), Yes/No equal 1/0.
 * Columns the shop's current values do not include are not compared.
 */
class PreviewBuilder
{
    private NumberParser $numbers;

    /**
     * @param string $emptyValueConstant
     */
    public function __construct(
        private readonly string $emptyValueConstant = '__EMPTY__VALUE__'
    ) {
        $this->numbers = new NumberParser();
    }

    /**
     * @param string $identifier
     * @param array<string, string> $after the core row
     * @param array<string, string>|null $before current values, null when the record does not exist
     * @param string[] $skip columns never compared (identifier, store_view_code …)
     * @param int $sourceRow
     * @return PreviewRecord
     */
    public function compare(string $identifier, array $after, ?array $before, array $skip, int $sourceRow): PreviewRecord
    {
        $diff = [];
        foreach ($after as $column => $value) {
            if (in_array($column, $skip, true)) {
                continue;
            }
            $value = trim($value);
            if ($before === null) {
                if ($value !== '' && $value !== $this->emptyValueConstant) {
                    $diff[$column] = [null, $value];
                }
                continue;
            }
            if ($value === '' || !array_key_exists($column, $before)) {
                continue;
            }
            $new = $value === $this->emptyValueConstant ? '' : $value;
            if (!$this->same(trim($before[$column]), $new)) {
                $diff[$column] = [$before[$column], $new];
            }
        }
        $type = match (true) {
            $before === null => PreviewRecord::NEW,
            $diff !== [] => PreviewRecord::CHANGED,
            default => PreviewRecord::UNCHANGED,
        };

        return new PreviewRecord($identifier, $type, $diff, $sourceRow);
    }

    /**
     * A record the run would delete (or not find).
     *
     * @param string $identifier
     * @param bool $exists
     * @param int $sourceRow
     * @return PreviewRecord
     */
    public function deletion(string $identifier, bool $exists, int $sourceRow): PreviewRecord
    {
        return new PreviewRecord(
            $identifier,
            $exists ? PreviewRecord::DELETED : PreviewRecord::UNCHANGED,
            [],
            $sourceRow
        );
    }

    private function same(string $before, string $after): bool
    {
        if ($before === $after) {
            return true;
        }
        $booleans = ['yes' => '1', 'no' => '0', 'true' => '1', 'false' => '0'];
        $a = $booleans[strtolower($before)] ?? $before;
        $b = $booleans[strtolower($after)] ?? $after;
        if ($a === $b) {
            return true;
        }
        if ($this->numbers->isPlain($a) && $this->numbers->isPlain($b)) {
            return abs((float) $a - (float) $b) < 0.0000001;
        }

        return false;
    }
}
