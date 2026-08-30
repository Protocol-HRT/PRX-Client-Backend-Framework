<?php

namespace App\Support;

/**
 * Normalizes the `detail_layout` column written by the catalog forms.
 *
 * THE PROBLEM THIS SOLVES. Filament hydrates an unset Select as null and an
 * unset CheckboxList as `[]`, and dehydrates them the same way. So opening a
 * product, fixing a typo in the subtitle and saving writes a fully-populated
 * layout object of nulls — including `rails: []`. The frontend reads a present
 * empty list as "the operator deliberately chose no rails", so an edit that
 * never touched the Layout tab would silently delete a page's recommendation
 * rails. That is the same class of silent, invisible save behaviour the
 * detail_layout work exists to remove, so it must not be reintroduced here.
 *
 * `prune()` therefore drops anything the operator did not actually choose:
 * nulls, empty strings, and empty arrays, recursively, along with any nested
 * group left empty as a result. What survives is only what was set, so
 * "never configured" stays expressible after a save and the form's own
 * promise — "blank means the deployment default" — stays true.
 *
 * BECAUSE OF THIS, "no rails at all" CANNOT be an empty list: an empty list is
 * indistinguishable from an untouched control and is pruned away. It is the
 * explicit `none` token instead, the same idiom the section spacing scale
 * uses, where `none` is deliberately not redundant with leaving a knob unset.
 */
final class DetailLayout
{
    /**
     * @param  array<string, mixed>|null  $layout
     * @return array<string, mixed>
     */
    public static function prune(?array $layout): array
    {
        if ($layout === null) {
            return [];
        }

        $pruned = [];

        foreach ($layout as $key => $value) {
            if (is_array($value)) {
                $nested = self::prune($value);

                // A list (rails) keeps its own emptiness meaning — an empty
                // one is pruned, not preserved — while an empty group
                // (accordions, pair_with) simply disappears.
                if ($nested !== []) {
                    $pruned[$key] = array_is_list($value) ? array_values($nested) : $nested;
                }

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $pruned[$key] = $value;
        }

        return $pruned;
    }
}
