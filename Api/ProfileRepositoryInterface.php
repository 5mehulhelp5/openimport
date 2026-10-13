<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Api;

use Iranimij\OpenImport\Api\Data\ProfileInterface;
use Iranimij\OpenImport\Api\Data\ProfileSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface ProfileRepositoryInterface
{
    /**
     * @param ProfileInterface $profile
     * @return ProfileInterface
     * @throws CouldNotSaveException with every validation problem
     */
    public function save(ProfileInterface $profile): ProfileInterface;

    /**
     * @param int $profileId
     * @return ProfileInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $profileId): ProfileInterface;

    /**
     * @param string $name case-insensitive
     * @return ProfileInterface
     * @throws NoSuchEntityException
     */
    public function getByName(string $name): ProfileInterface;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return ProfileSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): ProfileSearchResultsInterface;

    /**
     * Runs are kept with the profile name; mapping, value maps and delta hashes are removed.
     *
     * @param ProfileInterface $profile
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ProfileInterface $profile): bool;

    /**
     * @param int $profileId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $profileId): bool;
}
