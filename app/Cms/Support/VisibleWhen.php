<?php

namespace App\Cms\Support;

/**
 * Evaluates a field's declarative `visible_when` rules — the data-driven
 * replacement for the Get-closure visibility toggles code blueprints use.
 *
 * Shape: a list of conditions, ANDed together:
 *   [{"field": "mode", "operator": "equals", "value": "manual"}, …]
 *
 * Operators: `equals` (default), `not_equals`, `contains`, `not_contains`.
 * Comparison for the first two is loose via string casts on both sides
 * because Filament re-saves select values as integers ('4' -> 4) and null
 * must compare as ''.
 *
 * `contains` exists for MULTI-VALUED state and is not a substring test. The
 * intake quiz branches on answers like `health_goals: ["weight-management",
 * "sleep"]`, and `equals` cannot express "one of these was picked" — casting
 * an array to a string to compare it would fatal, which is why `stringable()`
 * excludes arrays and an array silently compared as '' before this existed.
 * It also accepts a scalar, where it degrades to equality, so a condition
 * keeps working if a question is changed from multi- to single-select.
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

            $operator = $condition['operator'] ?? 'equals';
            $expectedRaw = $condition['value'] ?? null;

            if ($operator === 'contains' || $operator === 'not_contains') {
                $matches = self::contains($get($field), $expectedRaw);

                if ($operator === 'not_contains' ? $matches : ! $matches) {
                    return false;
                }

                continue;
            }

            $actual = self::stringable($get($field)) ? (string) $get($field) : '';
            $expected = self::stringable($expectedRaw) ? (string) $expectedRaw : '';
            $matches = $actual === $expected;

            if ($operator === 'not_equals' ? $matches : ! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Membership, with a scalar degrading to equality.
     *
     * Compared as strings for the same reason the equality path is: a select
     * value saved as 4 must still match an authored '4'.
     */
    private static function contains(mixed $actual, mixed $expected): bool
    {
        if (! self::stringable($expected)) {
            return false;
        }

        $needle = (string) $expected;

        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (self::stringable($item) && (string) $item === $needle) {
                    return true;
                }
            }

            return false;
        }

        return self::stringable($actual) && (string) $actual === $needle;
    }

    private static function stringable(mixed $value): bool
    {
        return is_scalar($value);
    }
}
