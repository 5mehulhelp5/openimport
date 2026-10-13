<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Value;

use Iranimij\OpenImport\Model\Value\NumberText;
use PHPUnit\Framework\TestCase;

class NumberTextTest extends TestCase
{
    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function numbers(): array
    {
        return [
            'whole' => [3.0, '3'],
            'price' => [9.95, '9.95'],
            'binary noise' => [0.1 + 0.2, '0.3'],
            'negative' => [-2.5, '-2.5'],
            'negative zero' => [-0.0, '0'],
            'tiny' => [1.0E-7, '0.0000001'],
            'tiny with digits' => [-1.25E-7, '-0.000000125'],
            'huge' => [1.0E20, '100000000000000000000'],
            'huge with digits' => [1.5E16, '15000000000000000'],
            'not finite' => [INF, ''],
        ];
    }

    /**
     * @dataProvider numbers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('numbers')]
    public function testFromFloat(float $value, string $expected): void
    {
        self::assertSame($expected, (new NumberText())->fromFloat($value));
    }
}
