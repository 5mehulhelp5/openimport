<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Reader;

use Iranimij\OpenImport\Model\Reader\ReaderFactory;
use Iranimij\OpenImport\Model\Reader\ReaderOptions;
use PHPUnit\Framework\TestCase;

class ReaderOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = (new ReaderFactory())->options([]);

        self::assertSame(ReaderOptions::AUTO, $options->delimiter);
        self::assertSame('"', $options->enclosure);
        self::assertTrue($options->hasHeader);
        self::assertSame(52428800, $options->maxBytes);
        self::assertSame(1000, $options->sampleSize);
    }

    public function testStoredOptionsRoundTrip(): void
    {
        $stored = [
            'delimiter' => '\t',
            'enclosure' => "'",
            'encoding' => 'Windows-1252',
            'sheet' => 'Stock',
            'record_path' => 'data.items',
            'has_header' => false,
        ];
        $options = (new ReaderFactory())->options($stored);

        self::assertSame("\t", $options->delimiter);
        self::assertSame($stored, $options->toArray());
    }

    public function testWrongTypesFallBackToDefaults(): void
    {
        $options = (new ReaderFactory())->options([
            'delimiter' => ['x'],
            'enclosure' => '',
            'max_bytes' => 5,
            'sample_size' => 'many',
        ]);

        self::assertSame(ReaderOptions::AUTO, $options->delimiter);
        self::assertSame('"', $options->enclosure);
        self::assertSame(1024, $options->maxBytes);
        self::assertSame(1000, $options->sampleSize);
    }
}
