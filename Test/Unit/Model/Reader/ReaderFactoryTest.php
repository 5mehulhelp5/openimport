<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Reader;

use Iranimij\OpenImport\Model\Reader\CsvReader;
use Iranimij\OpenImport\Model\Reader\JsonReader;
use Iranimij\OpenImport\Model\Reader\ReaderException;
use Iranimij\OpenImport\Model\Reader\ReaderFactory;
use Iranimij\OpenImport\Model\Reader\XlsxReader;
use Iranimij\OpenImport\Model\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

class ReaderFactoryTest extends TestCase
{
    use TempFiles;

    public function testFormatFromTheExtension(): void
    {
        $factory = new ReaderFactory();

        self::assertInstanceOf(CsvReader::class, $factory->create($this->tempFile('a', 'tsv')));
        self::assertInstanceOf(JsonReader::class, $factory->create($this->tempFile('[]', 'JSON')));
        self::assertInstanceOf(XmlReader::class, $factory->create($this->tempFile('<a/>', 'xml')));
        self::assertInstanceOf(XlsxReader::class, $factory->create($this->tempFile('', 'xlsx')));
        self::assertSame('json', $factory->detect('/tmp/upload-123', 'feed.json'));
    }

    public function testFormatFromTheContent(): void
    {
        $factory = new ReaderFactory();

        self::assertSame('xlsx', $factory->detect($this->tempFile("PK\x03\x04rest", 'bin')));
        self::assertSame('json', $factory->detect($this->tempFile("\xEF\xBB\xBF  [{}]", 'bin')));
        self::assertSame('json', $factory->detect($this->tempFile('{"a":1}', 'dat')));
        self::assertSame('xml', $factory->detect($this->tempFile("\n<?xml version=\"1.0\"?><a/>", 'feed')));
        self::assertSame('csv', $factory->detect($this->tempFile('sku,qty', 'export')));
    }

    public function testTheProfileFormatWins(): void
    {
        $reader = (new ReaderFactory())->create($this->tempFile('[1]', 'txt'), 'JSON');

        self::assertInstanceOf(JsonReader::class, $reader);
    }

    public function testOldExcelFilesAreExplained(): void
    {
        $factory = new ReaderFactory();
        try {
            $factory->detect($this->tempFile("\xD0\xCF\x11\xE0rest", 'bin'));
            self::fail('Expected an exception');
        } catch (ReaderException $exception) {
            self::assertStringContainsString('.xls', $exception->getMessage());
        }

        $this->expectException(ReaderException::class);
        $factory->detect('/whatever/file.xls');
    }

    public function testUnknownFormat(): void
    {
        $this->expectException(ReaderException::class);
        (new ReaderFactory())->create($this->tempFile('a'), 'pdf');
    }

    public function testUnreadableFileWithoutExtension(): void
    {
        $this->expectException(ReaderException::class);
        (new ReaderFactory())->detect('/nonexistent/file');
    }
}
