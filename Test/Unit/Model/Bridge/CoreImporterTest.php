<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Bridge;

use Iranimij\OpenImport\Model\Bridge\CoreImport;
use Iranimij\OpenImport\Model\Bridge\CoreImportFactory;
use Iranimij\OpenImport\Model\Bridge\CoreImporter;
use Iranimij\OpenImport\Model\Bridge\ImportRequest;
use Iranimij\OpenImport\Model\Source\MappedSource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\ImportExport\Model\History;
use Magento\ImportExport\Model\HistoryFactory;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Import\Data as ImportData;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CoreImporterTest extends TestCase
{
    private CoreImport&MockObject $import;
    private ProcessingErrorAggregatorInterface&MockObject $aggregator;
    private History&MockObject $history;
    private AdapterInterface&MockObject $connection;
    private CoreImporter $importer;
    /** @var array<int, array{0: string, 1: mixed}> */
    private array $deletes = [];

    protected function setUp(): void
    {
        $this->import = $this->createMock(CoreImport::class);
        $this->aggregator = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $this->import->method('getErrorAggregator')->willReturn($this->aggregator);
        $factory = $this->createMock(CoreImportFactory::class);
        $factory->method('create')->willReturn($this->import);

        $this->history = $this->createMock(History::class);
        $historyFactory = $this->createMock(HistoryFactory::class);
        $historyFactory->method('create')->willReturn($this->history);

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('delete')->willReturnCallback(function (string $table, mixed $where): int {
            $this->deletes[] = [$table, $where];

            return 1;
        });
        $importData = $this->createMock(ImportData::class);
        $importData->method('getConnection')->willReturn($this->connection);
        $importData->method('getMainTable')->willReturn('importexport_importdata');

        $this->importer = new CoreImporter($factory, $historyFactory, $importData);
    }

    private function source(): MappedSource
    {
        return new MappedSource(['sku'], static fn (): array => [['sku' => 'A']]);
    }

    /**
     * @param string $code
     * @param int|null $row
     * @param string|null $column
     * @param string $message
     * @param string $level
     * @return ProcessingError
     */
    private function error(string $code, ?int $row, ?string $column, string $message, string $level): ProcessingError
    {
        $error = new ProcessingError();
        $error->init($code, $level, $row, $column, $message);

        return $error;
    }

    public function testDryRunValidatesOnlyAndRemovesItsBunches(): void
    {
        $request = new ImportRequest('catalog_product', Import::BEHAVIOR_APPEND, true, true, 5);
        $this->import->expects(self::once())->method('setData')->with($request->toImportData());
        $this->import->method('validateSource')->willReturn(true);
        $this->import->method('getBunchIds')->willReturn([7, 8]);
        $this->import->method('getProcessedRowsCount')->willReturn(3);
        $this->import->method('getProcessedEntitiesCount')->willReturn(2);
        $this->import->method('isImportAllowed')->willReturn(true);
        $this->import->expects(self::never())->method('importSource');
        $this->history->expects(self::never())->method('addReport');
        $this->aggregator->expects(self::once())->method('clear');
        $this->aggregator->method('getInvalidRowsCount')->willReturn(1);
        $this->aggregator->method('getAllErrors')->willReturn([
            $this->error('invalidValue', 1, 'price', 'Value for price is not valid', ProcessingError::ERROR_LEVEL_CRITICAL),
            $this->error('notice', null, '', '', ProcessingError::ERROR_LEVEL_NOT_CRITICAL),
        ]);

        $result = $this->importer->run($request, $this->source());

        self::assertTrue($result->valid);
        self::assertFalse($result->imported);
        self::assertSame([3, 2, 1, 0, 0, 0], [
            $result->rows, $result->entities, $result->invalidRows, $result->created, $result->updated, $result->deleted,
        ]);
        self::assertNull($result->historyId);
        self::assertCount(2, $result->errors);
        $error = $result->errorsForRow(1)[0];
        self::assertSame(['invalidValue', 'Value for price is not valid', 'price', true], [
            $error->code, $error->message, $error->column, $error->critical,
        ]);
        self::assertSame([null, 'notice', null, false], [
            $result->errors[1]->row, $result->errors[1]->message, $result->errors[1]->column, $result->errors[1]->critical,
        ]);
        self::assertSame([['importexport_importdata', ['id IN (?)' => [7, 8]]]], $this->deletes);
        self::assertGreaterThanOrEqual(0.0, $result->seconds);
    }

    public function testInvalidSourceIsNotImported(): void
    {
        $this->import->method('validateSource')->willReturn(false);
        $this->import->method('getBunchIds')->willReturn([]);
        $this->import->method('isImportAllowed')->willReturn(true);
        $this->import->expects(self::never())->method('importSource');
        $this->aggregator->method('getAllErrors')->willReturn([]);

        $result = $this->importer->run(new ImportRequest('customer'), $this->source());

        self::assertFalse($result->valid);
        self::assertFalse($result->imported);
        self::assertSame([], $this->deletes);
    }

    public function testImportNotAllowedByTheAdapterIsNotImported(): void
    {
        $this->import->method('validateSource')->willReturn(true);
        $this->import->method('getBunchIds')->willReturn([3]);
        $this->import->method('isImportAllowed')->willReturn(false);
        $this->import->expects(self::never())->method('importSource');
        $this->aggregator->method('getAllErrors')->willReturn([]);

        $result = $this->importer->run(new ImportRequest('customer'), $this->source());

        self::assertTrue($result->valid);
        self::assertFalse($result->imported);
        self::assertSame([['importexport_importdata', ['id IN (?)' => [3]]]], $this->deletes);
    }

    public function testLiveRunWritesHistoryImportsInvalidatesIndexersAndCleansUp(): void
    {
        $calls = [];
        $this->import->method('validateSource')->willReturnCallback(static function () use (&$calls): bool {
            $calls[] = 'validate';

            return true;
        });
        $this->import->method('getBunchIds')->willReturn([11]);
        $this->import->method('isImportAllowed')->willReturn(true);
        $this->import->method('getProcessedRowsCount')->willReturn(4);
        $this->import->method('getProcessedEntitiesCount')->willReturn(4);
        $this->import->method('getCreatedItemsCount')->willReturn(3);
        $this->import->method('getUpdatedItemsCount')->willReturn(1);
        $this->import->method('getDeletedItemsCount')->willReturn(0);
        $this->import->method('setData')->willReturnCallback(function (mixed $key, mixed $value = null) use (&$calls) {
            if ($key === '_import_history_id') {
                $calls[] = 'history:' . $value;
            }

            return $this->import;
        });
        $this->history->expects(self::once())->method('addReport')->with('supplier.csv')
            ->willReturnCallback(function () use (&$calls): History {
                $calls[] = 'addReport';

                return $this->history;
            });
        $this->history->method('getId')->willReturn(42);
        $this->import->method('importSource')->willReturnCallback(static function () use (&$calls): bool {
            $calls[] = 'import';

            return true;
        });
        $this->import->method('invalidateIndex')->willReturnCallback(function () use (&$calls): Import {
            $calls[] = 'invalidate';

            return $this->import;
        });
        $this->aggregator->method('getAllErrors')->willReturn([]);

        $result = $this->importer->run(
            new ImportRequest('catalog_product', historyLabel: 'supplier.csv'),
            $this->source()
        );

        self::assertSame(['validate', 'addReport', 'history:42', 'import', 'invalidate'], $calls);
        self::assertTrue($result->imported);
        self::assertSame(42, $result->historyId);
        self::assertSame([3, 1, 0], [$result->created, $result->updated, $result->deleted]);
        self::assertSame([['importexport_importdata', ['id IN (?)' => [11]]]], $this->deletes);
    }

    public function testBunchesAreRemovedEvenWhenCoreThrows(): void
    {
        $this->import->method('validateSource')->willReturn(true);
        $this->import->method('getBunchIds')->willReturn([5]);
        $this->import->method('isImportAllowed')->willReturn(true);
        $this->history->method('getId')->willReturn(1);
        $this->import->method('importSource')->willThrowException(new \RuntimeException('disk full'));

        try {
            $this->importer->run(new ImportRequest('catalog_product'), $this->source());
            self::fail('The exception must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('disk full', $exception->getMessage());
        }
        self::assertSame([['importexport_importdata', ['id IN (?)' => [5]]]], $this->deletes);
    }

    public function testSkipErrorsLeavesOutRowsWithCriticalErrorsAndValidatesAgain(): void
    {
        $seen = [];
        $this->import->method('validateSource')->willReturnCallback(static function ($source) use (&$seen): bool {
            $skus = [];
            foreach ($source as $row) {
                $skus[] = $row['sku'];
            }
            $seen[] = $skus;

            return true;
        });
        $this->import->method('getBunchIds')->willReturnOnConsecutiveCalls([1], [2]);
        $this->import->method('isImportAllowed')->willReturn(true);
        $this->import->method('importSource')->willReturn(true);
        $this->import->method('getCreatedItemsCount')->willReturn(2);
        $this->history->method('getId')->willReturn(9);
        $this->aggregator->method('hasFatalExceptions')->willReturnOnConsecutiveCalls(true, false);
        $this->aggregator->method('getInvalidRowsCount')->willReturn(0);
        $this->aggregator->method('getAllErrors')->willReturnOnConsecutiveCalls(
            [$this->error('invalidAttrSet', 1, 'attribute_set_code', 'No such set', ProcessingError::ERROR_LEVEL_CRITICAL)],
            [$this->error('notice', 1, 'name', 'Looks odd', ProcessingError::ERROR_LEVEL_NOT_CRITICAL)]
        );
        $source = new MappedSource(['sku'], static fn (): array => [['sku' => 'A'], ['sku' => 'B'], ['sku' => 'C']]);

        $result = $this->importer->run(new ImportRequest('catalog_product', skipErrors: true, allowedErrors: 10), $source);

        self::assertSame([['A', 'B', 'C'], ['A', 'C']], $seen);
        self::assertTrue($result->imported);
        self::assertSame(2, $result->created);
        self::assertSame(1, $result->invalidRows);
        self::assertSame([1, 2], array_map(static fn ($error): ?int => $error->row, $result->errors), 'rows of the original source');
        self::assertSame(
            [['importexport_importdata', ['id IN (?)' => [1]]], ['importexport_importdata', ['id IN (?)' => [2]]]],
            $this->deletes
        );
    }

    public function testSkipErrorsDoesNotImportWhenACriticalErrorHasNoRow(): void
    {
        $this->import->method('validateSource')->willReturn(true);
        $this->import->method('getBunchIds')->willReturn([4]);
        $this->import->method('isImportAllowed')->willReturn(true);
        $this->import->expects(self::never())->method('importSource');
        $this->aggregator->method('hasFatalExceptions')->willReturn(true);
        $this->aggregator->method('getAllErrors')->willReturn(
            [$this->error('systemException', null, null, 'Broken', ProcessingError::ERROR_LEVEL_CRITICAL)]
        );

        $result = $this->importer->run(new ImportRequest('catalog_product', skipErrors: true), $this->source());

        self::assertFalse($result->valid);
        self::assertFalse($result->imported);
        self::assertSame('Broken', $result->errors[0]->message);
    }

    public function testRequestMapsToCoreImportData(): void
    {
        $data = (new ImportRequest('customer', Import::BEHAVIOR_DELETE, false, false, 2, '|', '__CLEAR__', 'supplier'))
            ->toImportData();

        self::assertSame([
            'entity' => 'customer',
            'behavior' => 'delete',
            Import::FIELD_NAME_VALIDATION_STRATEGY => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_STOP_ON_ERROR,
            Import::FIELD_NAME_ALLOWED_ERROR_COUNT => 2,
            Import::FIELD_FIELD_SEPARATOR => ',',
            Import::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR => '|',
            Import::FIELD_EMPTY_ATTRIBUTE_VALUE_CONSTANT => '__CLEAR__',
            Import::FIELD_NAME_IMG_FILE_DIR => 'supplier',
        ], $data);
        self::assertArrayNotHasKey(
            Import::FIELD_NAME_IMG_FILE_DIR,
            (new ImportRequest('customer', allowedErrors: -3))->toImportData()
        );
        self::assertSame(0, (new ImportRequest('customer', allowedErrors: -3))->toImportData()[Import::FIELD_NAME_ALLOWED_ERROR_COUNT]);
    }
}
