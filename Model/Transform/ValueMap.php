<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

/**
 * Replaces whole values of one field: "Ja" → "1", "rot" → "Red". Unmatched values pass through unless the map is
 * strict.
 */
class ValueMap
{
    /**
     * @var array<string, string>
     */
    private array $map = [];

    /**
     * @param array<int|string, mixed> $pairs from => to
     * @param bool $caseSensitive
     * @param bool $strict unmatched values are an error
     */
    public function __construct(
        array $pairs,
        private readonly bool $caseSensitive = false,
        private readonly bool $strict = false
    ) {
        foreach ($pairs as $from => $to) {
            $key = $this->key((string) $from);
            if (!array_key_exists($key, $this->map)) {
                $this->map[$key] = (string) $to;
            }
        }
    }

    /**
     * @param string $value
     * @return string
     * @throws TransformException when the map is strict and the value is not in it
     */
    public function apply(string $value): string
    {
        $key = $this->key($value);
        if (array_key_exists($key, $this->map)) {
            return $this->map[$key];
        }
        if ($this->strict) {
            throw new TransformException(sprintf('The value "%s" is not in the value map.', $value));
        }

        return $value;
    }

    /**
     * @return int number of entries
     */
    public function count(): int
    {
        return count($this->map);
    }

    private function key(string $value): string
    {
        $value = trim($value);

        return $this->caseSensitive ? $value : mb_strtolower($value);
    }
}
