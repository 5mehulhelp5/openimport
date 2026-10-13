<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface ProfileSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Iranimij\OpenImport\Api\Data\ProfileInterface[]
     */
    public function getItems();

    /**
     * @param \Iranimij\OpenImport\Api\Data\ProfileInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
