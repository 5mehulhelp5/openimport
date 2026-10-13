<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Entity;

/**
 * What OpenImport needs to know about an import entity beyond core's own definition.
 */
class EntityInfo
{
    /**
     * @param string $code core entity code (import.xml) or OpenImport's own
     * @param string[] $identifier core columns that identify one record (the delta and preview key)
     * @param bool $storeViews rows can carry store-view values (store_view_code)
     * @param string[] $storeViewCarry default-row columns repeated on store-view rows (core needs the attribute set
     *                                 and type there for new products)
     * @param string[] $synonyms extra auto-match synonyms: source name (lower case) => core column
     */
    public function __construct(
        public readonly string $code,
        public readonly array $identifier,
        public readonly bool $storeViews = false,
        public readonly array $storeViewCarry = [],
        public readonly array $synonyms = []
    ) {
    }
}
