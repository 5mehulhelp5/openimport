<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Source;

use Magento\ImportExport\Model\Import\AbstractSource;

/**
 * Feeds rows that OpenImport prepared (read, mapped, transformed, filtered) into Magento's own import engine.
 *
 * Core reads an import through an AbstractSource: a fixed list of column names and rows in that order. This source
 * takes the column names and a factory that returns a fresh iterable of associative rows each time core starts
 * reading again (core rewinds the source, for example between validation and saving bunches). Nothing is written
 * to a temporary file. Missing columns become empty strings, which core treats like an empty cell in a CSV file
 * ("leave unchanged"), exactly as a CSV with that column empty would.
 */
class MappedSource extends AbstractSource
{
    /**
     * @var callable(): iterable<array<string, mixed>>
     */
    private $rowsFactory;

    /**
     * @var \Iterator<mixed, array<string, mixed>>|null
     */
    private ?\Iterator $rows = null;

    /**
     * @var array<string, int>
     */
    private array $columnIndex;

    /**
     * @param string[] $columnNames core column names, in the order core should see them
     * @param callable $rowsFactory returns iterable<array<string, scalar|null>>, a new iteration on every call
     */
    public function __construct(array $columnNames, callable $rowsFactory)
    {
        $columnNames = array_values(array_map('strval', $columnNames));
        parent::__construct($columnNames);
        $this->rowsFactory = $rowsFactory;
        $this->columnIndex = array_flip($columnNames);
    }

    /**
     * Next row as a list in column order, or false at the end.
     *
     * @return string[]|false
     */
    protected function _getNextRow()
    {
        if ($this->rows === null) {
            $this->rows = $this->startRows();
        } else {
            $this->rows->next();
        }
        if (!$this->rows->valid()) {
            return false;
        }

        return $this->toList($this->rows->current());
    }

    /**
     * Starts over: the next row read is the first one of a new iteration.
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->rows = null;
        parent::rewind();
    }

    /**
     * A fresh iterator from the factory, positioned on its first row.
     *
     * @return \Iterator<mixed, array<string, mixed>>
     */
    private function startRows(): \Iterator
    {
        $rows = ($this->rowsFactory)();
        if (is_array($rows)) {
            $iterator = new \ArrayIterator($rows);
        } elseif ($rows instanceof \Iterator) {
            $iterator = $rows;
        } elseif ($rows instanceof \Traversable) {
            $iterator = new \IteratorIterator($rows);
        } else {
            throw new \InvalidArgumentException('The rows factory must return an array or a Traversable.');
        }
        $iterator->rewind();

        return $iterator;
    }

    /**
     * Turns an associative row into core's positional row; unknown columns are a programming error.
     *
     * @param mixed $row
     * @return string[]
     */
    private function toList(mixed $row): array
    {
        if (!is_array($row)) {
            throw new \InvalidArgumentException('Each row must be an array keyed by column name.');
        }
        $list = array_fill(0, count($this->columnIndex), '');
        foreach ($row as $column => $value) {
            $position = $this->columnIndex[(string) $column] ?? null;
            if ($position === null) {
                throw new \InvalidArgumentException(sprintf('Column "%s" is not one of the source columns.', $column));
            }
            $list[$position] = $this->toCell($value);
        }

        return $list;
    }

    /**
     * Cell value as core receives it from a CSV file: a string.
     *
     * @param mixed $value
     * @return string
     */
    private function toCell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_float($value) => $this->formatFloat($value),
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => throw new \InvalidArgumentException('Cell values must be scalar, null or Stringable.'),
        };
    }

    /**
     * A float without exponent and without locale, the way a spreadsheet would export it.
     *
     * @param float $value
     * @return string
     */
    private function formatFloat(float $value): string
    {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('Cell values must be finite numbers.');
        }
        $text = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
