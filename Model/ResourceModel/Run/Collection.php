<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\ResourceModel\Run;

use Iranimij\OpenImport\Model\ResourceModel\Run as RunResource;
use Iranimij\OpenImport\Model\Run;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Runs.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'run_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Run::class, RunResource::class);
    }
}
