<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Entity;

/**
 * Reads what is in the shop now for one entity, in import-file terms, in batches (never per row).
 *
 * Identifiers are the mapping's identifiers ("SKU-1", "a@b.de|base"); matching ignores case as Magento does.
 *
 * @api
 */
interface RecordReaderInterface
{
    /**
     * Which of these identifiers exist.
     *
     * @param string[] $identifiers
     * @return array<string, true> lower-cased identifier => true
     */
    public function existing(array $identifiers): array;

    /**
     * Current values of the given import columns. Columns the reader cannot compare are left out.
     *
     * @param string[] $identifiers
     * @param string[] $columns import columns
     * @param string|null $storeCode null = default values
     * @return array<string, array<string, string>> lower-cased identifier => column => value
     */
    public function current(array $identifiers, array $columns, ?string $storeCode = null): array;

    /**
     * Number of records of the entity in the shop (safety threshold).
     *
     * @return int
     */
    public function total(): int;
}
