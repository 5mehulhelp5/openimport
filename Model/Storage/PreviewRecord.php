<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Storage;

/**
 * One record of a dry-run preview: what would happen to it, field by field.
 */
class PreviewRecord
{
    public const NEW = 'new';
    public const CHANGED = 'changed';
    public const UNCHANGED = 'unchanged';
    public const DELETED = 'deleted';

    /**
     * @param string $identifier
     * @param string $changeType new, changed, unchanged or deleted
     * @param array<string, array{0: string|null, 1: string|null}> $diff field => [before, after]
     * @param int $sourceRow
     */
    public function __construct(
        public readonly string $identifier,
        public readonly string $changeType,
        public readonly array $diff = [],
        public readonly int $sourceRow = 0
    ) {
    }
}
