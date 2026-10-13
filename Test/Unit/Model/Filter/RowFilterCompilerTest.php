<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Filter;

use Iranimij\OpenImport\Model\Filter\FilterException;
use Iranimij\OpenImport\Model\Filter\RowFilterCompiler;
use PHPUnit\Framework\TestCase;

class RowFilterCompilerTest extends TestCase
{
    private const ROW = ['Status' => ' Active ', 'Qty' => '1,5', 'Name' => 'Red Shirt', 'Empty' => '', 'Brand' => 'acme'];

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function conditions(): array
    {
        return [
            'equals ignores case and spaces' => ['Status', 'equals', true],
            'not_equals' => ['Status', 'not_equals', false],
            'contains' => ['Name', 'contains', true],
            'not_contains' => ['Name', 'not_contains', false],
            'starts_with' => ['Name', 'starts_with', true],
            'ends_with' => ['Name', 'ends_with', false],
            'in' => ['Brand', 'in', true],
            'not_in' => ['Brand', 'not_in', false],
            'empty' => ['Empty', 'empty', true],
            'not_empty' => ['Empty', 'not_empty', false],
            'missing column is empty' => ['Missing', 'empty', true],
            'greater_than' => ['Qty', 'greater_than', true],
            'less_than' => ['Qty', 'less_than', false],
            'not a number never matches' => ['Name', 'greater_than', false],
            'regex' => ['Name', 'regex', true],
        ];
    }

    /**
     * @dataProvider conditions
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('conditions')]
    public function testOperators(string $column, string $operator, bool $expected): void
    {
        $values = [
            'equals' => 'ACTIVE', 'not_equals' => 'active', 'contains' => 'shirt', 'not_contains' => 'SHIRT',
            'starts_with' => 'red', 'ends_with' => 'red', 'in' => 'Foo, ACME ,bar', 'not_in' => 'acme',
            'empty' => '', 'not_empty' => '', 'greater_than' => '1', 'less_than' => '1', 'regex' => '^Red\s',
        ];
        $filter = (new RowFilterCompiler())->compile([
            'conditions' => [['column' => $column, 'operator' => $operator, 'value' => $values[$operator]]],
        ]);

        self::assertSame($expected, $filter->keep(self::ROW));
    }

    public function testIncludeExcludeAllAny(): void
    {
        $compiler = new RowFilterCompiler();
        $conditions = [
            ['column' => 'Status', 'operator' => 'equals', 'value' => 'active'],
            ['column' => 'Brand', 'operator' => 'equals', 'value' => 'other'],
        ];

        self::assertFalse($compiler->compile(['mode' => 'include', 'match' => 'all', 'conditions' => $conditions])->keep(self::ROW));
        self::assertTrue($compiler->compile(['mode' => 'include', 'match' => 'any', 'conditions' => $conditions])->keep(self::ROW));
        self::assertTrue($compiler->compile(['mode' => 'exclude', 'match' => 'all', 'conditions' => $conditions])->keep(self::ROW));
        self::assertFalse($compiler->compile(['mode' => 'EXCLUDE', 'match' => 'ANY', 'conditions' => $conditions])->keep(self::ROW));
    }

    public function testNoConditionsKeepsEverything(): void
    {
        $filter = (new RowFilterCompiler())->compile([]);

        self::assertTrue($filter->isEmpty());
        self::assertTrue($filter->keep([]));
        self::assertFalse((new RowFilterCompiler())->compile(['conditions' => [['expression' => '1']]])->isEmpty());
    }

    public function testExpressions(): void
    {
        $filter = (new RowFilterCompiler())->compile([
            'conditions' => [['expression' => "{Qty} > 1 and CONTAINS({Name}, 'shirt')"]],
        ]);

        self::assertTrue($filter->keep(self::ROW));
        self::assertFalse($filter->keep(['Qty' => '0', 'Name' => 'Shirt']));
    }

    public function testExpressionErrorsAreRowErrors(): void
    {
        $filter = (new RowFilterCompiler())->compile(['conditions' => [['expression' => '{Qty} / 0 > 1']]]);

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('Row filter: Division by zero. (in {Qty} / 0 > 1)');
        $filter->keep(self::ROW);
    }

    public function testProblemsAreListedTogether(): void
    {
        try {
            (new RowFilterCompiler())->compile([
                'mode' => 'only',
                'match' => 'some',
                'conditions' => [
                    ['operator' => 'equals'],
                    ['column' => 'Nope', 'operator' => 'equals'],
                    ['column' => 'Qty', 'operator' => 'like'],
                    ['column' => 'Qty', 'operator' => 'greater_than', 'value' => 'ten'],
                    ['column' => 'Qty', 'operator' => 'regex', 'value' => '('],
                    ['expression' => '1 +'],
                    'not a condition',
                ],
            ], ['Qty']);
            self::fail('Expected a filter exception');
        } catch (FilterException $exception) {
            self::assertSame(
                "The filter mode must be \"include\" or \"exclude\".\n"
                . "The filter match must be \"all\" or \"any\".\n"
                . "Filter 1: choose a column or write an expression.\n"
                . "Filter 2: the file has no column \"Nope\".\n"
                . 'Filter 3: unknown operator "like". Operators: ' . implode(', ', RowFilterCompiler::OPERATORS) . ".\n"
                . "Filter 4: \"ten\" is not a number.\n"
                . "Filter 5: the pattern \"(\" is not valid.\n"
                . "Filter 6: The expression ends too early.\n"
                . 'Filter 7: choose a column or write an expression.',
                $exception->getMessage()
            );
        }
    }
}
