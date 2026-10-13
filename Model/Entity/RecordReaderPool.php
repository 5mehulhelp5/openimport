<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Entity;

/**
 * Record readers by entity code (di.xml argument "readers").
 */
class RecordReaderPool
{
    /**
     * @param array<string, RecordReaderInterface> $readers
     */
    public function __construct(
        private readonly array $readers = []
    ) {
    }

    /**
     * @param string $entity
     * @return RecordReaderInterface|null
     */
    public function get(string $entity): ?RecordReaderInterface
    {
        return $this->readers[$entity] ?? null;
    }
}
