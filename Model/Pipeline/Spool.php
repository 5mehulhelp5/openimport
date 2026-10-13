<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Pipeline;

/**
 * The prepared core rows of a run, one JSON list per line in the run folder, so memory stays flat however large the
 * file is. Core reads them twice (validation, then import) through rows().
 *
 * For every core row the spool remembers its source row number and identifier (core reports errors by core row
 * position).
 */
class Spool
{
    private ?\SplFileObject $file = null;

    private int $count = 0;

    /**
     * @var int[] core row position => source row
     */
    private array $lines = [];

    /**
     * @var string[] core row position => identifier
     */
    private array $identifiers = [];

    /**
     * @param string $path file to write (created or emptied)
     * @param string[] $columns core columns, in order
     */
    public function __construct(
        private readonly string $path,
        private readonly array $columns
    ) {
        try {
            $this->file = new \SplFileObject($path, 'wb');
        } catch (\RuntimeException $exception) {
            throw new PipelineException('The run folder cannot be written: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, string> $row core row (missing columns become '')
     * @param int $sourceRow
     * @param string $identifier
     * @return void
     */
    public function add(array $row, int $sourceRow, string $identifier): void
    {
        if ($this->file === null) {
            throw new PipelineException('The spool is closed.');
        }
        $values = [];
        foreach ($this->columns as $column) {
            $values[] = $row[$column] ?? '';
        }
        $this->file->fwrite(json_encode($values, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
        $this->lines[$this->count] = $sourceRow;
        $this->identifiers[$this->count] = $identifier;
        $this->count++;
    }

    public function close(): void
    {
        if ($this->file !== null) {
            $this->file->fflush();
            $this->file = null;
        }
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return string[]
     */
    public function columns(): array
    {
        return $this->columns;
    }

    public function sourceRow(int $position): int
    {
        return $this->lines[$position] ?? 0;
    }

    public function identifier(int $position): string
    {
        return $this->identifiers[$position] ?? '';
    }

    /**
     * Identifiers in the spool, without duplicates.
     *
     * @return string[]
     */
    public function allIdentifiers(): array
    {
        return array_values(array_unique($this->identifiers));
    }

    /**
     * A new pass over the rows, as column => value.
     *
     * @return \Generator<int, array<string, string>>
     */
    public function rows(): \Generator
    {
        $this->close();
        $file = new \SplFileObject($this->path, 'rb');
        $position = 0;
        while (!$file->eof()) {
            $line = (string) $file->fgets();
            if ($line === '') {
                continue;
            }
            $values = json_decode($line, true);
            if (is_array($values) && count($values) === count($this->columns)) {
                yield $position++ => array_combine($this->columns, array_map('strval', $values));
            }
        }
    }
}
