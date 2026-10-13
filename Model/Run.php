<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model;

use Iranimij\OpenImport\Api\Data\RunInterface;
use Iranimij\OpenImport\Model\ResourceModel\Run as RunResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Run model. Counts live in columns count_<name>.
 */
class Run extends AbstractModel implements RunInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'openimport_run';

    private ?JsonField $json = null;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(RunResource::class);
    }

    private function json(): JsonField
    {
        return $this->json ??= new JsonField();
    }

    /**
     * @inheritDoc
     */
    public function getRunId(): ?int
    {
        $id = $this->getData(self::RUN_ID);

        return $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * @inheritDoc
     */
    public function getProfileId(): ?int
    {
        $id = $this->getData(self::PROFILE_ID);

        return $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * @inheritDoc
     */
    public function setProfileId(?int $profileId): RunInterface
    {
        return $this->setData(self::PROFILE_ID, $profileId);
    }

    /**
     * @inheritDoc
     */
    public function getProfileName(): string
    {
        return (string) $this->getData(self::PROFILE_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setProfileName(string $name): RunInterface
    {
        return $this->setData(self::PROFILE_NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getDirection(): string
    {
        return (string) ($this->getData(self::DIRECTION) ?: 'import');
    }

    /**
     * @inheritDoc
     */
    public function setDirection(string $direction): RunInterface
    {
        return $this->setData(self::DIRECTION, $direction);
    }

    /**
     * @inheritDoc
     */
    public function getEntity(): string
    {
        return (string) $this->getData(self::ENTITY);
    }

    /**
     * @inheritDoc
     */
    public function setEntity(string $entity): RunInterface
    {
        return $this->setData(self::ENTITY, $entity);
    }

    /**
     * @inheritDoc
     */
    public function getTrigger(): string
    {
        return (string) ($this->getData(self::TRIGGER) ?: self::TRIGGER_MANUAL);
    }

    /**
     * @inheritDoc
     */
    public function setTrigger(string $trigger): RunInterface
    {
        return $this->setData(self::TRIGGER, $trigger);
    }

    /**
     * @inheritDoc
     */
    public function getMode(): string
    {
        return (string) ($this->getData(self::MODE) ?: self::MODE_DRY_RUN);
    }

    /**
     * @inheritDoc
     */
    public function setMode(string $mode): RunInterface
    {
        return $this->setData(self::MODE, $mode);
    }

    /**
     * @inheritDoc
     */
    public function isDryRun(): bool
    {
        return $this->getMode() !== self::MODE_LIVE;
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string) ($this->getData(self::STATUS) ?: self::STATUS_QUEUED);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): RunInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getCount(string $name): int
    {
        return (int) $this->getData('count_' . $name);
    }

    /**
     * @inheritDoc
     */
    public function setCount(string $name, int $value): RunInterface
    {
        if (!in_array($name, self::COUNTS, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown run count "%s".', $name));
        }

        return $this->setData('count_' . $name, max(0, $value));
    }

    /**
     * @inheritDoc
     */
    public function getCounts(): array
    {
        $counts = [];
        foreach (self::COUNTS as $name) {
            $counts[$name] = $this->getCount($name);
        }

        return $counts;
    }

    /**
     * @inheritDoc
     */
    public function getSourceFile(): ?string
    {
        $value = $this->getData(self::SOURCE_FILE);

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setSourceFile(?string $file): RunInterface
    {
        return $this->setData(self::SOURCE_FILE, $file);
    }

    /**
     * @inheritDoc
     */
    public function getOutputFile(): ?string
    {
        $value = $this->getData(self::OUTPUT_FILE);

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setOutputFile(?string $file): RunInterface
    {
        return $this->setData(self::OUTPUT_FILE, $file);
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->json()->decode($this->getData(self::OPTIONS));
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options): RunInterface
    {
        return $this->setData(self::OPTIONS, $this->json()->encode($options));
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        return (string) $this->getData(self::MESSAGE);
    }

    /**
     * @inheritDoc
     */
    public function setMessage(string $message): RunInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * @inheritDoc
     */
    public function getRequestedBy(): ?string
    {
        $value = $this->getData(self::REQUESTED_BY);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setRequestedBy(?string $requestedBy): RunInterface
    {
        return $this->setData(self::REQUESTED_BY, $requestedBy);
    }

    /**
     * @inheritDoc
     */
    public function getParentRunId(): ?int
    {
        $id = $this->getData(self::PARENT_RUN_ID);

        return $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * @inheritDoc
     */
    public function setParentRunId(?int $runId): RunInterface
    {
        return $this->setData(self::PARENT_RUN_ID, $runId);
    }

    /**
     * @inheritDoc
     */
    public function getProgressRows(): int
    {
        return (int) $this->getData(self::PROGRESS_ROWS);
    }

    /**
     * @inheritDoc
     */
    public function getPeakMemoryMb(): int
    {
        return (int) $this->getData(self::PEAK_MEMORY_MB);
    }

    /**
     * @inheritDoc
     */
    public function setPeakMemoryMb(int $megabytes): RunInterface
    {
        return $this->setData(self::PEAK_MEMORY_MB, max(0, $megabytes));
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function getStartedAt(): ?string
    {
        $value = $this->getData(self::STARTED_AT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setStartedAt(?string $time): RunInterface
    {
        return $this->setData(self::STARTED_AT, $time);
    }

    /**
     * @inheritDoc
     */
    public function getFinishedAt(): ?string
    {
        $value = $this->getData(self::FINISHED_AT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setFinishedAt(?string $time): RunInterface
    {
        return $this->setData(self::FINISHED_AT, $time);
    }
}
