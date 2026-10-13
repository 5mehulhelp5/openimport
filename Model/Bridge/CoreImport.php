<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Bridge;

use Magento\ImportExport\Model\Import;

/**
 * Magento's import model with one addition: the ids of the bunches this import validated.
 *
 * Core keeps validated rows in importexport_importdata and imports exactly the bunches whose ids the entity adapter
 * collected during validation. 2.4.9 exposes them (getValidatedIds()), 2.4.7 and 2.4.8 do not. OpenImport needs them
 * to remove the bunches of a dry run (nothing may stay behind that a later core import could pick up) and to clean
 * up after a live run. Instantiated only by OpenImport; it is not a preference for core's class.
 */
class CoreImport extends Import
{
    /**
     * Ids of the importexport_importdata rows saved by this import's validation.
     *
     * @return int[]
     */
    public function getBunchIds(): array
    {
        $ids = $this->_getEntityAdapter()->getIds();

        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }
}
