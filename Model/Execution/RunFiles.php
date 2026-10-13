<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Execution;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;

/**
 * The run folder var/openimport/runs/<run id>/: the source copy, the prepared rows, export files and error reports.
 */
class RunFiles
{
    public const BASE = 'openimport/runs';

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Absolute path of the run folder, created when missing.
     *
     * @param int $runId
     * @return string
     * @throws FileSystemException
     */
    public function folder(int $runId): string
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $relative = self::BASE . '/' . $runId;
        $directory->create($relative);

        return rtrim($directory->getAbsolutePath($relative), '/');
    }

    /**
     * Copies a file into the run folder as source.<ext> and returns the copy's absolute path.
     *
     * @param int $runId
     * @param string $file absolute path
     * @param string $originalName
     * @return string
     * @throws FileSystemException
     */
    public function stageSource(int $runId, string $file, string $originalName = ''): string
    {
        $extension = strtolower((new \SplFileInfo($originalName !== '' ? $originalName : $file))->getExtension());
        $extension = preg_match('/^[a-z0-9]{1,5}$/', $extension) === 1 ? $extension : 'dat';
        $target = $this->folder($runId) . '/source.' . $extension;
        $driver = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getDriver();
        $driver->copy($file, $target);

        return $target;
    }

    /**
     * The path stored on the run row (relative to var/).
     *
     * @param string $absolute
     * @return string
     */
    public function relative(string $absolute): string
    {
        $var = rtrim($this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath(), '/') . '/';

        return str_starts_with($absolute, $var) ? substr($absolute, strlen($var)) : $absolute;
    }

    /**
     * Absolute path of a path stored on a run row.
     *
     * @param string $relative
     * @return string
     */
    public function absolute(string $relative): string
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath($relative);
    }
}
