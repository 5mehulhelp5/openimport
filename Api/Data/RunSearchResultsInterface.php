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
interface RunSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Iranimij\OpenImport\Api\Data\RunInterface[]
     */
    public function getItems();

    /**
     * @param \Iranimij\OpenImport\Api\Data\RunInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
