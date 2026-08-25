<?php

namespace App\Cms\Support;

/**
 * The one data key typed sub-blocks live under, and the shape they serve as.
 *
 * RESERVED, the same way the style knobs are namespaced. `children` is
 * structural: the transformer recognises it positionally, so a blueprint or
 * an admin-defined flexible type that declared a content field of the same
 * name would have its content run through the block pipeline and dropped.
 * That is the `background_image` collision again — it cost a live-page bug
 * once, so this key is guarded twice, the same way that one is:
 * LayoutFieldCollisionTest asserts no CODE blueprint declares it, and
 * FlexibleSectionTypeForm::reservedFieldKeys() refuses it at the point an
 * operator types it, which is the only place a runtime-created flexible type
 * can be caught.
 *
 * NOT IN LayoutFields::KEYS, and the distinction matters. Layout keys are
 * PRESENTATION — SectionContent skips them when deciding whether a section
 * was authored. Children are CONTENT, so `children` must stay countable, and
 * each child is judged by its own has_content rather than by the mere fact
 * that a child exists. Putting this key in KEYS would make a section holding
 * nothing but children render as empty.
 *
 * Served shape, per item:
 *
 *   { type: "testimonial", data: { ... }, has_content: true }
 *
 * which is Filament Builder's own `{type, data}` plus the emptiness verdict,
 * computed once here rather than re-derived by every consumer.
 */
final class SectionChildren
{
    public const KEY = 'children';

    /**
     * A served child envelope, as distinct from an ordinary nested array.
     *
     * SectionContent leans on this to judge a child by its own verdict: a
     * child holding only knobs is empty, but its `type` discriminator is a
     * non-empty string, so a generic walk would count it as authored content
     * and leak an empty scaffold onto a live page.
     *
     * @phpstan-assert-if-true array{type: string, data: array<string, mixed>, has_content: bool} $value
     */
    public static function isEnvelope(mixed $value): bool
    {
        return is_array($value)
            && array_key_exists('type', $value)
            && array_key_exists('has_content', $value)
            && array_key_exists('data', $value);
    }

    /**
     * The stored items at $data[KEY], filtered to those carrying a type.
     *
     * Filament writes a partially-added block before its type is chosen, and
     * an item whose block was removed from the registry must not become a
     * fatal — both arrive here as untyped rows and are dropped.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function items(array $data): array
    {
        $items = $data[self::KEY] ?? null;

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            $items,
            static fn (mixed $item): bool => is_array($item) && filled($item['type'] ?? null),
        ));
    }
}
