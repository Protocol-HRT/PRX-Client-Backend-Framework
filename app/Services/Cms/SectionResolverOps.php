<?php

namespace App\Services\Cms;

use App\Cms\Support\CtaFields;
use App\Cms\Support\VisibleWhen;
use InvalidArgumentException;

/**
 * Declarative resolver pipeline for DB-defined section types — the
 * data-driven replacement for code blueprints' resolveData() overrides.
 *
 * A type's schema may carry a `resolvers` list; each entry names an op from
 * the fixed vocabulary below plus its parameters. Ops run in order after
 * field-kind transforms, mirroring how blueprints append computed keys
 * (inlined catalog cards, resolved CTAs) to the payload. Behavior is PHP
 * written once here; which types use it is pure data.
 *
 *   {"op": "products_by_mode", "output": "products"}
 *   {"op": "inline_product", "input": "product_id", "output": "product"}
 *   {"op": "resolve_cta", "path": "callouts.*"}
 *   {"op": "cast_string", "path": "callouts.*.position"}
 *
 * Any op may carry a `when` list (visible_when shape, evaluated against the
 * payload): while the conditions fail, the op is skipped and its `output`
 * key (if any) is written as null.
 */
class SectionResolverOps
{
    /** @var list<string> */
    public const OPS = [
        'inline_product',
        'inline_package',
        'inline_products',
        'inline_packages',
        'products_by_mode',
        'packages_by_mode',
        'categories',
        'resolve_cta',
        'cast_string',
    ];

    public function __construct(private readonly CatalogInliner $catalog) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $resolvers
     * @return array<string, mixed>
     */
    public function apply(array $data, array $resolvers): array
    {
        foreach ($resolvers as $resolver) {
            if (! is_array($resolver)) {
                continue;
            }

            if (! $this->passesWhen($data, $resolver)) {
                // A gated-off op still owns its output key: write null so the
                // payload shape matches the blueprint's conditional branches
                // (e.g. product-callout nulls the non-selected item type).
                if (is_string($resolver['output'] ?? null)) {
                    $data[$resolver['output']] = null;
                }

                continue;
            }

            $data = match ($resolver['op'] ?? null) {
                'inline_product' => $this->inlineOne($data, $resolver, 'product'),
                'inline_package' => $this->inlineOne($data, $resolver, 'package'),
                'inline_products' => $this->inlineMany($data, $resolver, 'products'),
                'inline_packages' => $this->inlineMany($data, $resolver, 'packages'),
                'products_by_mode' => $this->byMode($data, $resolver, 'products'),
                'packages_by_mode' => $this->byMode($data, $resolver, 'packages'),
                'categories' => $this->categories($data, $resolver),
                'resolve_cta' => $this->applyAtPath($data, (string) ($resolver['path'] ?? ''), fn (array $item): array => CtaFields::resolve($item)),
                'cast_string' => $this->castString($data, (string) ($resolver['path'] ?? '')),
                default => $data,
            };
        }

        return $data;
    }

    /**
     * Structural validation for a schema's resolver list. Throws on the
     * first violation, mirroring FlexibleSchemaValidator's contract.
     *
     * @param  list<array<string, mixed>>  $resolvers
     */
    public static function validate(array $resolvers): void
    {
        foreach (array_values($resolvers) as $index => $resolver) {
            $position = 'Resolver #'.($index + 1);

            if (! is_array($resolver)) {
                throw new InvalidArgumentException("{$position} is malformed — expected a resolver object.");
            }

            $op = $resolver['op'] ?? null;

            if (! is_string($op) || ! in_array($op, self::OPS, true)) {
                throw new InvalidArgumentException("{$position} has unknown op '".(is_scalar($op) ? $op : gettype($op))."'. Known ops: ".implode(', ', self::OPS).'.');
            }

            if (in_array($op, ['inline_product', 'inline_package', 'inline_products', 'inline_packages'], true)
                && (! is_string($resolver['input'] ?? null) || ! is_string($resolver['output'] ?? null))) {
                throw new InvalidArgumentException("{$position} ({$op}) needs 'input' and 'output' keys.");
            }

            if (isset($resolver['output']) && ! is_string($resolver['output'])) {
                throw new InvalidArgumentException("{$position} ({$op}): 'output' must be a string key.");
            }

            if ($op === 'cast_string' && ! is_string($resolver['path'] ?? null)) {
                throw new InvalidArgumentException("{$position} (cast_string) needs a 'path' to the value to cast.");
            }

            $when = $resolver['when'] ?? null;

            if ($when !== null) {
                if (! is_array($when)) {
                    throw new InvalidArgumentException("{$position} ({$op}): 'when' must be a list of conditions.");
                }

                foreach (array_values($when) as $conditionIndex => $condition) {
                    if (! is_array($condition)
                        || ! is_string($condition['field'] ?? null)
                        || ! is_scalar($condition['value'] ?? null)
                        || ! in_array($condition['operator'] ?? 'equals', ['equals', 'not_equals'], true)) {
                        throw new InvalidArgumentException("{$position} ({$op}): 'when' condition #".($conditionIndex + 1).' needs a field, an equals/not_equals operator, and a scalar value.');
                    }
                }
            }
        }
    }

    /**
     * Evaluate an op's optional `when` gate — the payload-side counterpart
     * of a field's visible_when (same shape, same loose comparison).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $resolver
     */
    private function passesWhen(array $data, array $resolver): bool
    {
        $conditions = $resolver['when'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        return VisibleWhen::passes($conditions, fn (string $field): mixed => $data[$field] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function inlineOne(array $data, array $resolver, string $kind): array
    {
        $input = (string) ($resolver['input'] ?? '');
        $output = (string) ($resolver['output'] ?? '');
        $id = is_numeric($data[$input] ?? null) ? (int) $data[$input] : null;

        $data[$output] = $kind === 'product'
            ? $this->catalog->product($id)
            : $this->catalog->package($id);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function inlineMany(array $data, array $resolver, string $kind): array
    {
        $input = (string) ($resolver['input'] ?? '');
        $output = (string) ($resolver['output'] ?? '');
        $ids = array_map('intval', array_filter((array) ($data[$input] ?? []), 'is_numeric'));

        $data[$output] = $kind === 'products'
            ? $this->catalog->productsByIds($ids)
            : $this->catalog->packagesByIds($ids);

        return $data;
    }

    /**
     * Replicates the blueprint slider convention: `mode` picks manual ids or
     * a rule (featured / newest / category), writing the inlined list to
     * `output` while every input key stays raw in the payload.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function byMode(array $data, array $resolver, string $kind): array
    {
        $mode = (string) ($data[$resolver['mode_key'] ?? 'mode'] ?? 'manual');
        $idsKey = (string) ($resolver['ids_key'] ?? ($kind === 'products' ? 'product_ids' : 'package_ids'));
        $limit = (int) ($data[$resolver['limit_key'] ?? 'limit'] ?? ($resolver['default_limit'] ?? 8));
        $output = (string) ($resolver['output'] ?? $kind);

        if ($mode === 'manual') {
            $ids = array_map('intval', array_filter((array) ($data[$idsKey] ?? []), 'is_numeric'));

            $data[$output] = $kind === 'products'
                ? $this->catalog->productsByIds($ids)
                : $this->catalog->packagesByIds($ids);

            return $data;
        }

        $categoryKey = (string) ($resolver['category_key'] ?? 'category_id');
        $categoryId = is_numeric($data[$categoryKey] ?? null) ? (int) $data[$categoryKey] : null;

        $data[$output] = $kind === 'products'
            ? $this->catalog->productsByMode($mode, $categoryId, $limit)
            : $this->catalog->packagesByMode($mode, $categoryId, $limit);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function categories(array $data, array $resolver): array
    {
        $output = (string) ($resolver['output'] ?? 'categories');
        $mode = (string) ($data[$resolver['mode_key'] ?? 'mode'] ?? 'all');

        if ($mode === 'manual') {
            $ids = array_map('intval', array_filter((array) ($data[$resolver['ids_key'] ?? 'category_ids'] ?? []), 'is_numeric'));

            $data[$output] = $this->catalog->categoriesByIds($ids);

            return $data;
        }

        $limit = (int) ($data[$resolver['limit_key'] ?? 'limit'] ?? ($resolver['default_limit'] ?? 12));

        $data[$output] = $this->catalog->visibleCategories($limit);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function castString(array $data, string $path): array
    {
        return $this->walkPath($data, explode('.', $path), fn (mixed $value): mixed => is_scalar($value) ? (string) $value : $value);
    }

    /**
     * Apply $fn to the ARRAY at a dot path ('' = the payload root, 'items.*'
     * = each repeater item) — used by resolve_cta, whose flat keys live
     * inside the target array itself.
     *
     * @param  array<string, mixed>  $data
     * @param  callable(array<string, mixed>): array<string, mixed>  $fn
     * @return array<string, mixed>
     */
    private function applyAtPath(array $data, string $path, callable $fn): array
    {
        if ($path === '') {
            return $fn($data);
        }

        return $this->walkPath(
            $data,
            explode('.', $path),
            fn (mixed $value): mixed => is_array($value) ? $fn($value) : $value,
        );
    }

    /**
     * Apply $fn to the VALUE at a dot path, `*` fanning out over list items.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function walkPath(array $data, array $segments, callable $fn): array
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach ($data as $index => $item) {
                if ($segments === []) {
                    $data[$index] = $fn($item);
                } elseif (is_array($item)) {
                    $data[$index] = $this->walkPath($item, $segments, $fn);
                }
            }

            return $data;
        }

        if ($segment === null || ! array_key_exists($segment, $data)) {
            return $data;
        }

        if ($segments === []) {
            $data[$segment] = $fn($data[$segment]);

            return $data;
        }

        if (is_array($data[$segment])) {
            $data[$segment] = $this->walkPath($data[$segment], $segments, $fn);
        }

        return $data;
    }
}
