<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Value;

/**
 * Reads numbers the way merchants write them in files: "1234.5", "1,5", "1.234,56", "1,234.56", "1 234,5",
 * "1'234.50", "-3", "+3", "1e3".
 *
 * Rules: spaces and apostrophes are thousands separators. With both "." and ",", the last one is the decimal
 * separator. A single kind of separator is the decimal separator when it appears once, a thousands separator when it
 * appears several times. Exception for decimal-comma files: in "1.234" the dot is a thousands separator.
 */
class NumberParser
{
    /**
     * The number, or null when the text is not a number. Empty text is not a number.
     *
     * @param string $text
     * @param bool $decimalComma the file writes decimals with a comma ("1.234" means 1234)
     * @return float|null
     */
    public function parse(string $text, bool $decimalComma = false): ?float
    {
        $clean = str_replace([' ', "\u{00A0}", "\u{202F}", "'", "\u{2019}"], '', trim($text));
        if ($clean === '') {
            return null;
        }
        if (is_numeric($clean) && !$decimalComma) {
            return (float) $clean;
        }
        $dot = strrpos($clean, '.');
        $comma = strrpos($clean, ',');
        if ($dot !== false && $comma !== false) {
            $decimal = $dot > $comma ? '.' : ',';
            $thousands = $decimal === '.' ? ',' : '.';
            $clean = str_replace($thousands, '', $clean);
            $clean = str_replace($decimal, '.', $clean);
        } elseif ($comma !== false) {
            $clean = substr_count($clean, ',') === 1 ? str_replace(',', '.', $clean) : str_replace(',', '', $clean);
        } elseif ($dot !== false && (substr_count($clean, '.') > 1
            || ($decimalComma && preg_match('/^[+-]?\d{1,3}(\.\d{3})+$/', $clean) === 1))) {
            $clean = str_replace('.', '', $clean);
        }
        if (!is_numeric($clean) || substr_count($clean, '.') > 1) {
            return null;
        }

        return (float) $clean;
    }

    /**
     * True when the text is a number in plain notation ("12", "-1.5"), the form compared numerically.
     *
     * @param string $text
     * @return bool
     */
    public function isPlain(string $text): bool
    {
        return preg_match('/^\s*[+-]?(\d+\.?\d*|\.\d+)\s*$/', $text) === 1;
    }
}
