<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Mapping;

/**
 * One row of a profile's mapping: where a core column's value comes from and how it is changed.
 */
class FieldMapping
{
    public const STORE_PREFIX = 'store:';

    /**
     * @param string $target core column, or "store:<store view code>:<column>" for a store-view value
     * @param string|null $source source column; null when the value is static (or built by an expression)
     * @param string|null $static fixed value used when there is no source column
     * @param array<int, mixed> $transforms transform definitions (see TransformPool)
     * @param bool $required an empty result is a row error
     */
    public function __construct(
        public readonly string $target,
        public readonly ?string $source = null,
        public readonly ?string $static = null,
        public readonly array $transforms = [],
        public readonly bool $required = false
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'source' => $this->source,
            'static' => $this->static,
            'transforms' => $this->transforms,
            'required' => $this->required,
        ];
    }

    /**
     * The store view code for a store-view target, else null.
     */
    public function storeView(): ?string
    {
        if (!str_starts_with($this->target, self::STORE_PREFIX)) {
            return null;
        }
        $parts = explode(':', $this->target, 3);

        return count($parts) === 3 && $parts[1] !== '' ? $parts[1] : null;
    }

    /**
     * The core column without the store-view prefix.
     */
    public function column(): string
    {
        if (!str_starts_with($this->target, self::STORE_PREFIX)) {
            return $this->target;
        }
        $parts = explode(':', $this->target, 3);

        return $parts[2] ?? '';
    }
}
