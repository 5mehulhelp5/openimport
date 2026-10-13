<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Storage;

use Magento\Framework\App\ResourceConnection;

/**
 * Delta hashes per profile and record (openimport_row_hash): what was last imported for an identifier.
 */
class RowHashStore
{
    private const TABLE = 'openimport_row_hash';
    private const BATCH = 1000;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Stored hashes for these identifiers.
     *
     * @param int $profileId
     * @param string[] $identifiers
     * @return array<string, string> identifier => hash
     */
    public function fetch(int $profileId, array $identifiers): array
    {
        $connection = $this->resource->getConnection();
        $hashes = [];
        foreach (array_chunk(array_values(array_unique($identifiers)), self::BATCH) as $chunk) {
            $pairs = $connection->fetchPairs(
                $connection->select()
                    ->from($this->resource->getTableName(self::TABLE), ['identifier', 'hash'])
                    ->where('profile_id = ?', $profileId)
                    ->where('identifier IN (?)', $chunk)
            );
            foreach ($pairs as $identifier => $hash) {
                $hashes[(string) $identifier] = (string) $hash;
            }
        }

        return $hashes;
    }

    /**
     * @param int $profileId
     * @param array<string, string> $hashes identifier => hash
     * @param int|null $runId
     * @return void
     */
    public function save(int $profileId, array $hashes, ?int $runId = null): void
    {
        $rows = [];
        foreach ($hashes as $identifier => $hash) {
            $rows[] = [
                'profile_id' => $profileId,
                'identifier' => mb_substr((string) $identifier, 0, 255),
                'hash' => $hash,
                'run_id' => $runId,
            ];
        }
        $connection = $this->resource->getConnection();
        foreach (array_chunk($rows, self::BATCH) as $chunk) {
            $connection->insertOnDuplicate($this->resource->getTableName(self::TABLE), $chunk, ['hash', 'run_id']);
        }
    }

    /**
     * @param int $profileId
     * @param string[] $identifiers
     * @return void
     */
    public function delete(int $profileId, array $identifiers): void
    {
        $connection = $this->resource->getConnection();
        foreach (array_chunk(array_values($identifiers), self::BATCH) as $chunk) {
            $connection->delete(
                $this->resource->getTableName(self::TABLE),
                ['profile_id = ?' => $profileId, 'identifier IN (?)' => $chunk]
            );
        }
    }

    /**
     * @param int $profileId
     * @return void
     */
    public function clear(int $profileId): void
    {
        $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['profile_id = ?' => $profileId]
        );
    }

    /**
     * @param int $profileId
     * @return int
     */
    public function count(int $profileId): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), ['COUNT(*)'])
                ->where('profile_id = ?', $profileId)
        );
    }
}
