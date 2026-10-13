<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

/**
 * What a transform chain may use besides its arguments: the target field it belongs to and that field's value map.
 */
class TransformScope
{
    /**
     * @param string $field target field (for messages)
     * @param ValueMap|null $valueMap
     */
    public function __construct(
        public readonly string $field,
        public readonly ?ValueMap $valueMap = null
    ) {
    }
}
