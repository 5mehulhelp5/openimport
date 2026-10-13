<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Filter;

use Iranimij\OpenImport\Model\Expression\EvaluationException;
use Iranimij\OpenImport\Model\Expression\ExpressionException;
use Iranimij\OpenImport\Model\Expression\Parser;
use Iranimij\OpenImport\Model\Value\NumberParser;

/**
 * Compiles a profile's row filters:
 *
 *     {"mode": "include" | "exclude", "match": "all" | "any",
 *      "conditions": [{"column": "Status", "operator": "equals", "value": "active"}, {"expression": "{Qty} > 0"}]}
 *
 * Operators compare trimmed text ignoring case: equals, not_equals, contains, not_contains, starts_with, ends_with,
 * in / not_in (comma-separated list), empty, not_empty, regex; greater_than and less_than compare numbers (a cell
 * that is not a number never matches).
 */
class RowFilterCompiler
{
    public const OPERATORS = ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'in',
        'not_in', 'empty', 'not_empty', 'greater_than', 'less_than', 'regex'];

    private Parser $parser;

    private NumberParser $numbers;

    public function __construct()
    {
        $this->parser = new Parser();
        $this->numbers = new NumberParser();
    }

    /**
     * @param array<string, mixed> $definition
     * @param string[]|null $sourceColumns when given, filtered columns must exist
     * @return RowFilter
     * @throws FilterException listing every problem
     */
    public function compile(array $definition, ?array $sourceColumns = null): RowFilter
    {
        $mode = strtolower((string) ($definition['mode'] ?? 'include'));
        $match = strtolower((string) ($definition['match'] ?? 'all'));
        $problems = [];
        if (!in_array($mode, ['include', 'exclude'], true)) {
            $problems[] = 'The filter mode must be "include" or "exclude".';
        }
        if (!in_array($match, ['all', 'any'], true)) {
            $problems[] = 'The filter match must be "all" or "any".';
        }
        $conditions = [];
        $list = $definition['conditions'] ?? [];
        foreach (is_array($list) ? array_values($list) : [] as $position => $condition) {
            try {
                $conditions[] = $this->condition(is_array($condition) ? $condition : [], $sourceColumns);
            } catch (FilterException $exception) {
                $problems[] = sprintf('Filter %d: %s', $position + 1, $exception->getMessage());
            }
        }
        if ($problems !== []) {
            throw new FilterException(implode("\n", $problems));
        }

        return new RowFilter($conditions, $mode === 'include', $match === 'all');
    }

    /**
     * @param array<string, mixed> $condition
     * @param string[]|null $sourceColumns
     * @return \Closure
     */
    private function condition(array $condition, ?array $sourceColumns): \Closure
    {
        if (isset($condition['expression'])) {
            try {
                $expression = $this->parser->parse((string) $condition['expression']);
            } catch (ExpressionException $exception) {
                throw new FilterException($exception->getMessage());
            }
            return static function (array $row) use ($expression): bool {
                try {
                    return $expression->test($row);
                } catch (EvaluationException $exception) {
                    throw new FilterException(sprintf(
                        'Row filter: %s (in %s)',
                        $exception->getMessage(),
                        $expression->source()
                    ));
                }
            };
        }
        $column = isset($condition['column']) && is_scalar($condition['column']) ? (string) $condition['column'] : '';
        if ($column === '') {
            throw new FilterException('choose a column or write an expression.');
        }
        if ($sourceColumns !== null && !in_array($column, $sourceColumns, true)) {
            throw new FilterException(sprintf('the file has no column "%s".', $column));
        }
        $operator = strtolower((string) ($condition['operator'] ?? 'equals'));
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new FilterException(sprintf(
                'unknown operator "%s". Operators: %s.',
                $operator,
                implode(', ', self::OPERATORS)
            ));
        }
        $value = isset($condition['value']) && is_scalar($condition['value']) ? (string) $condition['value'] : '';
        $test = $this->test($operator, $value);

        return static fn (array $row): bool => $test(trim((string) ($row[$column] ?? '')));
    }

    private function test(string $operator, string $value): \Closure
    {
        $needle = mb_strtolower(trim($value));
        $list = array_map(static fn (string $item): string => mb_strtolower(trim($item)), explode(',', $value));
        $numbers = $this->numbers;
        $limit = in_array($operator, ['greater_than', 'less_than'], true) ? $numbers->parse($value) : null;
        if (in_array($operator, ['greater_than', 'less_than'], true) && $limit === null) {
            throw new FilterException(sprintf('"%s" is not a number.', $value));
        }
        $pattern = '~' . str_replace('~', '\~', $value) . '~u';
        if ($operator === 'regex') {
            set_error_handler(static fn (): bool => true);
            $valid = preg_match($pattern, '');
            restore_error_handler();
            if ($valid === false || $value === '') {
                throw new FilterException(sprintf('the pattern "%s" is not valid.', $value));
            }
        }

        return match ($operator) {
            'equals' => static fn (string $cell): bool => mb_strtolower($cell) === $needle,
            'not_equals' => static fn (string $cell): bool => mb_strtolower($cell) !== $needle,
            'contains' => static fn (string $cell): bool => str_contains(mb_strtolower($cell), $needle),
            'not_contains' => static fn (string $cell): bool => !str_contains(mb_strtolower($cell), $needle),
            'starts_with' => static fn (string $cell): bool => str_starts_with(mb_strtolower($cell), $needle),
            'ends_with' => static fn (string $cell): bool => str_ends_with(mb_strtolower($cell), $needle),
            'in' => static fn (string $cell): bool => in_array(mb_strtolower($cell), $list, true),
            'not_in' => static fn (string $cell): bool => !in_array(mb_strtolower($cell), $list, true),
            'empty' => static fn (string $cell): bool => $cell === '',
            'not_empty' => static fn (string $cell): bool => $cell !== '',
            'greater_than' => static function (string $cell) use ($numbers, $limit): bool {
                $number = $numbers->parse($cell);
                return $number !== null && $number > $limit;
            },
            'less_than' => static function (string $cell) use ($numbers, $limit): bool {
                $number = $numbers->parse($cell);
                return $number !== null && $number < $limit;
            },
            default => static fn (string $cell): bool => preg_match($pattern, $cell) === 1,
        };
    }
}
