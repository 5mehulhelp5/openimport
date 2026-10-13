<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Source;

use Iranimij\OpenImport\Model\Source\MappedSource;
use PHPUnit\Framework\TestCase;

class MappedSourceTest extends TestCase
{
    public function testRowsComeInColumnOrderWithEmptyStringsForMissingColumns(): void
    {
        $source = new MappedSource(['sku', 'name', 'price'], static fn (): array => [
            ['price' => '10', 'sku' => 'A'],
            ['sku' => 'B', 'name' => 'Bee', 'price' => null],
        ]);

        self::assertSame(['sku', 'name', 'price'], $source->getColNames());
        self::assertSame(
            [0 => ['sku' => 'A', 'name' => '', 'price' => '10'], 1 => ['sku' => 'B', 'name' => 'Bee', 'price' => '']],
            iterator_to_array($source)
        );
    }

    public function testCellValuesBecomeStringsLikeInACsvFile(): void
    {
        $source = new MappedSource(['a', 'b', 'c', 'd', 'e', 'f', 'g'], static fn (): array => [[
            'a' => 3,
            'b' => 2.5,
            'c' => true,
            'd' => false,
            'e' => 1.0E-7,
            'f' => -0.0,
            'g' => new class implements \Stringable {
                public function __toString(): string
                {
                    return 'text';
                }
            },
        ]]);

        $source->rewind();
        self::assertSame(
            ['a' => '3', 'b' => '2.5', 'c' => '1', 'd' => '0', 'e' => '0.0000001', 'f' => '0', 'g' => 'text'],
            $source->current()
        );
    }

    public function testEveryRewindStartsANewIterationOfTheFactory(): void
    {
        $calls = 0;
        $source = new MappedSource(['sku'], static function () use (&$calls): \Generator {
            $calls++;
            yield ['sku' => 'A'];
            yield ['sku' => 'B'];
        });

        self::assertSame([['sku' => 'A'], ['sku' => 'B']], array_values(iterator_to_array($source)));
        self::assertSame([['sku' => 'A'], ['sku' => 'B']], array_values(iterator_to_array($source)));
        self::assertSame(2, $calls);
    }

    public function testKeysAreZeroBasedRowPositionsAndSeekWorks(): void
    {
        $rows = new class implements \IteratorAggregate {
            /**
             * @return \Generator<int, array<string, string>>
             */
            public function getIterator(): \Generator
            {
                yield ['sku' => 'A'];
                yield ['sku' => 'B'];
                yield ['sku' => 'C'];
            }
        };
        $source = new MappedSource(['sku'], static fn (): \IteratorAggregate => $rows);

        $source->seek(2);
        self::assertSame(2, $source->key());
        self::assertSame(['sku' => 'C'], $source->current());
        $source->seek(1);
        self::assertSame(['sku' => 'B'], $source->current());

        $this->expectException(\OutOfBoundsException::class);
        $source->seek(3);
    }

    public function testAnEmptyFactoryIsAnEmptySource(): void
    {
        $source = new MappedSource(['sku'], static fn (): array => []);
        $source->rewind();
        self::assertFalse($source->valid());
    }

    public function testUnknownColumnsAreRejected(): void
    {
        $source = new MappedSource(['sku'], static fn (): array => [['sku' => 'A', 'colour' => 'red']]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column "colour" is not one of the source columns.');
        $source->rewind();
    }

    public function testRowsMustBeArrays(): void
    {
        $source = new MappedSource(['sku'], static fn (): array => ['A']);
        $this->expectException(\InvalidArgumentException::class);
        $source->rewind();
    }

    public function testCellsMustBeScalar(): void
    {
        $source = new MappedSource(['sku'], static fn (): array => [['sku' => ['A']]]);
        $this->expectException(\InvalidArgumentException::class);
        $source->rewind();
    }

    public function testInfiniteNumbersAreRejected(): void
    {
        $source = new MappedSource(['price'], static fn (): array => [['price' => INF]]);
        $this->expectException(\InvalidArgumentException::class);
        $source->rewind();
    }

    public function testFactoryMustReturnRows(): void
    {
        $source = new MappedSource(['sku'], static fn (): string => 'nope');
        $this->expectException(\InvalidArgumentException::class);
        $source->rewind();
    }

    public function testDuplicateColumnsAreRejectedByCore(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MappedSource(['sku', 'sku'], static fn (): array => []);
    }
}
