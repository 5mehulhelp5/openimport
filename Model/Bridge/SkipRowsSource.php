<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Bridge;

use Magento\ImportExport\Model\Import\AbstractSource;

/**
 * Another source without some of its rows, for a second validation pass. Remembers where each remaining row was in
 * the original source, so errors of the second pass still point at the original rows.
 */
class SkipRowsSource extends AbstractSource
{
    /**
     * @var array<int, true>
     */
    private array $skip;

    /**
     * @var int[] original 0-based position of each row of this source
     */
    private array $positions = [];

    private bool $started = false;

    /**
     * @param AbstractSource $source
     * @param int[] $skip 0-based positions in $source to leave out
     */
    public function __construct(private readonly AbstractSource $source, array $skip)
    {
        parent::__construct($source->getColNames());
        $this->skip = array_fill_keys(array_map('intval', $skip), true);
    }

    /**
     * Where a row of this source was in the original source.
     *
     * @param int $position 0-based position in this source
     * @return int
     */
    public function originalPosition(int $position): int
    {
        return $this->positions[$position] ?? $position;
    }

    /**
     * Next row of the original source that is not skipped, as a list in column order, or false at the end.
     *
     * @return string[]|false
     */
    protected function _getNextRow()
    {
        if ($this->started) {
            $this->source->next();
        } else {
            $this->source->rewind();
            $this->started = true;
        }
        while ($this->source->valid() && isset($this->skip[(int) $this->source->key()])) {
            $this->source->next();
        }
        if (!$this->source->valid()) {
            return false;
        }
        $this->positions[$this->_key] = (int) $this->source->key();

        return array_values($this->source->current());
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->started = false;
        parent::rewind();
    }
}
