<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

/**
 * A transform is set up wrongly (profile validation) or fails for one value (row error).
 */
class TransformException extends \RuntimeException
{
}
