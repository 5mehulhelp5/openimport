<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Expression;

/**
 * A parsed expression, evaluated once per row.
 */
class Expression
{
    /**
     * @param string $source the expression as written
     * @param \Closure $node compiled expression: fn (array $row, string $value): mixed
     * @param string[] $columns columns the expression reads
     * @param Values $values
     */
    public function __construct(
        private readonly string $source,
        private readonly \Closure $node,
        private readonly array $columns,
        private readonly Values $values
    ) {
    }

    /**
     * The result as text.
     *
     * @param array<string, string> $row source row
     * @param string $value current value of the column being transformed
     * @return string
     * @throws EvaluationException
     */
    public function evaluate(array $row, string $value = ''): string
    {
        return $this->values->text(($this->node)($row, $value));
    }

    /**
     * The result as a condition (row filters).
     *
     * @param array<string, string> $row
     * @param string $value
     * @return bool
     * @throws EvaluationException
     */
    public function test(array $row, string $value = ''): bool
    {
        return $this->values->truthy(($this->node)($row, $value));
    }

    /**
     * @return string[]
     */
    public function columns(): array
    {
        return $this->columns;
    }

    public function source(): string
    {
        return $this->source;
    }
}
