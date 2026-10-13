<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Bridge;

use Magento\ImportExport\Model\HistoryFactory;
use Magento\ImportExport\Model\Import\AbstractSource;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
use Magento\ImportExport\Model\ResourceModel\Import\Data as ImportData;

/**
 * Hands a prepared source to Magento's own import engine: the same validate + import steps the admin import page
 * runs, so the data written is exactly what core would write for an equivalent CSV file.
 *
 * - Dry run: validation only. The bunches validation saved are removed again, so nothing stays behind that a later
 *   import could pick up.
 * - Live run: a row in core's import history (so the run shows up under System › Data Transfer › Import History and
 *   core's history updates land on it instead of on someone else's import), import, invalidate the affected
 *   indexers like the admin import does, then remove this run's bunches.
 */
class CoreImporter
{
    /**
     * @param CoreImportFactory $importFactory
     * @param HistoryFactory $historyFactory
     * @param ImportData $importData
     */
    public function __construct(
        private readonly CoreImportFactory $importFactory,
        private readonly HistoryFactory $historyFactory,
        private readonly ImportData $importData
    ) {
    }

    /**
     * Validates the source and, unless this is a dry run or validation failed, imports it.
     *
     * @param ImportRequest $request
     * @param AbstractSource $source
     * @return ImportResult
     */
    public function run(ImportRequest $request, AbstractSource $source): ImportResult
    {
        $started = microtime(true);
        /** @var CoreImport $import */
        $import = $this->importFactory->create();
        $import->setData($request->toImportData());

        $bunchIds = [];
        try {
            $valid = (bool) $import->validateSource($source);
            $bunchIds = $import->getBunchIds();
            $rows = (int) $import->getProcessedRowsCount();
            $entities = (int) $import->getProcessedEntitiesCount();
            if ($request->dryRun || !$valid || !$import->isImportAllowed()) {
                return $this->result($import, $valid, false, $rows, $entities, null, $started);
            }

            $historyId = $this->openHistory($request->historyLabel);
            $import->setData('_import_history_id', $historyId);
            $imported = (bool) $import->importSource();
            $import->invalidateIndex();

            return $this->result($import, $valid, $imported, $rows, $entities, $historyId, $started);
        } finally {
            $this->removeBunches($bunchIds);
        }
    }

    /**
     * Builds the result from the import's counters and error aggregator.
     *
     * @param CoreImport $import
     * @param bool $valid
     * @param bool $imported
     * @param int $rows
     * @param int $entities
     * @param int|null $historyId
     * @param float $started
     * @return ImportResult
     */
    private function result(
        CoreImport $import,
        bool $valid,
        bool $imported,
        int $rows,
        int $entities,
        ?int $historyId,
        float $started
    ): ImportResult {
        $aggregator = $import->getErrorAggregator();
        $errors = [];
        foreach ($aggregator->getAllErrors() as $error) {
            $errors[] = $this->rowError($error);
        }

        return new ImportResult(
            $valid,
            $imported,
            $rows,
            $entities,
            (int) $aggregator->getInvalidRowsCount(),
            $imported ? (int) $import->getCreatedItemsCount() : 0,
            $imported ? (int) $import->getUpdatedItemsCount() : 0,
            $imported ? (int) $import->getDeletedItemsCount() : 0,
            $errors,
            $historyId,
            microtime(true) - $started
        );
    }

    /**
     * Converts one of core's processing errors.
     *
     * @param ProcessingError $error
     * @return RowError
     */
    private function rowError(ProcessingError $error): RowError
    {
        $row = $error->getRowNumber();
        $column = $error->getColumnName();
        $message = (string) $error->getErrorMessage();

        return new RowError(
            $row === null || $row === '' ? null : (int) $row,
            (string) $error->getErrorCode(),
            $message !== '' ? $message : (string) $error->getErrorCode(),
            $column === null || $column === '' ? null : (string) $column,
            $error->getErrorLevel() === ProcessingError::ERROR_LEVEL_CRITICAL
        );
    }

    /**
     * Adds the core import history row this run's history updates go to.
     *
     * @param string $label
     * @return int
     */
    private function openHistory(string $label): int
    {
        $history = $this->historyFactory->create();
        $history->addReport($label);

        return (int) $history->getId();
    }

    /**
     * Removes this import's validated bunches from importexport_importdata.
     *
     * @param int[] $ids
     * @return void
     */
    private function removeBunches(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $this->importData->getConnection()->delete($this->importData->getMainTable(), ['id IN (?)' => $ids]);
    }
}
