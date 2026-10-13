<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Value;

use Iranimij\OpenImport\Model\Value\NumberParser;
use PHPUnit\Framework\TestCase;

class NumberParserTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool, 2: float|null}>
     */
    public static function numbers(): array
    {
        return [
            'plain' => ['1234.5', false, 1234.5],
            'integer' => ['-3', false, -3.0],
            'plus sign' => ['+3', false, 3.0],
            'exponent' => ['1e3', false, 1000.0],
            'decimal comma' => ['1,5', false, 1.5],
            'german thousands' => ['1.234,56', false, 1234.56],
            'english thousands' => ['1,234.56', false, 1234.56],
            'several commas' => ['1,234,567', false, 1234567.0],
            'several dots' => ['1.234.567', false, 1234567.0],
            'spaces' => [' 1 234,5 ', false, 1234.5],
            'no-break space' => ["1\u{00A0}234", false, 1234.0],
            'swiss' => ["1'234.50", false, 1234.5],
            'dot is decimal by default' => ['1.234', false, 1.234],
            'dot is thousands in decimal-comma files' => ['1.234', true, 1234.0],
            'decimal in decimal-comma files' => ['12.5', true, 12.5],
            'comma in decimal-comma files' => ['12,5', true, 12.5],
            'empty' => ['', false, null],
            'blank' => ['   ', false, null],
            'text' => ['abc', false, null],
            'currency' => ['€ 5', false, null],
            'hex' => ['0x1A', false, null],
            'two decimals' => ['1.2.3,4.5', false, null],
        ];
    }

    /**
     * @dataProvider numbers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('numbers')]
    public function testParse(string $text, bool $decimalComma, ?float $expected): void
    {
        self::assertSame($expected, (new NumberParser())->parse($text, $decimalComma));
    }

    public function testPlainNumbers(): void
    {
        $parser = new NumberParser();

        self::assertTrue($parser->isPlain(' -1.5 '));
        self::assertTrue($parser->isPlain('.5'));
        self::assertTrue($parser->isPlain('3.'));
        self::assertFalse($parser->isPlain('1,5'));
        self::assertFalse($parser->isPlain('1e3'));
        self::assertFalse($parser->isPlain(''));
    }
}
