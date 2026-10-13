<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

use Iranimij\OpenImport\Model\Expression\EvaluationException;
use Iranimij\OpenImport\Model\Expression\ExpressionException;
use Iranimij\OpenImport\Model\Expression\Parser;

/**
 * map: the field's value map. expr(expression): a safe expression over the source row (`value` is the current value).
 */
class LookupTransforms implements TransformProviderInterface
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * @inheritDoc
     */
    public function names(): array
    {
        return ['map', 'expr'];
    }

    /**
     * @inheritDoc
     */
    public function compile(string $name, Arguments $arguments, TransformScope $scope): \Closure
    {
        if ($name === 'map') {
            $arguments->expectCount(0, 0);
            $map = $scope->valueMap;
            if ($map === null || $map->count() === 0) {
                throw $arguments->error(sprintf('the field "%s" has no value map', $scope->field));
            }
            return static fn (string $value): string => $map->apply($value);
        }
        $arguments->expectCount(1, 1);
        try {
            $expression = $this->parser->parse($arguments->string(0));
        } catch (ExpressionException $exception) {
            throw $arguments->error($exception->getMessage());
        }

        return static function (string $value, array $row) use ($expression): string {
            try {
                return $expression->evaluate($row, $value);
            } catch (EvaluationException $exception) {
                throw new TransformException(sprintf(
                    'expr: %s (in %s)',
                    $exception->getMessage(),
                    $expression->source()
                ));
            }
        };
    }
}
