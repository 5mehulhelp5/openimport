<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Pipeline;

/**
 * A run cannot start or continue (bad profile, unreadable file …); the message is shown on the run page.
 */
class PipelineException extends \RuntimeException
{
}
