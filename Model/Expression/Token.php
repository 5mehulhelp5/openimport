<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Expression;

/**
 * One token of an expression.
 */
class Token
{
    public const NUMBER = 'number';
    public const STRING = 'string';
    public const COLUMN = 'column';
    public const NAME = 'name';
    public const OPERATOR = 'operator';
    public const END = 'end';

    /**
     * @param string $type one of the constants
     * @param string $text the token's value (string literals unescaped, column names without braces)
     * @param int $position 1-based character position in the expression
     */
    public function __construct(
        public readonly string $type,
        public readonly string $text,
        public readonly int $position
    ) {
    }

    /**
     * @param string $type
     * @param string|null $text
     * @return bool
     */
    public function is(string $type, ?string $text = null): bool
    {
        if ($this->type !== $type) {
            return false;
        }

        return $text === null || ($type === self::NAME ? strcasecmp($this->text, $text) === 0 : $this->text === $text);
    }
}
