<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Execution;

use Iranimij\OpenImport\Api\Data\ProfileInterface;
use Iranimij\OpenImport\Model\Pipeline\PipelineException;

/**
 * A place a profile reads its file from (server folder, URL, SFTP, FTP).
 *
 * @api
 */
interface SourceInterface
{
    /**
     * Downloads or locates the file to import.
     *
     * @param ProfileInterface $profile
     * @param string $folder the run folder; downloads go there
     * @return array{0: string, 1: string} absolute path and original file name
     * @throws PipelineException with a plain-words reason
     */
    public function fetch(ProfileInterface $profile, string $folder): array;
}
