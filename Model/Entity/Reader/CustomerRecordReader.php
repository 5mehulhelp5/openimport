<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Entity\Reader;

use Iranimij\OpenImport\Model\Entity\RecordReaderInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Current customer values in import-file terms. Identifier: "e-mail|website code". Address columns are not
 * compared.
 */
class CustomerRecordReader implements RecordReaderInterface
{
    private const BATCH = 500;
    private const SKIPPED = ['email', '_website', '_store', 'password', 'password_hash', 'rp_token',
        'rp_token_created_at'];

    /**
     * @param ResourceConnection $resource
     * @param CollectionFactory $collectionFactory
     * @param EavConfig $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param ValueFormatter $formatter
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly EavConfig $eavConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ValueFormatter $formatter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function existing(array $identifiers): array
    {
        $existing = [];
        foreach ($this->load($identifiers, []) as $key => $row) {
            $existing[$key] = true;
        }

        return $existing;
    }

    /**
     * @inheritDoc
     */
    public function current(array $identifiers, array $columns, ?string $storeCode = null): array
    {
        $attributes = [];
        foreach ($columns as $column) {
            if (in_array($column, self::SKIPPED, true) || str_starts_with($column, '_address_')) {
                continue;
            }
            $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, $column);
            if ($attribute && $attribute->getId()) {
                $attributes[$column] = $attribute;
            }
        }

        return $this->load($identifiers, $attributes);
    }

    /**
     * @inheritDoc
     */
    public function total(): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($this->resource->getTableName('customer_entity'), ['COUNT(*)'])
        );
    }

    /**
     * @param string[] $identifiers
     * @param array<string, \Magento\Eav\Model\Entity\Attribute\AbstractAttribute> $attributes
     * @return array<string, array<string, string>>
     */
    private function load(array $identifiers, array $attributes): array
    {
        $websites = [];
        foreach ($this->storeManager->getWebsites(true) as $website) {
            $websites[(int) $website->getId()] = mb_strtolower((string) $website->getCode());
        }
        $emails = [];
        foreach ($identifiers as $identifier) {
            $emails[] = explode('|', $identifier, 2)[0];
        }
        $wanted = array_flip(array_map('mb_strtolower', $identifiers));
        $values = [];
        foreach (array_chunk(array_values(array_unique($emails)), self::BATCH) as $chunk) {
            $collection = $this->collectionFactory->create();
            $collection->addAttributeToFilter('email', ['in' => $chunk]);
            foreach ($attributes as $attribute) {
                $collection->addAttributeToSelect($attribute->getAttributeCode());
            }
            foreach ($collection as $customer) {
                $key = mb_strtolower((string) $customer->getEmail()) . '|'
                    . ($websites[(int) $customer->getWebsiteId()] ?? '');
                if (!isset($wanted[$key])) {
                    continue;
                }
                $row = [];
                foreach ($attributes as $column => $attribute) {
                    $row[$column] = $this->formatter->format($attribute, $customer->getData($column));
                }
                $values[$key] = $row;
            }
        }

        return $values;
    }
}
