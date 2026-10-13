<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Execution;

use Iranimij\OpenImport\Api\Data\ProfileInterface;
use Iranimij\OpenImport\Api\Data\RunInterface;
use Iranimij\OpenImport\Api\Data\RunInterfaceFactory;
use Iranimij\OpenImport\Api\RunRepositoryInterface;

/**
 * Adds runs to the queue (admin buttons, schedules, CLI). Runs never execute in a web request.
 */
class RunQueue
{
    /**
     * @param RunInterfaceFactory $runFactory
     * @param RunRepositoryInterface $runRepository
     */
    public function __construct(
        private readonly RunInterfaceFactory $runFactory,
        private readonly RunRepositoryInterface $runRepository
    ) {
    }

    /**
     * @param ProfileInterface $profile
     * @param string $mode RunInterface::MODE_*
     * @param string $trigger RunInterface::TRIGGER_*
     * @param array<string, mixed> $options file, original_name, full, confirmed …
     * @param string|null $requestedBy
     * @param int|null $parentRunId the dry run a live run confirms
     * @return RunInterface
     */
    public function queue(
        ProfileInterface $profile,
        string $mode,
        string $trigger,
        array $options = [],
        ?string $requestedBy = null,
        ?int $parentRunId = null
    ): RunInterface {
        $run = $this->runFactory->create();
        $run->setProfileId($profile->getProfileId())
            ->setProfileName($profile->getName())
            ->setDirection($profile->getDirection())
            ->setEntity($profile->getEntity())
            ->setMode($mode === RunInterface::MODE_LIVE ? RunInterface::MODE_LIVE : RunInterface::MODE_DRY_RUN)
            ->setTrigger($trigger)
            ->setStatus(RunInterface::STATUS_QUEUED)
            ->setOptions($options)
            ->setRequestedBy($requestedBy)
            ->setParentRunId($parentRunId);

        return $this->runRepository->save($run);
    }
}
