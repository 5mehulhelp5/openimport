<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model;

/**
 * JSON columns of OpenImport's tables: stored as text, used as arrays. Broken JSON reads as an empty array.
 */
class JsonField
{
    /**
     * @param mixed $stored
     * @return array<int|string, mixed>
     */
    public function decode(mixed $stored): array
    {
        if (is_array($stored)) {
            return $stored;
        }
        if (!is_string($stored) || $stored === '') {
            return [];
        }
        $value = json_decode($stored, true);

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int|string, mixed> $value
     * @return string
     */
    public function encode(array $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
