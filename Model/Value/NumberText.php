<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Value;

/**
 * Writes a float as a plain decimal the way a person would type it: 15 significant digits (no binary noise such as
 * 0.30000000000000004), no exponent, no trailing zeros, no "-0".
 */
class NumberText
{
    public function fromFloat(float $value): string
    {
        if (!is_finite($value)) {
            return '';
        }
        $text = sprintf('%.15G', $value);
        if (stripos($text, 'E') !== false) {
            $text = $this->expand($text);
        }
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $text === '-0' ? '0' : $text;
    }

    private function expand(string $text): string
    {
        [$mantissa, $exponent] = explode('E', strtoupper($text));
        $negative = str_starts_with($mantissa, '-');
        $mantissa = ltrim($mantissa, '+-');
        [$whole, $fraction] = array_pad(explode('.', $mantissa), 2, '');
        $digits = $whole . $fraction;
        $point = strlen($whole) + (int) $exponent;
        if ($point <= 0) {
            $result = '0.' . str_repeat('0', -$point) . $digits;
        } elseif ($point >= strlen($digits)) {
            $result = $digits . str_repeat('0', $point - strlen($digits));
        } else {
            $result = substr($digits, 0, $point) . '.' . substr($digits, $point);
        }

        return ($negative ? '-' : '') . $result;
    }
}
