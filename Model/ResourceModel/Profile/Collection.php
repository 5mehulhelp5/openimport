<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\ResourceModel\Profile;

use Iranimij\OpenImport\Model\Profile;
use Iranimij\OpenImport\Model\ResourceModel\Profile as ProfileResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Profiles (without mapping rows; load a profile through the repository for those).
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'profile_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Profile::class, ProfileResource::class);
    }
}
