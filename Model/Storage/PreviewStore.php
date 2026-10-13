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
 * Stores the dry-run preview sample (openimport_run_preview).
 */
class PreviewStore
{
    private const TABLE = 'openimport_run_preview';

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
     * @param PreviewRecord[] $records
     * @return void
     */
    public function add(int $runId, array $records): void
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'run_id' => $runId,
                'identifier' => mb_substr($record->identifier, 0, 255),
                'change_type' => $record->changeType,
                'diff' => $this->json->encode($record->diff),
                'source_row' => max(0, $record->sourceRow),
            ];
        }
        $connection = $this->resource->getConnection();
        foreach (array_chunk($rows, 500) as $chunk) {
            $connection->insertMultiple($this->resource->getTableName(self::TABLE), $chunk);
        }
    }

    /**
     * Changed records first, then new, deleted and unchanged; each group in source-row order.
     *
     * @param int $runId
     * @param int $limit
     * @param int $offset
     * @return PreviewRecord[]
     */
    public function fetch(int $runId, int $limit = 200, int $offset = 0): array
    {
        $connection = $this->resource->getConnection();
        $order = new \Zend_Db_Expr(
            "FIELD(change_type, 'changed', 'new', 'deleted', 'unchanged'), source_row ASC, preview_id ASC"
        );
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE))
                ->where('run_id = ?', $runId)
                ->order($order)
                ->limit($limit, $offset)
        );

        return array_map(function (array $row): PreviewRecord {
            $diff = [];
            foreach ($this->json->decode($row['diff']) as $field => $change) {
                if (is_array($change)) {
                    $diff[(string) $field] = [
                        isset($change[0]) ? (string) $change[0] : null,
                        isset($change[1]) ? (string) $change[1] : null,
                    ];
                }
            }
            return new PreviewRecord(
                (string) $row['identifier'],
                (string) $row['change_type'],
                $diff,
                (int) $row['source_row']
            );
        }, $rows);
    }
}
