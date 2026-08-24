<?php

namespace App\Cms\Support;

/**
 * The layout knob vocabulary, in one place so the API layer can classify a
 * payload without depending on Filament.
 *
 * These keys live in every section's `data` without appearing in any
 * blueprint's defaults(), because SectionFormBuilder injects the same
 * "Layout & spacing" panel for every type. That is exactly why they must be
 * listed here: presentationKeys() unions KEYS in, and SectionContent counts
 * anything NOT a presentation key as authored content. A knob added to the
 * form but missed here makes an untouched scaffold look authored the moment
 * an operator nudges it, and the empty section leaks onto a live page.
 *
 * ADDING A KNOB: add it to KEYS in the same commit as its form control.
 */
final class LayoutFields
{
    /** @var list<string> */
    public const KEYS = [
        'extra_padding',
        'content_inset',
        'content_width',
        'content_align',
        'media_width',
    ];

    /**
     * Fill in a definition's layout defaults where the operator left a knob
     * unset, so a section looks right out of the box and a later retune of
     * the design default reaches sections that already exist.
     *
     * Only KEYS are merged: a definition cannot smuggle content into a
     * payload through layoutDefaults(), which would defeat hasContent().
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function applyDefaults(array $data, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }

            if (($data[$key] ?? null) === null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
