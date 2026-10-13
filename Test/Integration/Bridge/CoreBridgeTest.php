<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Integration\Bridge;

use Iranimij\OpenImport\Model\Bridge\CoreImporter;
use Iranimij\OpenImport\Model\Bridge\ImportRequest;
use Iranimij\OpenImport\Model\Bridge\ImportResult;
use Iranimij\OpenImport\Model\Source\MappedSource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\AbstractSource;
use Magento\ImportExport\Model\Import\Source\Csv;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * M0 spike, the oracle for everything OpenImport writes: the same rows imported through OpenImport's MappedSource
 * and through core's own CSV source (what System › Data Transfer › Import uses) must give the same data.
 *
 * Each import uses its own SKU / e-mail prefix, so both results live side by side and are compared after stripping
 * the prefix.
 *
 * Isolation and area are set with attributes only: Magento's test framework reads attributes and docblock
 * annotations on every supported version and refuses a test that has both.
 */
#[AppArea('adminhtml')]
#[DbIsolation(false)]
#[AppIsolation(true)]
class CoreBridgeTest extends TestCase
{
    private const PRODUCT_COLUMNS = [
        'sku', 'store_view_code', 'attribute_set_code', 'product_type', 'product_websites', 'name', 'description',
        'short_description', 'weight', 'product_online', 'visibility', 'price', 'special_price', 'url_key', 'qty',
        'is_in_stock', 'meta_title',
    ];

    private const CUSTOMER_COLUMNS = ['email', '_website', '_store', 'firstname', 'lastname', 'group_id', 'gender', 'dob'];

    /** Nullable: the 2.4.7 test framework resets test properties to null after each test. */
    private ?CoreImporter $importer = null;

    protected function setUp(): void
    {
        $this->importer = Bootstrap::getObjectManager()->get(CoreImporter::class);
    }

    private function importer(): CoreImporter
    {
        return $this->importer ??= Bootstrap::getObjectManager()->get(CoreImporter::class);
    }

    /**
     * Product rows: simple products with stock, a special price, an HTML description, a German store-view row and an
     * empty optional cell.
     *
     * @param string $prefix
     * @return array<int, array<string, string>>
     */
    private function productRows(string $prefix): array
    {
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = [
                'sku' => $prefix . '-' . $i,
                'attribute_set_code' => 'Default',
                'product_type' => 'simple',
                'product_websites' => 'base',
                'name' => $prefix . ' Trail shoe ' . $i,
                'description' => '<p>Grip on <strong>wet</strong> rock, size ' . (38 + $i) . '</p>',
                'short_description' => $i === 3 ? '' : 'Light & fast',
                'weight' => (string) (0.25 * $i),
                'product_online' => $i === 5 ? '2' : '1',
                'visibility' => $i === 4 ? 'Not Visible Individually' : 'Catalog, Search',
                'price' => number_format(19.99 + $i * 10, 2, '.', ''),
                'special_price' => $i === 2 ? '24.50' : '',
                'url_key' => strtolower($prefix) . '-trail-shoe-' . $i,
                'qty' => (string) ($i * 7),
                'is_in_stock' => $i === 5 ? '0' : '1',
                'meta_title' => 'Trail shoe ' . $i,
            ];
        }
        // Core validates every row of a product that is new in this file as a new product, store-view rows too, so
        // they need the attribute set and the type (finding of the M0 spike; OpenImport's store-view expansion copies
        // both onto the rows it generates).
        $rows[] = [
            'sku' => $prefix . '-1',
            'store_view_code' => 'default',
            'attribute_set_code' => 'Default',
            'product_type' => 'simple',
            'name' => $prefix . ' Trailschuh 1',
        ];

        return $rows;
    }

    /**
     * @param string $prefix
     * @return array<int, array<string, string>>
     */
    private function customerRows(string $prefix): array
    {
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = [
                'email' => strtolower($prefix) . '.customer' . $i . '@example.com',
                '_website' => 'base',
                '_store' => 'default',
                'firstname' => 'Ada ' . $i,
                'lastname' => 'Müller-Lovelace',
                'group_id' => (string) (($i % 3) + 1),
                'gender' => $i === 2 ? '' : (string) (($i % 2) + 1),
                'dob' => $i === 1 ? '1990-04-' . str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT) : '',
            ];
        }

        return $rows;
    }

    /**
     * Core's own CSV source for the rows (the admin import path).
     *
     * @param string[] $columns
     * @param array<int, array<string, string>> $rows
     * @return AbstractSource
     */
    private function csvSource(array $columns, array $rows): AbstractSource
    {
        $handle = fopen('php://memory', 'w+');
        self::assertIsResource($handle);
        fputcsv($handle, $columns, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn (string $column): string => $row[$column] ?? '', $columns), ',', '"', '\\');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $directory = Bootstrap::getObjectManager()->get(Filesystem::class)->getDirectoryWrite(DirectoryList::VAR_DIR);
        $file = 'openimport-test/' . uniqid('oracle-', true) . '.csv';
        $directory->writeFile($file, $csv);

        return Bootstrap::getObjectManager()->create(Csv::class, [
            'file' => $directory->getAbsolutePath($file),
            'directory' => $directory,
        ]);
    }

    /**
     * @param string[] $columns
     * @param array<int, array<string, string>> $rows
     * @return MappedSource
     */
    private function mappedSource(array $columns, array $rows): MappedSource
    {
        return new MappedSource($columns, static function () use ($rows): \Generator {
            foreach ($rows as $row) {
                yield $row;
            }
        });
    }

    private function prefix(string $name): string
    {
        return strtoupper($name) . substr(str_replace('.', '', uniqid('', true)), -8);
    }

    /**
     * The part of a product core's import decides, with the run prefix replaced.
     *
     * @param string $sku
     * @param string $prefix
     * @return array<string, mixed>
     */
    private function productSnapshot(string $sku, string $prefix): array
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(ProductRepositoryInterface::class);
        $product = $repository->get($sku, false, 0, true);
        $german = $repository->get($sku, false, (int) $om->get(StoreManagerInterface::class)->getStore('default')->getId(), true);
        $stock = $om->get(StockRegistryInterface::class)->getStockItemBySku($sku);
        $data = [
            'type_id' => $product->getTypeId(),
            'attribute_set_id' => (int) $product->getAttributeSetId(),
            'website_ids' => array_map('intval', $product->getWebsiteIds()),
            'store_view_name' => $german->getName(),
            'qty' => (float) $stock->getQty(),
            'is_in_stock' => (bool) $stock->getIsInStock(),
        ];
        foreach (['name', 'description', 'short_description', 'weight', 'status', 'visibility', 'price',
                     'special_price', 'url_key', 'meta_title'] as $code) {
            $value = $product->getData($code);
            $data[$code] = $value === null ? null : (string) $value;
        }

        return json_decode(
            str_ireplace([$prefix, strtolower($prefix)], 'PREFIX', (string) json_encode($data)),
            true
        );
    }

    /**
     * @param string $email
     * @param string $prefix
     * @return array<string, mixed>
     */
    private function customerSnapshot(string $email, string $prefix): array
    {
        $customer = Bootstrap::getObjectManager()->get(CustomerRepositoryInterface::class)->get($email, 1);
        $data = [
            'email' => $customer->getEmail(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'group_id' => (int) $customer->getGroupId(),
            'gender' => (int) $customer->getGender(),
            'dob' => $customer->getDob(),
            'store_id' => (int) $customer->getStoreId(),
            'website_id' => (int) $customer->getWebsiteId(),
        ];

        return json_decode(str_ireplace($prefix, 'PREFIX', (string) json_encode($data)), true);
    }

    /**
     * @param ImportResult $result
     * @return string
     */
    private function describe(ImportResult $result): string
    {
        return (string) json_encode([
            'valid' => $result->valid,
            'imported' => $result->imported,
            'errors' => array_map(static fn ($e): array => (array) $e, $result->errors),
        ]);
    }

    private function importDataRows(): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);

        return (int) $resource->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM ' . $resource->getTableName('importexport_importdata')
        );
    }

    public function testProductsImportedThroughMappedSourceEqualCoreCsvImport(): void
    {
        $core = $this->prefix('core');
        $ours = $this->prefix('oi');

        $coreResult = $this->importer()->run(
            new ImportRequest('catalog_product'),
            $this->csvSource(self::PRODUCT_COLUMNS, $this->productRows($core))
        );
        $ourResult = $this->importer()->run(
            new ImportRequest('catalog_product'),
            $this->mappedSource(self::PRODUCT_COLUMNS, $this->productRows($ours))
        );

        self::assertTrue($coreResult->imported, $this->describe($coreResult));
        self::assertTrue($ourResult->imported, $this->describe($ourResult));
        self::assertSame(
            [$coreResult->rows, $coreResult->entities, $coreResult->created, $coreResult->updated],
            [$ourResult->rows, $ourResult->entities, $ourResult->created, $ourResult->updated]
        );
        self::assertSame(6, $ourResult->rows);
        for ($i = 1; $i <= 5; $i++) {
            self::assertEquals(
                $this->productSnapshot($core . '-' . $i, $core),
                $this->productSnapshot($ours . '-' . $i, $ours),
                'product ' . $i
            );
        }
        $snapshot = $this->productSnapshot($ours . '-1', $ours);
        self::assertSame('PREFIX Trailschuh 1', $snapshot['store_view_name']);
        self::assertSame('PREFIX Trail shoe 1', $snapshot['name']);
        self::assertEquals(7.0, $snapshot['qty']);
    }

    public function testUpdatesThroughMappedSourceEqualCoreCsvUpdates(): void
    {
        $core = $this->prefix('coreupd');
        $ours = $this->prefix('oiupd');
        foreach ([$core, $ours] as $prefix) {
            $this->importer()->run(
                new ImportRequest('catalog_product'),
                $this->mappedSource(self::PRODUCT_COLUMNS, $this->productRows($prefix))
            );
        }
        // Every row fills every column: core refuses an empty cell in a required attribute's column (name, price)
        // even for an existing product (M0 spike finding, same through the CSV source).
        $updates = static fn (string $prefix): array => [
            ['sku' => $prefix . '-1', 'name' => $prefix . ' Trail shoe 1', 'price' => '99.00', 'special_price' => '',
                'qty' => '0', 'is_in_stock' => '0'],
            ['sku' => $prefix . '-2', 'name' => $prefix . ' Renamed', 'price' => '29.99',
                'special_price' => '__EMPTY__VALUE__', 'qty' => '14', 'is_in_stock' => '1'],
        ];
        $columns = ['sku', 'name', 'price', 'special_price', 'qty', 'is_in_stock'];

        $coreResult = $this->importer()->run(new ImportRequest('catalog_product'), $this->csvSource($columns, $updates($core)));
        $ourResult = $this->importer()->run(new ImportRequest('catalog_product'), $this->mappedSource($columns, $updates($ours)));

        self::assertTrue($coreResult->imported, $this->describe($coreResult));
        self::assertTrue($ourResult->imported, $this->describe($ourResult));
        self::assertSame([$coreResult->created, $coreResult->updated], [$ourResult->created, $ourResult->updated]);
        for ($i = 1; $i <= 2; $i++) {
            self::assertEquals(
                $this->productSnapshot($core . '-' . $i, $core),
                $this->productSnapshot($ours . '-' . $i, $ours),
                'product ' . $i
            );
        }
        $second = $this->productSnapshot($ours . '-2', $ours);
        self::assertNull($second['special_price']);
        self::assertSame('PREFIX Renamed', $second['name']);
        self::assertSame('Trail shoe 2', $second['meta_title']);
    }

    public function testCustomersImportedThroughMappedSourceEqualCoreCsvImport(): void
    {
        $core = $this->prefix('corecust');
        $ours = $this->prefix('oicust');

        $coreResult = $this->importer()->run(
            new ImportRequest('customer'),
            $this->csvSource(self::CUSTOMER_COLUMNS, $this->customerRows($core))
        );
        $ourResult = $this->importer()->run(
            new ImportRequest('customer'),
            $this->mappedSource(self::CUSTOMER_COLUMNS, $this->customerRows($ours))
        );

        self::assertTrue($coreResult->imported, $this->describe($coreResult));
        self::assertTrue($ourResult->imported, $this->describe($ourResult));
        self::assertSame([$coreResult->created, $coreResult->updated], [$ourResult->created, $ourResult->updated]);
        for ($i = 1; $i <= 4; $i++) {
            self::assertEquals(
                $this->customerSnapshot(strtolower($core) . '.customer' . $i . '@example.com', $core),
                $this->customerSnapshot(strtolower($ours) . '.customer' . $i . '@example.com', $ours),
                'customer ' . $i
            );
        }
    }

    public function testDryRunWritesNothingAndLeavesNoBunchesBehind(): void
    {
        $prefix = $this->prefix('dry');
        $bunchesBefore = $this->importDataRows();

        $result = $this->importer()->run(
            new ImportRequest('catalog_product', dryRun: true),
            $this->mappedSource(self::PRODUCT_COLUMNS, $this->productRows($prefix))
        );

        self::assertTrue($result->valid, $this->describe($result));
        self::assertFalse($result->imported);
        self::assertSame(6, $result->rows);
        self::assertNull($result->historyId);
        self::assertSame($bunchesBefore, $this->importDataRows());
        $this->expectException(NoSuchEntityException::class);
        Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class)->get($prefix . '-1', false, 0, true);
    }

    public function testInvalidRowsAreReportedWithTheirZeroBasedPositionAndValidRowsAreImported(): void
    {
        $prefix = $this->prefix('err');
        $rows = $this->productRows($prefix);
        $rows[1]['price'] = 'twelve';
        $rows[3]['product_type'] = 'spaceship';

        $result = $this->importer()->run(
            new ImportRequest('catalog_product', skipErrors: true, allowedErrors: 10),
            $this->mappedSource(self::PRODUCT_COLUMNS, $rows)
        );

        self::assertTrue($result->imported, $this->describe($result));
        self::assertSame(2, $result->invalidRows, $this->describe($result));
        self::assertNotSame([], $result->errorsForRow(1), $this->describe($result));
        self::assertNotSame([], $result->errorsForRow(3), $this->describe($result));
        self::assertSame([], $result->errorsForRow(0));
        $repository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        self::assertSame($prefix . '-1', $repository->get($prefix . '-1', false, 0, true)->getSku());
        try {
            $repository->get($prefix . '-2', false, 0, true);
            self::fail('The row with an invalid price must not be imported.');
        } catch (NoSuchEntityException) {
            self::addToAssertionCount(1);
        }
    }

    public function testStopOnErrorRefusesTheWholeImport(): void
    {
        $prefix = $this->prefix('stop');
        $rows = $this->productRows($prefix);
        $rows[2]['price'] = 'n/a';
        $bunchesBefore = $this->importDataRows();

        $result = $this->importer()->run(
            new ImportRequest('catalog_product'),
            $this->mappedSource(self::PRODUCT_COLUMNS, $rows)
        );

        self::assertFalse($result->imported, $this->describe($result));
        self::assertNotSame([], $result->errorsForRow(2), $this->describe($result));
        self::assertSame($bunchesBefore, $this->importDataRows());
    }

    public function testLiveRunIsRecordedInCoreImportHistory(): void
    {
        $prefix = $this->prefix('hist');
        $label = 'openimport-' . strtolower($prefix) . '.csv';

        $result = $this->importer()->run(
            new ImportRequest('catalog_product', historyLabel: $label),
            $this->mappedSource(self::PRODUCT_COLUMNS, $this->productRows($prefix))
        );

        self::assertNotNull($result->historyId);
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $row = $resource->getConnection()->fetchRow(
            'SELECT imported_file, execution_time, summary FROM ' . $resource->getTableName('import_history')
            . ' WHERE history_id = ?',
            [$result->historyId]
        );
        self::assertIsArray($row);
        self::assertSame($label, $row['imported_file']);
        self::assertNotSame('In Progress', $row['execution_time']);
        self::assertNotEmpty($row['summary'], (string) json_encode($row));
    }

    public function testReplaceAndDeleteBehaveLikeCore(): void
    {
        $core = $this->prefix('corerep');
        $ours = $this->prefix('oirep');
        foreach ([$core, $ours] as $prefix) {
            $this->importer()->run(
                new ImportRequest('catalog_product'),
                $this->mappedSource(self::PRODUCT_COLUMNS, $this->productRows($prefix))
            );
        }

        $replace = fn (string $prefix): array => array_slice(
            array_map(static fn (array $row): array => ['name' => $row['name'] . ' v2'] + $row, $this->productRows($prefix)),
            0,
            2
        );
        $coreReplace = $this->importer()->run(
            new ImportRequest('catalog_product', Import::BEHAVIOR_REPLACE),
            $this->csvSource(self::PRODUCT_COLUMNS, $replace($core))
        );
        $ourReplace = $this->importer()->run(
            new ImportRequest('catalog_product', Import::BEHAVIOR_REPLACE),
            $this->mappedSource(self::PRODUCT_COLUMNS, $replace($ours))
        );
        self::assertTrue($ourReplace->imported, $this->describe($ourReplace));
        self::assertSame(
            [$coreReplace->created, $coreReplace->updated, $coreReplace->deleted],
            [$ourReplace->created, $ourReplace->updated, $ourReplace->deleted]
        );
        self::assertEquals(
            $this->productSnapshot($core . '-1', $core),
            $this->productSnapshot($ours . '-1', $ours)
        );

        $delete = static fn (string $prefix): array => [['sku' => $prefix . '-3'], ['sku' => $prefix . '-4']];
        $coreDelete = $this->importer()->run(
            new ImportRequest('catalog_product', Import::BEHAVIOR_DELETE),
            $this->csvSource(['sku'], $delete($core))
        );
        $ourDelete = $this->importer()->run(
            new ImportRequest('catalog_product', Import::BEHAVIOR_DELETE),
            $this->mappedSource(['sku'], $delete($ours))
        );
        self::assertTrue($ourDelete->imported, $this->describe($ourDelete));
        self::assertSame($coreDelete->deleted, $ourDelete->deleted);
        self::assertSame(2, $ourDelete->deleted);
        $repository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        foreach ([$core, $ours] as $prefix) {
            foreach ([3, 4] as $i) {
                try {
                    $repository->get($prefix . '-' . $i, false, 0, true);
                    self::fail($prefix . '-' . $i . ' must be deleted.');
                } catch (NoSuchEntityException) {
                    self::addToAssertionCount(1);
                }
            }
        }
    }
}
