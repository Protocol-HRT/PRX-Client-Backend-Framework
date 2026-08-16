<?php

namespace App\Services\Cms;

use App\Cms\Support\CtaFields;
use App\Enums\Cms\FlexibleFieldKind;
use InvalidArgumentException;

/**
 * Structural validator for the flexible section type field schema
 * ({"fields": [...]}) authored in the admin. Throws a human-readable
 * InvalidArgumentException on the first violation found.
 */
class FlexibleSchemaValidator
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const MAX_KEY_LENGTH = 64;

    /**
     * @param  list<array<string, mixed>>  $fields
     *
     * @throws InvalidArgumentException
     */
    public static function validate(array $fields): void
    {
        self::validateLevel($fields, repeaterDepth: 0, insideGroup: false, context: '');
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function validateLevel(array $fields, int $repeaterDepth, bool $insideGroup, string $context): void
    {
        $seenKeys = [];
        $ctaSeen = false;

        foreach (array_values($fields) as $index => $field) {
            $position = 'Field #'.($index + 1).$context;

            if (! is_array($field)) {
                throw new InvalidArgumentException("{$position} is malformed — expected a field definition object.");
            }

            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException("{$position} is missing a key.");
            }

            if (mb_strlen($key) > self::MAX_KEY_LENGTH) {
                throw new InvalidArgumentException("{$position}: key '{$key}' exceeds ".self::MAX_KEY_LENGTH.' characters.');
            }

            if (! preg_match(self::KEY_PATTERN, $key)) {
                throw new InvalidArgumentException("{$position}: key '{$key}' must be snake_case — a lowercase letter followed by lowercase letters, numbers, or underscores.");
            }

            if (isset($seenKeys[$key])) {
                throw new InvalidArgumentException("Duplicate field key '{$key}'{$context}. Keys must be unique at each level.");
            }

            $seenKeys[$key] = true;

            $kindValue = $field['kind'] ?? null;

            if (! is_string($kindValue) || $kindValue === '') {
                throw new InvalidArgumentException("Field '{$key}'{$context} is missing a kind.");
            }

            $kind = FlexibleFieldKind::tryFrom($kindValue);

            if ($kind === null) {
                throw new InvalidArgumentException("Field '{$key}'{$context} has unknown kind '{$kindValue}'.");
            }

            if ($kind === FlexibleFieldKind::Select) {
                self::validateSelectOptions($field, $key, $context);
            }

            if ($kind === FlexibleFieldKind::Cta) {
                if ($ctaSeen) {
                    throw new InvalidArgumentException("Field '{$key}'{$context}: only one CTA group is allowed per level — its keys (cta_label, cta_mode, …) are flat and would collide.");
                }

                $ctaSeen = true;
            }

            self::validateVisibleWhen($field, $key, $context);

            if ($kind === FlexibleFieldKind::Repeater) {
                if ($repeaterDepth >= 2) {
                    throw new InvalidArgumentException("Repeater field '{$key}'{$context} nests too deeply — repeaters may nest at most one level.");
                }

                self::validateRepeaterChildren($field, $key, $repeaterDepth, $insideGroup);
            }

            if ($kind === FlexibleFieldKind::Group) {
                if ($repeaterDepth > 0 || $insideGroup) {
                    throw new InvalidArgumentException("Group field '{$key}'{$context} is only allowed at the top level — groups cannot nest inside repeaters or other groups.");
                }

                self::validateGroupChildren($field, $key);
            }
        }

        if ($ctaSeen) {
            $reserved = array_intersect(
                array_keys($seenKeys),
                array_merge(CtaFields::KEYS, CtaFields::RESOLVED_KEYS),
            );

            if ($reserved !== []) {
                throw new InvalidArgumentException("Field key '".reset($reserved)."'{$context} collides with the CTA group's flat keys.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function validateVisibleWhen(array $field, string $key, string $context): void
    {
        $conditions = $field['visible_when'] ?? null;

        if ($conditions === null || $conditions === []) {
            return;
        }

        if (! is_array($conditions)) {
            throw new InvalidArgumentException("Field '{$key}'{$context}: visible_when must be a list of conditions.");
        }

        foreach (array_values($conditions) as $index => $condition) {
            $target = is_array($condition) ? ($condition['field'] ?? null) : null;

            if (! is_string($target) || ! preg_match(self::KEY_PATTERN, $target)) {
                throw new InvalidArgumentException('Condition #'.($index + 1)." on field '{$key}'{$context} needs a snake_case sibling field name.");
            }

            $operator = $condition['operator'] ?? 'equals';

            if (! in_array($operator, ['equals', 'not_equals'], true)) {
                throw new InvalidArgumentException('Condition #'.($index + 1)." on field '{$key}'{$context} has unknown operator '{$operator}'.");
            }

            if (! is_scalar($condition['value'] ?? null)) {
                throw new InvalidArgumentException('Condition #'.($index + 1)." on field '{$key}'{$context} needs a scalar value.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function validateSelectOptions(array $field, string $key, string $context): void
    {
        $options = $field['options'] ?? null;

        if (! is_array($options) || $options === []) {
            throw new InvalidArgumentException("Select field '{$key}'{$context} needs at least one option.");
        }

        foreach (array_values($options) as $index => $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : null;
            $label = is_array($option) ? ($option['label'] ?? null) : null;

            if (! is_scalar($value) || (string) $value === '' || ! is_scalar($label) || (string) $label === '') {
                throw new InvalidArgumentException('Option #'.($index + 1)." of select field '{$key}'{$context} needs both a value and a label.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function validateRepeaterChildren(array $field, string $key, int $repeaterDepth, bool $insideGroup): void
    {
        $children = $field['fields'] ?? null;

        if (! is_array($children) || $children === []) {
            throw new InvalidArgumentException("Repeater field '{$key}' must define at least one child field.");
        }

        if ($field['simple'] ?? false) {
            if (count($children) !== 1) {
                throw new InvalidArgumentException("Simple repeater field '{$key}' must define exactly one child field — items store that field's bare value.");
            }

            $childKind = is_array($children[array_key_first($children)] ?? null)
                ? FlexibleFieldKind::tryFrom((string) ($children[array_key_first($children)]['kind'] ?? ''))
                : null;

            if (in_array($childKind, [null, FlexibleFieldKind::Repeater, FlexibleFieldKind::Group, FlexibleFieldKind::Cta], true)) {
                throw new InvalidArgumentException("Simple repeater field '{$key}' needs a scalar-valued child kind.");
            }
        }

        self::validateLevel($children, repeaterDepth: $repeaterDepth + 1, insideGroup: $insideGroup, context: " inside repeater '{$key}'");
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function validateGroupChildren(array $field, string $key): void
    {
        $children = $field['fields'] ?? null;

        if (! is_array($children) || $children === []) {
            throw new InvalidArgumentException("Group field '{$key}' must define at least one child field.");
        }

        self::validateLevel($children, repeaterDepth: 0, insideGroup: true, context: " inside group '{$key}'");
    }
}
