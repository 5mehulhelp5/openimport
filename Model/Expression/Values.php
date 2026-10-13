<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Expression;

use Iranimij\OpenImport\Model\Value\NumberParser;
use Iranimij\OpenImport\Model\Value\NumberText;

/**
 * How expression values convert. Values are text, numbers or booleans.
 *
 * - Text of a number: plain decimal ("0.3", never "0.30000000000000004"); booleans are "1" / "0".
 * - Number of a text: as merchants write it ("1,5", "1.234,56"); empty text has no number.
 * - Truth: '', '0', 0 and false are false, everything else is true.
 */
class Values
{
    private NumberParser $parser;

    private NumberText $numberText;

    public function __construct()
    {
        $this->parser = new NumberParser();
        $this->numberText = new NumberText();
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_float($value) => $this->numberText->fromFloat($value),
            is_int($value) => (string) $value,
            default => (string) $value,
        };
    }

    /**
     * The number of a value; null for empty text.
     *
     * @param mixed $value
     * @return float|null
     * @throws EvaluationException when the value is text that is not a number
     */
    public function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        $text = (string) $value;
        if (trim($text) === '') {
            return null;
        }
        $number = $this->parser->parse($text);
        if ($number === null) {
            throw new EvaluationException(sprintf('"%s" is not a number.', mb_strimwidth($text, 0, 40, '…')));
        }

        return $number;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        $text = trim((string) $value);

        return $text !== '' && $text !== '0';
    }

    /**
     * Compares two values: as numbers when both are numbers or plain numeric text, otherwise as text ignoring case.
     *
     * @param mixed $left
     * @param mixed $right
     * @return int -1, 0 or 1
     */
    public function compare(mixed $left, mixed $right): int
    {
        if ($this->isNumeric($left) && $this->isNumeric($right)) {
            return (float) $this->number($left) <=> (float) $this->number($right);
        }

        return mb_strtolower($this->text($left)) <=> mb_strtolower($this->text($right));
    }

    private function isNumeric(mixed $value): bool
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return true;
        }

        return $this->parser->isPlain((string) $value);
    }
}
