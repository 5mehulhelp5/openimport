<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

/**
 * Text transforms: trim, lower, upper, title, replace, regex, strip_html, prefix, suffix, default, split, join.
 */
class TextTransforms implements TransformProviderInterface
{
    private const BLOCK_TAGS = '/<\s*(\/?\s*(p|div|li|ul|ol|tr|td|th|table|h[1-6]|section|article|blockquote)\b[^>]*|br\s*\/?)\s*>/i';

    /**
     * @inheritDoc
     */
    public function names(): array
    {
        return ['trim', 'lower', 'upper', 'title', 'replace', 'regex', 'strip_html', 'prefix', 'suffix', 'default',
            'split', 'join'];
    }

    /**
     * @inheritDoc
     */
    public function compile(string $name, Arguments $arguments, TransformScope $scope): \Closure
    {
        return match ($name) {
            'trim' => $this->noArguments($arguments, static fn (string $value): string => trim($value)),
            'lower' => $this->noArguments($arguments, static fn (string $value): string => mb_strtolower($value)),
            'upper' => $this->noArguments($arguments, static fn (string $value): string => mb_strtoupper($value)),
            'title' => $this->noArguments(
                $arguments,
                static fn (string $value): string => mb_convert_case($value, MB_CASE_TITLE)
            ),
            'replace' => $this->replace($arguments),
            'regex' => $this->regex($arguments),
            'strip_html' => $this->noArguments($arguments, fn (string $value): string => $this->stripHtml($value)),
            'prefix', 'suffix' => $this->affix($name, $arguments),
            'default' => $this->default($arguments),
            'split' => $this->split($arguments),
            'join' => $this->join($arguments),
            default => throw new TransformException(sprintf('Unknown transform "%s".', $name)),
        };
    }

    private function noArguments(Arguments $arguments, \Closure $function): \Closure
    {
        $arguments->expectCount(0, 0);

        return $function;
    }

    private function replace(Arguments $arguments): \Closure
    {
        $arguments->expectCount(1, 2);
        $find = $arguments->string(0);
        if ($find === '') {
            throw $arguments->error('the text to find is empty');
        }
        $replace = $arguments->string(1, '');

        return static fn (string $value): string => str_replace($find, $replace, $value);
    }

    /**
     * Pattern without delimiters, e.g. "\s+" or "(?i)^art-"; Unicode-aware. Replacements may use $1.
     */
    private function regex(Arguments $arguments): \Closure
    {
        $arguments->expectCount(1, 2);
        $pattern = $arguments->string(0);
        if ($pattern === '') {
            throw $arguments->error('the pattern is empty');
        }
        $regex = '~' . str_replace('~', '\~', $pattern) . '~u';
        $replace = $arguments->string(1, '');
        $valid = $this->quietly(static fn (): int|false => preg_match($regex, ''));
        if ($valid === false) {
            throw $arguments->error(sprintf('the pattern "%s" is not valid', $pattern));
        }

        return function (string $value) use ($regex, $replace, $pattern): string {
            $result = $this->quietly(static fn (): ?string => preg_replace($regex, $replace, $value));
            if (!is_string($result)) {
                throw new TransformException(sprintf(
                    'regex: the pattern "%s" failed on this value (%s).',
                    $pattern,
                    preg_last_error_msg()
                ));
            }
            return $result;
        };
    }

    /**
     * Runs a PCRE call without letting a pattern warning reach the error handler.
     *
     * @param \Closure $call
     * @return mixed
     */
    private function quietly(\Closure $call): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }

    private function stripHtml(string $value): string
    {
        if (!str_contains($value, '<') && !str_contains($value, '&')) {
            return trim((string) preg_replace('/\s+/u', ' ', $value));
        }
        $html = (string) preg_replace(['~<(script|style)\b.*?</\1\s*>~is', self::BLOCK_TAGS], ['', ' '], $value);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $text = str_replace("\u{00A0}", ' ', (string) $document->textContent);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function affix(string $name, Arguments $arguments): \Closure
    {
        $arguments->expectCount(1, 1);
        $text = $arguments->string(0);

        return $name === 'prefix'
            ? static fn (string $value): string => $value === '' ? '' : $text . $value
            : static fn (string $value): string => $value === '' ? '' : $value . $text;
    }

    private function default(Arguments $arguments): \Closure
    {
        $arguments->expectCount(1, 1);
        $default = $arguments->string(0);

        return static fn (string $value): string => trim($value) === '' ? $default : $value;
    }

    /**
     * split(separator, position): 1 is the first part, -1 the last; a missing part is empty.
     */
    private function split(Arguments $arguments): \Closure
    {
        $arguments->expectCount(1, 2);
        $separator = $arguments->string(0);
        if ($separator === '') {
            throw $arguments->error('the separator is empty');
        }
        $position = $arguments->integer(1, 1, -1000, 1000);
        if ($position === 0) {
            throw $arguments->error('the position starts at 1 (or -1 for the last part)');
        }

        return static function (string $value) use ($separator, $position): string {
            if ($value === '') {
                return '';
            }
            $parts = explode($separator, $value);
            $index = $position > 0 ? $position - 1 : count($parts) + $position;

            return trim($parts[$index] ?? '');
        };
    }

    /**
     * join(separator, column, …): the current value followed by the named source columns; empty values are left out.
     */
    private function join(Arguments $arguments): \Closure
    {
        $arguments->expectCount(2, -1);
        $separator = $arguments->string(0);
        $columns = $arguments->rest(1);

        return static function (string $value, array $row) use ($separator, $columns): string {
            $parts = [$value];
            foreach ($columns as $column) {
                $parts[] = isset($row[$column]) ? (string) $row[$column] : '';
            }

            return implode($separator, array_filter($parts, static fn (string $part): bool => trim($part) !== ''));
        };
    }
}
