<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

/**
 * All transforms by name. Compiles a field's transform list into one function.
 *
 * A transform definition is a name ("trim") or ["name" => "replace", "args" => ["a", "b"]], as stored in a mapping
 * row's JSON.
 */
class TransformPool
{
    /**
     * @var array<string, TransformProviderInterface>
     */
    private array $byName = [];

    /**
     * @param TransformProviderInterface[] $providers extra transforms; a provider may replace a built-in name
     */
    public function __construct(array $providers = [])
    {
        $builtIn = [new TextTransforms(), new NumberTransforms(), new DateTransform(), new LookupTransforms()];
        foreach (array_merge($builtIn, array_values($providers)) as $provider) {
            foreach ($provider->names() as $name) {
                $this->byName[strtolower($name)] = $provider;
            }
        }
    }

    /**
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->byName);
    }

    /**
     * @param array<int, mixed> $definitions
     * @param TransformScope $scope
     * @return \Closure fn (string $value, array $row): string
     * @throws TransformException naming the step ("Step 2 (round): …")
     */
    public function compile(array $definitions, TransformScope $scope): \Closure
    {
        $steps = [];
        foreach (array_values($definitions) as $position => $definition) {
            [$name, $arguments] = $this->definition($definition, $position + 1);
            $provider = $this->byName[$name] ?? null;
            if ($provider === null) {
                throw new TransformException(sprintf(
                    'Step %d: unknown transform "%s". Transforms: %s.',
                    $position + 1,
                    $name,
                    implode(', ', $this->names())
                ));
            }
            try {
                $steps[] = $provider->compile($name, new Arguments($name, $arguments), $scope);
            } catch (TransformException $exception) {
                throw new TransformException(sprintf('Step %d: %s', $position + 1, $exception->getMessage()));
            }
        }

        return static function (string $value, array $row) use ($steps): string {
            foreach ($steps as $step) {
                $value = $step($value, $row);
            }
            return $value;
        };
    }

    /**
     * @param mixed $definition
     * @param int $step
     * @return array{0: string, 1: array<int|string, mixed>}
     */
    private function definition(mixed $definition, int $step): array
    {
        if (is_string($definition)) {
            return [strtolower(trim($definition)), []];
        }
        if (is_array($definition) && isset($definition['name']) && is_string($definition['name'])) {
            $arguments = $definition['args'] ?? [];
            if (!is_array($arguments)) {
                $arguments = [$arguments];
            }
            return [strtolower(trim($definition['name'])), $arguments];
        }
        throw new TransformException(sprintf('Step %d: a transform needs a name.', $step));
    }
}
