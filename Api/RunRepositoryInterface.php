<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Api;

use Iranimij\OpenImport\Api\Data\RunInterface;
use Iranimij\OpenImport\Api\Data\RunSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface RunRepositoryInterface
{
    /**
     * @param RunInterface $run
     * @return RunInterface
     * @throws CouldNotSaveException
     */
    public function save(RunInterface $run): RunInterface;

    /**
     * @param int $runId
     * @return RunInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $runId): RunInterface;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return RunSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): RunSearchResultsInterface;
}
