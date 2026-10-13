<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Reader;

use Iranimij\OpenImport\Model\Reader\JsonReader;
use Iranimij\OpenImport\Model\Reader\ReaderException;
use Iranimij\OpenImport\Model\Reader\ReaderOptions;
use PHPUnit\Framework\TestCase;

class JsonReaderTest extends TestCase
{
    use TempFiles;

    public function testListOfObjects(): void
    {
        $reader = new JsonReader($this->tempFile(
            '[{"sku":"A","price":9.5,"stock":{"qty":3}},{"sku":"B","active":false,"tags":["x","y"]}]',
            'json'
        ));

        self::assertSame(['sku', 'price', 'stock.qty', 'active', 'tags'], $reader->columns());
        self::assertSame(
            [
                1 => ['sku' => 'A', 'price' => '9.5', 'stock.qty' => '3'],
                2 => ['sku' => 'B', 'active' => '0', 'tags' => 'x,y'],
            ],
            iterator_to_array($reader->rows())
        );
        self::assertSame(['record_path' => ''], $reader->detected());
    }

    public function testTheOnlyListInAnObjectIsFound(): void
    {
        $reader = new JsonReader($this->tempFile("\xEF\xBB\xBF" . '{"total":1,"items":[{"sku":"A"}]}', 'json'));

        self::assertSame([1 => ['sku' => 'A']], iterator_to_array($reader->rows()));
        self::assertSame(['record_path' => 'items'], $reader->detected());
    }

    public function testRecordPath(): void
    {
        $file = $this->tempFile('{"data":{"products":[{"sku":"A"}],"categories":[{"id":1}]}}', 'json');

        $reader = new JsonReader($file, new ReaderOptions(recordPath: 'data.products'));
        self::assertSame([1 => ['sku' => 'A']], iterator_to_array($reader->rows()));

        $reader = new JsonReader($file, new ReaderOptions(recordPath: '/data/categories'));
        self::assertSame([1 => ['id' => '1']], iterator_to_array($reader->rows()));
    }

    public function testSeveralListsNeedARecordPath(): void
    {
        $reader = new JsonReader($this->tempFile('{"a":[{"x":1}],"b":[{"y":2}]}', 'json'));

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('several lists (a, b)');
        $reader->columns();
    }

    public function testASingleObjectIsOneRecord(): void
    {
        $reader = new JsonReader($this->tempFile('{"sku":"A","qty":2}', 'json'));

        self::assertSame([1 => ['sku' => 'A', 'qty' => '2']], iterator_to_array($reader->rows()));
    }

    public function testBigIntegersStayExact(): void
    {
        $reader = new JsonReader($this->tempFile('[{"ean":12345678901234567890123}]', 'json'));

        self::assertSame([1 => ['ean' => '12345678901234567890123']], iterator_to_array($reader->rows()));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidFiles(): array
    {
        return [
            'not json' => ['{"a":', '', 'not valid JSON'],
            'missing path' => ['{"a":[]}', 'b', 'does not exist'],
            'path to a value' => ['{"a":{"b":1}}', 'a.b', 'does not point to a list'],
            'list of values' => ['[1,2]', '', 'Record 1 at "/" is not an object'],
            'scalar document' => ['"text"', '', 'holds no records'],
        ];
    }

    /**
     * @dataProvider invalidFiles
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFiles')]
    public function testInvalidFilesAreExplained(string $json, string $path, string $message): void
    {
        $reader = new JsonReader($this->tempFile($json, 'json'), new ReaderOptions(recordPath: $path));

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage($message);
        iterator_to_array($reader->rows());
    }

    public function testSizeLimit(): void
    {
        $reader = new JsonReader($this->tempFile(str_repeat(' ', 2048) . '[]', 'json'), new ReaderOptions(maxBytes: 1024));

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('Use CSV or XML');
        $reader->columns();
    }

    public function testEmptyListHasNoRows(): void
    {
        $reader = new JsonReader($this->tempFile('[]', 'json'));

        self::assertSame([], $reader->columns());
        self::assertSame([], iterator_to_array($reader->rows()));
    }

    public function testColumnsComeFromTheSample(): void
    {
        $reader = new JsonReader(
            $this->tempFile('[{"a":1},{"b":2}]', 'json'),
            new ReaderOptions(sampleSize: 1)
        );

        self::assertSame(['a'], $reader->columns());
        self::assertSame([1 => ['a' => '1'], 2 => ['b' => '2']], iterator_to_array($reader->rows()));
    }

    public function testMissingFileIsRefused(): void
    {
        $this->expectException(ReaderException::class);
        new JsonReader('/nonexistent/file.json');
    }
}
