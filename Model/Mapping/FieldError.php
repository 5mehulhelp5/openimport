<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Mapping;

/**
 * Why one field of one source row could not be mapped.
 */
class FieldError
{
    /**
     * @param string $field target field ("price", "store:de:name")
     * @param string $message
     */
    public function __construct(
        public readonly string $field,
        public readonly string $message
    ) {
    }
}
