<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Reader;

use Iranimij\OpenImport\Model\Reader\ReaderException;
use Iranimij\OpenImport\Model\Reader\ReaderOptions;
use Iranimij\OpenImport\Model\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

class XmlReaderTest extends TestCase
{
    use TempFiles;

    private const CATALOG = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<catalog>
  <meta><generated>today</generated></meta>
  <products>
    <product id="1">
      <sku>A</sku>
      <name><![CDATA[Shirt & tie]]></name>
      <price currency="EUR">9.95</price>
      <stock><qty>3</qty></stock>
      <category>Men</category>
      <category>Sale</category>
      <empty/>
    </product>
    <product id="2"><sku>B</sku></product>
  </products>
</catalog>
XML;

    public function testRecordsAtAPathAreFlattened(): void
    {
        $reader = new XmlReader($this->tempFile(self::CATALOG, 'xml'), new ReaderOptions(recordPath: '/catalog/products/product'));

        self::assertSame(
            [
                1 => [
                    '@id' => '1',
                    'sku' => 'A',
                    'name' => 'Shirt & tie',
                    'price' => '9.95',
                    'price.@currency' => 'EUR',
                    'stock.qty' => '3',
                    'category' => 'Men,Sale',
                    'empty' => '',
                ],
                2 => ['@id' => '2', 'sku' => 'B'],
            ],
            iterator_to_array($reader->rows())
        );
        self::assertSame(
            ['@id', 'sku', 'name', 'price', 'price.@currency', 'stock.qty', 'category', 'empty'],
            $reader->columns()
        );
        self::assertSame(['record_path' => '/catalog/products/product'], $reader->detected());
    }

    public function testAnyDepthPath(): void
    {
        $reader = new XmlReader($this->tempFile(self::CATALOG, 'xml'), new ReaderOptions(recordPath: '//product'));

        self::assertCount(2, $reader->rows());
    }

    public function testWithoutAPathTheRootChildrenAreRecords(): void
    {
        $reader = new XmlReader($this->tempFile('<items><item><sku>A</sku></item><item/><item>text</item></items>', 'xml'));

        self::assertSame(
            [1 => ['sku' => 'A'], 2 => ['value' => ''], 3 => ['value' => 'text']],
            iterator_to_array($reader->rows())
        );
        self::assertSame(['record_path' => '(children of the root element)'], $reader->detected());
    }

    public function testRepeatedChildRecordsStayAsJson(): void
    {
        $xml = '<r><p><v><s>1</s></v><v><s>2</s></v></p></r>';
        $reader = new XmlReader($this->tempFile($xml, 'xml'));

        self::assertSame([1 => ['v' => '[{"s":"1"},{"s":"2"}]']], iterator_to_array($reader->rows()));
    }

    public function testPathThatMatchesNothing(): void
    {
        $reader = new XmlReader($this->tempFile(self::CATALOG, 'xml'), new ReaderOptions(recordPath: '/catalog/product'));

        self::assertSame([], iterator_to_array($reader->rows()));
        self::assertSame([], $reader->columns());
    }

    public function testDoctypeIsRefused(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x "boom">]><r><p>&x;</p></r>';
        $reader = new XmlReader($this->tempFile($xml, 'xml'));

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('DOCTYPE');
        iterator_to_array($reader->rows());
    }

    public function testBrokenXmlIsExplained(): void
    {
        $reader = new XmlReader($this->tempFile('<r><p><sku>A</p></r>', 'xml'));

        $this->expectException(ReaderException::class);
        iterator_to_array($reader->rows());
    }

    public function testEveryCallToRowsStartsAgain(): void
    {
        $reader = new XmlReader($this->tempFile(self::CATALOG, 'xml'), new ReaderOptions(recordPath: '//product'));

        self::assertCount(2, $reader->rows());
        self::assertCount(2, $reader->rows());
    }

    public function testMissingFileIsRefused(): void
    {
        $this->expectException(ReaderException::class);
        new XmlReader('/nonexistent/file.xml');
    }
}
