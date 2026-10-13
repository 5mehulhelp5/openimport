<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model;

use Iranimij\OpenImport\Api\Data\RunSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Search results of RunRepository::getList().
 */
class RunSearchResults extends SearchResults implements RunSearchResultsInterface
{
}
