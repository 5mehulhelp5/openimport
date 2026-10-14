<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Bridge;

use Iranimij\OpenImport\Model\Bridge\SkipRowsSource;
use Iranimij\OpenImport\Model\Source\MappedSource;
use PHPUnit\Framework\TestCase;

class SkipRowsSourceTest extends TestCase
{
    public function testLeavesOutRowsAndKnowsWhereTheRestCameFrom(): void
    {
        $inner = new MappedSource(['sku', 'name'], static fn (): array => [
            ['sku' => 'A', 'name' => 'a'],
            ['sku' => 'B', 'name' => 'b'],
            ['sku' => 'C', 'name' => 'c'],
            ['sku' => 'D', 'name' => 'd'],
        ]);
        $source = new SkipRowsSource($inner, [0, 2]);

        self::assertSame(['sku', 'name'], $source->getColNames());
        for ($pass = 0; $pass < 2; $pass++) {
            $rows = [];
            foreach ($source as $key => $row) {
                $rows[$key] = $row;
            }
            self::assertSame([0 => ['sku' => 'B', 'name' => 'b'], 1 => ['sku' => 'D', 'name' => 'd']], $rows);
        }
        self::assertSame(1, $source->originalPosition(0));
        self::assertSame(3, $source->originalPosition(1));
        $source->rewind();
        $source->seek(1);
        self::assertSame('D', $source->current()['sku']);
    }

    public function testSkippingEverythingLeavesAnEmptySource(): void
    {
        $source = new SkipRowsSource(new MappedSource(['sku'], static fn (): array => [['sku' => 'A']]), [0]);
        $source->rewind();

        self::assertFalse($source->valid());
    }
}
