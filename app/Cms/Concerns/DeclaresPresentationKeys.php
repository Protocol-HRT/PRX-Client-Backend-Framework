<?php

namespace App\Cms\Concerns;

use App\Cms\Support\LayoutFields;

/**
 * Default `presentationKeys()` for both definition kinds.
 *
 * It leans on an invariant the CMS already documents and enforces: defaults()
 * must contain no copy — only nulls, empty arrays and structural flags. So a
 * key carrying a non-null default IS a structural flag, and needs no separate
 * declaration. Add a new knob with a default and it classifies itself; the
 * list cannot drift out of sync with the blueprint the way a hand-maintained
 * one would.
 *
 * The layout knobs are added on top because they live in every section's data
 * without appearing in any blueprint's defaults() — the form builder injects
 * them for every type.
 *
 * A blueprint with a structural key that has no default should override this
 * and merge its own.
 */
trait DeclaresPresentationKeys
{
    /**
     * Keys in `data` that describe how the section looks, not what it says.
     *
     * @return list<string>
     */
    public function presentationKeys(): array
    {
        $flagged = array_keys(array_filter(
            $this->defaults(),
            static fn (mixed $default): bool => $default !== null && $default !== '' && $default !== [],
        ));

        return array_values(array_unique([...LayoutFields::KEYS, ...$flagged]));
    }
}
