<?php

namespace App\Cms\Support;

use App\Models\Catalog\CatalogItemSection;
use App\Models\Cms\GlobalSection;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Model;

/**
 * Which sections still reference a palette colour BY NAME.
 *
 * Deleting a palette entry that a section stores does not fall back — it
 * fails invisibly. The frontend emits the knob as `--sx-bg: var(--palette-{name})`
 * guarded by an `.sx-bg` marker class; drop the palette row and the custom
 * property is never defined, the declaration computes to `unset`, and the band
 * renders TRANSPARENT rather than reverting to the section partial's own
 * background. No CSS-side fallback can recover it, because the marker class is
 * still there saying "an operator chose a colour here". So the only place this
 * can be caught is before the delete is saved.
 *
 * A RENAME IS A REMOVAL. Sections store the name, not an id, so renaming
 * `sand` to `bone` breaks every section holding `sand` exactly as deleting it
 * would. Callers must diff old names against new ones rather than watching for
 * a delete action, which is also why the guard lives at save time and not on
 * the Repeater's per-item delete button.
 *
 * THE WALK IS PHP, NOT SQL, and that cost was accepted deliberately when typed
 * sub-blocks were kept out of a table of their own: a child's knobs live inside
 * the parent's `data` JSON, so a `LIKE '%sand%'` on a colour name cannot tell a
 * child's background from the word appearing in authored copy. See
 * docs/cms/dev.md on sub-block storage. At this scale — tens of section rows —
 * decoding every row is nothing; do not "optimise" it into a LIKE that silently
 * stops seeing children.
 */
final class PaletteUsage
{
    /**
     * The layout knobs whose value is a palette colour NAME.
     *
     * A subset of LayoutFields::KEYS: `style_background_image` is a media id
     * and `extra_padding` and friends are tokens, so neither can hold a
     * palette name. Adding a name-valued knob means adding it here too, or
     * the guard goes blind to it — PaletteDeletionGuardTest's
     * test_palette_keys_stay_a_subset_of_the_layout_vocabulary pins the two
     * lists together so they cannot drift apart.
     *
     * @var list<string>
     */
    public const KEYS = [
        'style_background_color',
        'style_text_color',
    ];

    /**
     * Find which of $names are still in use, and where.
     *
     * @param  list<string>  $names
     * @return array<string, list<string>> colour name => human labels, only for names actually in use
     */
    public static function find(array $names): array
    {
        $names = array_values(array_filter(array_map(
            static fn (mixed $n): string => is_string($n) ? $n : '',
            $names,
        ), 'strlen'));

        if ($names === []) {
            return [];
        }

        $wanted = array_flip($names);
        $found = [];

        foreach (self::rows() as [$model, $label]) {
            $data = $model->data;

            if (! is_array($data)) {
                continue;
            }

            foreach (self::namesIn($data) as $name) {
                if (isset($wanted[$name])) {
                    $found[$name][] = $label;
                }
            }
        }

        // Two sections on the same page can hold the same colour; the operator
        // needs to know the colour is in use, not to read the page twice.
        return array_map(
            static fn (array $labels): array => array_values(array_unique($labels)),
            $found,
        );
    }

    /**
     * Every palette name stored anywhere in one section's data.
     *
     * Recursive on purpose. Children are one level deep today, but the walk
     * costs nothing extra and a nesting depth change must not silently reopen
     * this hole.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function namesIn(array $data): array
    {
        $names = [];

        foreach (self::KEYS as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $names[] = $value;
            }
        }

        foreach (SectionChildren::items($data) as $child) {
            $childData = $child['data'] ?? null;

            if (is_array($childData)) {
                $names = array_merge($names, self::namesIn($childData));
            }
        }

        return $names;
    }

    /**
     * Every section row in the install, paired with a label an operator can act on.
     *
     * All three tables, because a colour used only on a catalog item's section
     * or on a global section breaks just as visibly as one on a page — and the
     * operator editing the palette is not looking at any of them.
     *
     * DISABLED sections and sections the backend reports as empty are counted
     * too, deliberately. Neither is on a live page right now, but both are one
     * toggle or one paragraph away from being on one, and a guard that let you
     * delete a colour they hold would break them at the moment they are turned
     * back on — the worst possible time to discover it. Do not "optimise" this
     * into a filter on `enabled`.
     *
     * lazy() rather than cursor(): cursor() hydrates row by row and ignores
     * eager loads, so `with('page')` would be dead and each label would cost
     * its own query.
     *
     * @return iterable<array{0: Model, 1: string}>
     */
    private static function rows(): iterable
    {
        foreach (PageSection::query()->with('page')->lazy() as $section) {
            $page = $section->page?->title ?? $section->page?->slug ?? "page #{$section->page_id}";

            yield [$section, "{$page} — {$section->type}"];
        }

        foreach (CatalogItemSection::query()->lazy() as $section) {
            $owner = class_basename((string) $section->sectionable_type);

            yield [$section, "{$owner} #{$section->sectionable_id} — {$section->type}"];
        }

        foreach (GlobalSection::query()->lazy() as $section) {
            $name = $section->name ?? $section->slug;

            yield [$section, "global section “{$name}” — {$section->type}"];
        }
    }

    /**
     * The message an operator sees when the guard refuses.
     *
     * @param  array<string, list<string>>  $usages
     */
    public static function message(array $usages): string
    {
        $parts = [];

        foreach ($usages as $name => $labels) {
            $shown = array_slice($labels, 0, 5);
            $more = count($labels) - count($shown);
            $where = implode('; ', $shown).($more > 0 ? " (and {$more} more)" : '');

            $parts[] = "“{$name}” is used by {$where}";
        }

        return 'This color is still in use, and removing or renaming it would make those sections render with no background at all. '
            .implode('. ', $parts)
            .'. Change those sections to another color first, then remove it here.';
    }
}
