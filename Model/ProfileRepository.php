<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model;

use Iranimij\OpenImport\Api\Data\ProfileInterface;
use Iranimij\OpenImport\Api\Data\ProfileSearchResultsInterface;
use Iranimij\OpenImport\Api\Data\ProfileSearchResultsInterfaceFactory;
use Iranimij\OpenImport\Api\ProfileRepositoryInterface;
use Iranimij\OpenImport\Model\Profile\ProfileValidator;
use Iranimij\OpenImport\Model\ResourceModel\Profile as ProfileResource;
use Iranimij\OpenImport\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Profiles with their mapping and value maps.
 */
class ProfileRepository implements ProfileRepositoryInterface
{
    /**
     * @param ProfileFactory $profileFactory
     * @param ProfileResource $resource
     * @param CollectionFactory $collectionFactory
     * @param ProfileSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ProfileValidator $validator
     */
    public function __construct(
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileResource $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProfileSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly ProfileValidator $validator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(ProfileInterface $profile): ProfileInterface
    {
        if (!$profile instanceof Profile) {
            throw new CouldNotSaveException(__('Unsupported profile object.'));
        }
        $problems = $this->validator->validate($profile);
        $sameName = $this->resource->getIdByName($profile->getName());
        if ($sameName !== null && $sameName !== $profile->getProfileId()) {
            $problems[] = sprintf('A profile named "%s" already exists.', trim($profile->getName()));
        }
        if ($problems !== []) {
            throw new CouldNotSaveException(__(implode("\n", $problems)));
        }
        $profile->setName(trim($profile->getName()));
        try {
            $this->resource->save($profile);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('The profile could not be saved: %1', $exception->getMessage()), $exception);
        }

        return $this->getById((int) $profile->getId());
    }

    /**
     * @inheritDoc
     */
    public function getById(int $profileId): ProfileInterface
    {
        $profile = $this->profileFactory->create();
        $this->resource->load($profile, $profileId);
        if (!$profile->getId()) {
            throw new NoSuchEntityException(__('Profile %1 does not exist.', $profileId));
        }

        return $profile;
    }

    /**
     * @inheritDoc
     */
    public function getByName(string $name): ProfileInterface
    {
        $profileId = $this->resource->getIdByName($name);
        if ($profileId === null) {
            throw new NoSuchEntityException(__('There is no profile named "%1".', $name));
        }

        return $this->getById($profileId);
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $criteria): ProfileSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($criteria);
        $items = [];
        foreach ($collection->getItems() as $item) {
            if ($item instanceof ProfileInterface) {
                $items[] = $item;
            }
        }
        $results->setItems($items);
        $results->setTotalCount($collection->getSize());

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function delete(ProfileInterface $profile): bool
    {
        if (!$profile instanceof Profile) {
            throw new CouldNotDeleteException(__('Unsupported profile object.'));
        }
        try {
            $this->resource->delete($profile);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('The profile could not be deleted: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $profileId): bool
    {
        return $this->delete($this->getById($profileId));
    }
}
