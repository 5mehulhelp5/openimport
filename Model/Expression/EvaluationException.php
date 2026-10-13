<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Expression;

/**
 * An expression failed for one row (becomes a row error with the column and the expression).
 */
class EvaluationException extends \RuntimeException
{
}
