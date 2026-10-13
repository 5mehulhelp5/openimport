<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

/**
 * Provides one or more transforms by name. Add your own through di.xml (TransformPool argument "providers").
 *
 * compile() checks the arguments once per run and returns the function applied to every value:
 * fn (string $value, array $row): string, where $row is the source row. It throws TransformException for wrong
 * arguments (at compile time) and for values it cannot handle (at run time, becoming a row error).
 *
 * @api
 */
interface TransformProviderInterface
{
    /**
     * @return string[] transform names (lower case)
     */
    public function names(): array;

    /**
     * @param string $name
     * @param Arguments $arguments
     * @param TransformScope $scope
     * @return \Closure
     * @throws TransformException
     */
    public function compile(string $name, Arguments $arguments, TransformScope $scope): \Closure;
}
