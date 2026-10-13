<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model;

use Iranimij\OpenImport\Api\Data\RunInterface;
use Iranimij\OpenImport\Api\Data\RunSearchResultsInterface;
use Iranimij\OpenImport\Api\Data\RunSearchResultsInterfaceFactory;
use Iranimij\OpenImport\Api\RunRepositoryInterface;
use Iranimij\OpenImport\Model\ResourceModel\Run as RunResource;
use Iranimij\OpenImport\Model\ResourceModel\Run\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Runs.
 */
class RunRepository implements RunRepositoryInterface
{
    /**
     * @param RunFactory $runFactory
     * @param RunResource $resource
     * @param CollectionFactory $collectionFactory
     * @param RunSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly RunFactory $runFactory,
        private readonly RunResource $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly RunSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(RunInterface $run): RunInterface
    {
        if (!$run instanceof Run) {
            throw new CouldNotSaveException(__('Unsupported run object.'));
        }
        try {
            $this->resource->save($run);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('The run could not be saved: %1', $exception->getMessage()), $exception);
        }

        return $run;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $runId): RunInterface
    {
        $run = $this->runFactory->create();
        $this->resource->load($run, $runId);
        if (!$run->getId()) {
            throw new NoSuchEntityException(__('Run %1 does not exist.', $runId));
        }

        return $run;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $criteria): RunSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($criteria);
        $results->setItems(array_values($collection->getItems()));
        $results->setTotalCount($collection->getSize());

        return $results;
    }
}
