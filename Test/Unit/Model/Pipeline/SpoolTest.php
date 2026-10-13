<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Pipeline;

use Iranimij\OpenImport\Model\Pipeline\PipelineException;
use Iranimij\OpenImport\Model\Pipeline\Spool;
use PHPUnit\Framework\TestCase;

class SpoolTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/openimport-spool-' . bin2hex(random_bytes(4)) . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testRowsComeBackInColumnOrderAsOftenAsNeeded(): void
    {
        $spool = new Spool($this->file, ['sku', 'name', 'store_view_code']);
        $spool->add(['name' => "Shirt \"red\"\nline", 'sku' => 'A'], 2, 'A');
        $spool->add(['sku' => 'A', 'store_view_code' => 'de', 'name' => 'Hemd'], 2, 'A');
        $spool->add(['sku' => 'B', 'name' => 'Ä'], 5, 'B');

        $expected = [
            0 => ['sku' => 'A', 'name' => "Shirt \"red\"\nline", 'store_view_code' => ''],
            1 => ['sku' => 'A', 'name' => 'Hemd', 'store_view_code' => 'de'],
            2 => ['sku' => 'B', 'name' => 'Ä', 'store_view_code' => ''],
        ];
        self::assertSame($expected, iterator_to_array($spool->rows()));
        self::assertSame($expected, iterator_to_array($spool->rows()));
        self::assertSame(3, $spool->count());
        self::assertSame(['sku', 'name', 'store_view_code'], $spool->columns());
        self::assertSame(5, $spool->sourceRow(2));
        self::assertSame('A', $spool->identifier(1));
        self::assertSame(0, $spool->sourceRow(9));
        self::assertSame('', $spool->identifier(9));
        self::assertSame(['A', 'B'], $spool->allIdentifiers());
    }

    public function testAddingAfterReadingIsRefused(): void
    {
        $spool = new Spool($this->file, ['sku']);
        iterator_to_array($spool->rows());

        $this->expectException(PipelineException::class);
        $spool->add(['sku' => 'A'], 1, 'A');
    }

    public function testUnwritableFolder(): void
    {
        $this->expectException(PipelineException::class);
        new Spool('/nonexistent-folder/mapped.jsonl', ['sku']);
    }
}
