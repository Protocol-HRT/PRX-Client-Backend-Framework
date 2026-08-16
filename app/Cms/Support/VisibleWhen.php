<?php

namespace App\Cms\Support;

/**
 * Evaluates a field's declarative `visible_when` rules — the data-driven
 * replacement for the Get-closure visibility toggles code blueprints use.
 *
 * Shape: a list of conditions, ANDed together:
 *   [{"field": "mode", "operator": "equals", "value": "manual"}, …]
 *
 * Operators: `equals` (default) and `not_equals`. Comparison is loose via
 * string casts on both sides because Filament re-saves select values as
 * integers ('4' -> 4) and null must compare as ''.
 */
class VisibleWhen
{
    /**
     * @param  list<array<string, mixed>>  $conditions
     * @param  callable(string): mixed  $get  Sibling-state accessor.
     */
    public static function passes(array $conditions, callable $get): bool
    {
        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $field = $condition['field'] ?? null;

            if (! is_string($field) || $field === '') {
                continue;
            }

            $actual = self::stringable($get($field)) ? (string) $get($field) : '';
            $expected = self::stringable($condition['value'] ?? null) ? (string) $condition['value'] : '';
            $matches = $actual === $expected;

            if (($condition['operator'] ?? 'equals') === 'not_equals' ? $matches : ! $matches) {
                return false;
            }
        }

        return true;
    }

    private static function stringable(mixed $value): bool
    {
        return is_scalar($value);
    }
}
