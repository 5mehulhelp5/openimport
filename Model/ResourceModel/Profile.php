<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\ResourceModel;

use Iranimij\OpenImport\Api\Data\ProfileInterface;
use Iranimij\OpenImport\Model\JsonField;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Profiles with their mapping rows (openimport_mapping) and value maps (openimport_value_map).
 *
 * Changing the entity, source, format, row filters, mapping or value maps clears the profile's delta hashes, so the
 * next run sends every row again (Spec · Profile).
 */
class Profile extends AbstractDb
{
    public const MAPPING_TABLE = 'openimport_mapping';
    public const VALUE_MAP_TABLE = 'openimport_value_map';
    public const ROW_HASH_TABLE = 'openimport_row_hash';

    /**
     * Fields whose change makes stored delta hashes meaningless.
     */
    private const DELTA_FIELDS = [ProfileInterface::ENTITY, ProfileInterface::SOURCE_TYPE,
        ProfileInterface::SOURCE_CONFIG, ProfileInterface::FORMAT, ProfileInterface::FORMAT_OPTIONS,
        ProfileInterface::ROW_FILTERS, ProfileInterface::MAPPING, ProfileInterface::VALUE_MAPS,
        ProfileInterface::BEHAVIOR];

    /**
     * @param Context $context
     * @param JsonField $json
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly JsonField $json,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('openimport_profile', ProfileInterface::PROFILE_ID);
    }

    /**
     * The id of the profile with this name (case-insensitive), or null.
     *
     * @param string $name
     * @return int|null
     */
    public function getIdByName(string $name): ?int
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), [ProfileInterface::PROFILE_ID])
                ->where('LOWER(name) = ?', mb_strtolower(trim($name)))
                ->limit(1)
        );

        return $id === false || $id === null ? null : (int) $id;
    }

    /**
     * @inheritDoc
     */
    protected function _afterLoad(AbstractModel $object)
    {
        if ($object->getId()) {
            $profileId = (int) $object->getId();
            $object->setData(ProfileInterface::MAPPING, $this->loadMapping($profileId));
            $object->setData(ProfileInterface::VALUE_MAPS, $this->loadValueMaps($profileId));
        }

        return parent::_afterLoad($object);
    }

    /**
     * @inheritDoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        $profileId = (int) $object->getId();
        if ($object->hasData(ProfileInterface::MAPPING)) {
            $this->saveMapping($profileId, (array) $object->getData(ProfileInterface::MAPPING));
        }
        if ($object->hasData(ProfileInterface::VALUE_MAPS)) {
            $this->saveValueMaps($profileId, (array) $object->getData(ProfileInterface::VALUE_MAPS));
        }
        if ($object->getOrigData(ProfileInterface::PROFILE_ID) !== null && $this->deltaInputsChanged($object)) {
            $this->clearRowHashes($profileId);
        }

        return parent::_afterSave($object);
    }

    /**
     * Removes the stored delta hashes of a profile (next run sends every row).
     *
     * @param int $profileId
     * @return void
     */
    public function clearRowHashes(int $profileId): void
    {
        $this->getConnection()->delete(
            $this->getTable(self::ROW_HASH_TABLE),
            ['profile_id = ?' => $profileId]
        );
    }

    private function deltaInputsChanged(AbstractModel $object): bool
    {
        foreach (self::DELTA_FIELDS as $field) {
            if (!$object->hasData($field)) {
                continue;
            }
            if ($this->normalize($object->getOrigData($field)) !== $this->normalize($object->getData($field))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comparable form of a field value (JSON text and arrays compare by content).
     *
     * @param mixed $value
     * @return string
     */
    private function normalize(mixed $value): string
    {
        if (is_string($value) && $value !== '' && ($value[0] === '{' || $value[0] === '[')) {
            $value = $this->json->decode($value);
        }

        return is_array($value) ? $this->json->encode($value) : (string) $value;
    }

    /**
     * @param int $profileId
     * @return array<int, array<string, mixed>>
     */
    private function loadMapping(int $profileId): array
    {
        $connection = $this->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getTable(self::MAPPING_TABLE))
                ->where('profile_id = ?', $profileId)
                ->order(['position ASC', 'mapping_id ASC'])
        );
        $mapping = [];
        foreach ($rows as $row) {
            $mapping[] = [
                'target' => (string) $row['target'],
                'source' => $row['source'] === null ? null : (string) $row['source'],
                'static' => $row['static_value'] === null ? null : (string) $row['static_value'],
                'transforms' => $this->json->decode($row['transforms']),
                'required' => (bool) $row['is_required'],
                'value_map_case_sensitive' => (bool) $row['value_map_case_sensitive'],
                'value_map_strict' => (bool) $row['value_map_strict'],
            ];
        }

        return $mapping;
    }

    /**
     * @param int $profileId
     * @return array<string, array<string, string>>
     */
    private function loadValueMaps(int $profileId): array
    {
        $connection = $this->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getTable(self::VALUE_MAP_TABLE), ['target', 'from_value', 'to_value'])
                ->where('profile_id = ?', $profileId)
                ->order(['target ASC', 'position ASC', 'value_map_id ASC'])
        );
        $maps = [];
        foreach ($rows as $row) {
            $maps[(string) $row['target']][(string) $row['from_value']] = (string) $row['to_value'];
        }

        return $maps;
    }

    /**
     * @param int $profileId
     * @param array<int|string, mixed> $mapping
     * @return void
     */
    private function saveMapping(int $profileId, array $mapping): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable(self::MAPPING_TABLE);
        $connection->delete($table, ['profile_id = ?' => $profileId]);
        $rows = [];
        foreach (array_values($mapping) as $position => $field) {
            if (!is_array($field)) {
                continue;
            }
            $text = static fn (string $key): ?string
                => isset($field[$key]) && is_scalar($field[$key]) ? (string) $field[$key] : null;
            $source = $text('source');
            $rows[] = [
                'profile_id' => $profileId,
                'position' => $position,
                'target' => trim((string) $text('target')),
                'source' => $source === null || trim($source) === '' ? null : $source,
                'static_value' => $text('static'),
                'transforms' => $this->json->encode(
                    is_array($field['transforms'] ?? null) ? array_values($field['transforms']) : []
                ),
                'is_required' => (int) !empty($field['required']),
                'value_map_case_sensitive' => (int) !empty($field['value_map_case_sensitive']),
                'value_map_strict' => (int) !empty($field['value_map_strict']),
            ];
        }
        if ($rows !== []) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * @param int $profileId
     * @param array<int|string, mixed> $valueMaps
     * @return void
     */
    private function saveValueMaps(int $profileId, array $valueMaps): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable(self::VALUE_MAP_TABLE);
        $connection->delete($table, ['profile_id = ?' => $profileId]);
        $rows = [];
        foreach ($valueMaps as $target => $pairs) {
            if (!is_array($pairs) || trim((string) $target) === '') {
                continue;
            }
            $position = 0;
            foreach ($pairs as $from => $to) {
                $rows[] = [
                    'profile_id' => $profileId,
                    'target' => trim((string) $target),
                    'from_value' => mb_substr((string) $from, 0, 255),
                    'to_value' => is_scalar($to) ? (string) $to : '',
                    'position' => $position++,
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $connection->insertMultiple($table, $chunk);
        }
    }
}
