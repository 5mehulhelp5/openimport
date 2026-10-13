<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Mapping;

/**
 * The result of mapping one source row: the core rows it becomes (default row first, then one per store view) or
 * the reasons it cannot be imported.
 */
class MappedRow
{
    /**
     * @param array<int, array<string, string>> $rows
     * @param FieldError[] $errors
     * @param string $identifier the record's key ("SKU-1", "a@b.de|base"), '' when it could not be built
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly string $identifier
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * The default row (store-view rows follow it in $rows).
     *
     * @return array<string, string>
     */
    public function main(): array
    {
        return $this->rows[0] ?? [];
    }
}
