<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Reader;

use Iranimij\OpenImport\Model\Reader\ReaderException;
use Iranimij\OpenImport\Model\Reader\ReaderOptions;
use Iranimij\OpenImport\Model\Reader\XlsxReader;
use PHPUnit\Framework\TestCase;

class XlsxReaderTest extends TestCase
{
    use TempFiles;

    private const STYLES = '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/>'
        . '<numFmt numFmtId="165" formatCode="&quot;Day&quot; 0.00"/></numFmts>'
        . '<cellXfs count="5"><xf numFmtId="0"/><xf numFmtId="14"/><xf numFmtId="164"/><xf numFmtId="165"/>'
        . '<xf numFmtId="4"/></cellXfs>';

    public function testValuesOfEveryCellType(): void
    {
        $rows = '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="inlineStr">'
            . '<is><t>qty</t></is></c><c r="D1" t="s"><v>2</v></c><c r="E1" t="s"><v>3</v></c>'
            . '<c r="F1" t="s"><v>4</v></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>5</v></c><c r="B2"><v>0.30000000000000004</v></c><c r="C2"><v>12</v></c>'
            . '<c r="D2" t="b"><v>1</v></c><c r="E2" t="str"><f>A2&amp;"x"</f><v>A-1x</v></c><c r="F2" t="e"><v>#N/A</v></c>'
            . '</row>';
        $reader = new XlsxReader($this->xlsx(
            ['Products' => $rows],
            ['sku', 'price', 'active', 'formula', 'error', '<r><t>A-</t></r><r><t>1</t></r><rPh><t>ignored</t></rPh>']
        ));

        self::assertSame(['sku', 'price', 'qty', 'active', 'formula', 'error'], $reader->columns());
        self::assertSame(
            [2 => ['sku' => 'A-1', 'price' => '0.3', 'qty' => '12', 'active' => '1', 'formula' => 'A-1x', 'error' => '']],
            iterator_to_array($reader->rows())
        );
        self::assertSame(['sheet' => 'Products'], $reader->detected());
    }

    public function testDatesAndNumbers(): void
    {
        $rows = '<row r="1"><c r="A1" t="inlineStr"><is><t>date</t></is></c><c r="B1" t="inlineStr"><is><t>time</t></is></c>'
            . '<c r="C1" t="inlineStr"><is><t>custom</t></is></c><c r="D1" t="inlineStr"><is><t>money</t></is></c>'
            . '<c r="E1" t="inlineStr"><is><t>tiny</t></is></c></row>'
            . '<row r="2"><c r="A2" s="1"><v>45292</v></c><c r="B2" s="2"><v>45292.5</v></c>'
            . '<c r="C2" s="3"><v>2.5</v></c><c r="D2" s="4"><v>1234.5</v></c><c r="E2"><v>1E-7</v></c></row>';
        $reader = new XlsxReader($this->xlsx(['S' => $rows], [], self::STYLES));

        self::assertSame(
            [2 => ['date' => '2024-01-01', 'time' => '2024-01-01 12:00:00', 'custom' => '2.5', 'money' => '1234.5', 'tiny' => '0.0000001']],
            iterator_to_array($reader->rows())
        );
    }

    public function testThe1904DateSystem(): void
    {
        $rows = '<row r="1"><c r="A1" t="inlineStr"><is><t>d</t></is></c></row><row r="2"><c r="A2" s="1"><v>0</v></c></row>';
        $reader = new XlsxReader($this->xlsx(['S' => $rows], [], self::STYLES, true));

        self::assertSame([2 => ['d' => '1904-01-01']], iterator_to_array($reader->rows()));
    }

    public function testSparseCellsAndRowNumbersFromTheSheet(): void
    {
        $rows = '<row r="1"><c r="A1" t="inlineStr"><is><t>a</t></is></c><c r="C1" t="inlineStr"><is><t>c</t></is></c></row>'
            . '<row r="3"><c r="C3"><v>3</v></c></row>'
            . '<row r="4"><c r="A4" t="inlineStr"><is><t></t></is></c></row>'
            . '<row r="7"><c r="A7"><v>1</v></c><c r="E7"><v>5</v></c></row>';
        $reader = new XlsxReader($this->xlsx(['S' => $rows]));

        self::assertSame(['a', 'column_2', 'c'], $reader->columns());
        self::assertSame(
            [3 => ['a' => '', 'column_2' => '', 'c' => '3'], 7 => ['a' => '1', 'column_2' => '', 'c' => '']],
            iterator_to_array($reader->rows())
        );
    }

    public function testCellsWithoutReferences(): void
    {
        $rows = '<row><c t="inlineStr"><is><t>a</t></is></c><c t="inlineStr"><is><t>b</t></is></c></row>'
            . '<row><c><v>1</v></c><c><v>2</v></c></row>';
        $reader = new XlsxReader($this->xlsx(['S' => $rows]));

        self::assertSame([2 => ['a' => '1', 'b' => '2']], iterator_to_array($reader->rows()));
    }

    public function testSheetByNameOrPosition(): void
    {
        $file = $this->xlsx([
            'First' => '<row r="1"><c r="A1"><v>1</v></c></row>',
            'Stock' => '<row r="1"><c r="A1" t="inlineStr"><is><t>sku</t></is></c></row><row r="2"><c r="A2"><v>2</v></c></row>',
        ]);

        $byName = new XlsxReader($file, new ReaderOptions(sheet: 'stock'));
        self::assertSame([2 => ['sku' => '2']], iterator_to_array($byName->rows()));
        self::assertSame(['sheet' => 'Stock'], $byName->detected());

        $byPosition = new XlsxReader($file, new ReaderOptions(sheet: '2'));
        self::assertSame(['sheet' => 'Stock'], $byPosition->detected());

        self::assertSame(['First', 'Stock'], $byName->sheetNames());
    }

    public function testUnknownSheetListsTheSheets(): void
    {
        $reader = new XlsxReader($this->xlsx(['One' => '', 'Two' => '']), new ReaderOptions(sheet: 'Three'));

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('Sheets: One, Two.');
        $reader->columns();
    }

    public function testFileWithoutHeader(): void
    {
        $rows = '<row r="1"><c r="A1"><v>1</v></c><c r="B1"><v>2</v></c></row>';
        $reader = new XlsxReader($this->xlsx(['S' => $rows]), new ReaderOptions(hasHeader: false));

        self::assertSame(['column_1', 'column_2'], $reader->columns());
        self::assertSame([1 => ['column_1' => '1', 'column_2' => '2']], iterator_to_array($reader->rows()));
    }

    public function testEmptySheet(): void
    {
        $reader = new XlsxReader($this->xlsx(['S' => '<row r="1"/>']));

        self::assertSame([], $reader->columns());
        self::assertSame([], iterator_to_array($reader->rows()));
    }

    public function testNotAWorkbook(): void
    {
        $reader = new XlsxReader($this->tempFile('sku,qty', 'xlsx'));

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('not an Excel workbook');
        $reader->columns();
    }

    public function testZipWithoutWorkbook(): void
    {
        $file = $this->tempFile('', 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'hello');
        $zip->close();
        $reader = new XlsxReader($file);

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('not an Excel workbook');
        $reader->columns();
    }

    public function testMissingFileIsRefused(): void
    {
        $this->expectException(ReaderException::class);
        new XlsxReader('/nonexistent/file.xlsx');
    }
}
