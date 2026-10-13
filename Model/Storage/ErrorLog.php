<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Storage;

use Iranimij\OpenImport\Model\JsonField;
use Magento\Framework\App\ResourceConnection;

/**
 * Stores run errors (openimport_run_error) in batches. The run counts every error; only the first
 * openimport/general/max_errors_per_run are stored.
 */
class ErrorLog
{
    private const TABLE = 'openimport_run_error';
    private const MAX_MESSAGE = 2000;

    /**
     * @param ResourceConnection $resource
     * @param JsonField $json
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly JsonField $json
    ) {
    }

    /**
     * @param int $runId
     * @param ErrorRecord[] $errors
     * @return void
     */
    public function add(int $runId, array $errors): void
    {
        $rows = [];
        foreach ($errors as $error) {
            $rows[] = [
                'run_id' => $runId,
                'source_row' => max(0, $error->sourceRow),
                'identifier' => mb_substr($error->identifier, 0, 255),
                'field' => mb_substr($error->field, 0, 255),
                'message' => mb_substr($error->message, 0, self::MAX_MESSAGE),
                'source_data' => $error->sourceData === null ? null : $this->json->encode($error->sourceData),
            ];
        }
        $connection = $this->resource->getConnection();
        foreach (array_chunk($rows, 500) as $chunk) {
            $connection->insertMultiple($this->resource->getTableName(self::TABLE), $chunk);
        }
    }

    /**
     * @param int $runId
     * @return int stored errors
     */
    public function count(int $runId): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), ['COUNT(*)'])
                ->where('run_id = ?', $runId)
        );
    }

    /**
     * Errors in source-row order.
     *
     * @param int $runId
     * @param int $limit
     * @param int $offset
     * @return ErrorRecord[]
     */
    public function fetch(int $runId, int $limit = 100, int $offset = 0): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE))
                ->where('run_id = ?', $runId)
                ->order(['source_row ASC', 'error_id ASC'])
                ->limit($limit, $offset)
        );

        return array_map(fn (array $row): ErrorRecord => new ErrorRecord(
            (int) $row['source_row'],
            (string) $row['message'],
            (string) $row['identifier'],
            (string) $row['field'],
            $row['source_data'] === null ? null : array_map('strval', $this->json->decode($row['source_data']))
        ), $rows);
    }
}
