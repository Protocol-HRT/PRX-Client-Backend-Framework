<?php

namespace App\Services\Cms;

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
        self::validateLevel($fields, insideRepeater: false, context: '');
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function validateLevel(array $fields, bool $insideRepeater, string $context): void
    {
        $seenKeys = [];

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

            if ($kind === FlexibleFieldKind::Repeater) {
                if ($insideRepeater) {
                    throw new InvalidArgumentException("Repeater field '{$key}'{$context} cannot contain another repeater — only one nesting level is allowed.");
                }

                self::validateRepeaterChildren($field, $key);
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
    private static function validateRepeaterChildren(array $field, string $key): void
    {
        $children = $field['fields'] ?? null;

        if (! is_array($children) || $children === []) {
            throw new InvalidArgumentException("Repeater field '{$key}' must define at least one child field.");
        }

        self::validateLevel($children, insideRepeater: true, context: " inside repeater '{$key}'");
    }
}
