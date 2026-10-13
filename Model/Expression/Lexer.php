<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Expression;

/**
 * Splits an expression into tokens.
 *
 * Numbers start with a digit (0.5, not .5). Strings use single or double quotes; a doubled quote inside is one quote.
 * Columns are written {Column Name}; "}" inside a name is written "}}".
 */
class Lexer
{
    private const OPERATORS = ['<=', '>=', '!=', '<>', '+', '-', '*', '/', '%', '.', '=', '<', '>', '(', ')', ','];

    /**
     * @param string $expression
     * @return Token[]
     * @throws ExpressionException
     */
    public function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $i = 0;
        while ($i < $length) {
            $char = $expression[$i];
            if (ctype_space($char)) {
                $i++;
                continue;
            }
            $position = $i + 1;
            if (ctype_digit($char)) {
                preg_match('/\G\d+(\.\d+)?([eE][+-]?\d+)?/', $expression, $match, 0, $i);
                $tokens[] = new Token(Token::NUMBER, $match[0], $position);
                $i += strlen($match[0]);
                continue;
            }
            if ($char === "'" || $char === '"') {
                [$text, $i] = $this->quoted($expression, $i, $char, 'string');
                $tokens[] = new Token(Token::STRING, $text, $position);
                continue;
            }
            if ($char === '{') {
                [$text, $i] = $this->quoted($expression, $i, '}', 'column name');
                if (trim($text) === '') {
                    throw new ExpressionException(sprintf('Empty column name at position %d.', $position));
                }
                $tokens[] = new Token(Token::COLUMN, $text, $position);
                continue;
            }
            if (ctype_alpha($char) || $char === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $expression, $match, 0, $i);
                $tokens[] = new Token(Token::NAME, $match[0], $position);
                $i += strlen($match[0]);
                continue;
            }
            foreach (self::OPERATORS as $operator) {
                if (substr_compare($expression, $operator, $i, strlen($operator)) === 0) {
                    $tokens[] = new Token(Token::OPERATOR, $operator === '<>' ? '!=' : $operator, $position);
                    $i += strlen($operator);
                    continue 2;
                }
            }
            throw new ExpressionException(sprintf('Unexpected character "%s" at position %d.', $char, $position));
        }
        $tokens[] = new Token(Token::END, '', $length + 1);

        return $tokens;
    }

    /**
     * Reads a quoted part starting at $start; a doubled closing character stands for itself.
     *
     * @param string $expression
     * @param int $start
     * @param string $close
     * @param string $what
     * @return array{0: string, 1: int} text and the position after the closing character
     */
    private function quoted(string $expression, int $start, string $close, string $what): array
    {
        $length = strlen($expression);
        $text = '';
        $i = $start + 1;
        while ($i < $length) {
            if ($expression[$i] === $close) {
                if ($i + 1 < $length && $expression[$i + 1] === $close) {
                    $text .= $close;
                    $i += 2;
                    continue;
                }
                return [$text, $i + 1];
            }
            $text .= $expression[$i];
            $i++;
        }
        throw new ExpressionException(sprintf(
            'The %s starting at position %d is not closed with %s.',
            $what,
            $start + 1,
            $close
        ));
    }
}
