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
        // NAMESPACED, and it must stay that way. `background_image` was tried
        // bare first and collided head-on with an authored content field of
        // the same name on hero, cta-banner and image-callout-banner: the
        // key landed in KEYS, presentationKeys() reclassified those sections'
        // real background images as presentation, and a hero carrying only a
        // background image would have reported has_content: false and
        // vanished from a live page. Style words are exactly what a blueprint
        // wants to call its own fields, so every style knob carries the
        // prefix. LayoutFieldCollisionTest enforces it.
        'style_background_color',
        'style_background_image',
        'style_text_color',
        // Accents and CTAs. Separate from style_text_color on purpose: copy,
        // brand accents and button fills are three different jobs, and one
        // knob driving all three cannot express the design a section already
        // has — today's buttons are ink-on-white while its accents are gold.
        'style_accent_color',
        'style_button_color',
    ];

    /**
     * Layout keys holding a media id rather than a token.
     *
     * Media resolution is driven by each blueprint's fieldKinds(), and these
     * keys appear in NO blueprint — SectionFormBuilder injects them into
     * every type. SectionDataTransformer therefore unions this list in, or a
     * background image reaches the frontend as a bare integer instead of the
     * resolved {id, url, alt, width, height}.
     *
     * @var list<string>
     */
    public const IMAGE_KEYS = [
        'style_background_image',
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
