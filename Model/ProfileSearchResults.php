<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model;

use Iranimij\OpenImport\Api\Data\ProfileSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Search results of ProfileRepository::getList().
 */
class ProfileSearchResults extends SearchResults implements ProfileSearchResultsInterface
{
}
