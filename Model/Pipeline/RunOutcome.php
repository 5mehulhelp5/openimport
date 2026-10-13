<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Pipeline;

/**
 * How a run ended: status, counts and a plain-words summary.
 */
class RunOutcome
{
    /**
     * @param string $status RunInterface::STATUS_*
     * @param array<string, int> $counts RunInterface::COUNTS => value
     * @param string $message
     * @param int|null $historyId core import history row of a live run
     */
    public function __construct(
        public readonly string $status,
        public readonly array $counts,
        public readonly string $message,
        public readonly ?int $historyId = null
    ) {
    }
}
