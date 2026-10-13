<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Reader;

use Iranimij\OpenImport\Model\Reader\Flattener;
use PHPUnit\Framework\TestCase;

class FlattenerTest extends TestCase
{
    public function testNestedRecordsBecomeDottedColumns(): void
    {
        self::assertSame(
            [
                'sku' => 'A',
                'price' => '9.95',
                'stock.qty' => '3',
                'stock.in_stock' => '1',
                'tags' => 'red,blue',
                'variants' => '[{"sku":"A-1"},{"sku":"A-2"}]',
                'note' => '',
            ],
            (new Flattener())->flatten([
                'sku' => 'A',
                'price' => 9.95,
                'stock' => ['qty' => 3, 'in_stock' => true],
                'tags' => ['red', 'blue'],
                'variants' => [['sku' => 'A-1'], ['sku' => 'A-2']],
                'note' => null,
            ])
        );
    }

    public function testEmptyKeyTakesTheParentName(): void
    {
        self::assertSame(
            ['price' => '10', 'price.@currency' => 'EUR'],
            (new Flattener())->flatten(['price' => ['' => '10', '@currency' => 'EUR']])
        );
        self::assertSame(['value' => 'x'], (new Flattener())->flatten(['' => 'x']));
    }

    public function testScalars(): void
    {
        self::assertSame('0', (new Flattener())->scalar(false));
        self::assertSame('3', (new Flattener())->scalar(3.0));
        self::assertSame('0.1', (new Flattener())->scalar(0.1));
        self::assertSame('0.3', (new Flattener())->scalar(0.1 + 0.2));
        self::assertSame('0.0000001', (new Flattener())->scalar(1.0E-7));
        self::assertSame('0', (new Flattener())->scalar(-0.0));
        self::assertSame('-2.5', (new Flattener())->scalar(-2.5));
        self::assertSame('100000000000000000000', (new Flattener())->scalar(1.0E20));
    }
}
