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
        // ── Layout & spacing ──────────────────────────────────────────
        //
        // RESPONSIVE OVERRIDES ARE FLAT SUFFIXED KEYS, not a nested shape.
        // The base key IS the mobile value, so existing rows need no
        // migration and no serve-time shim. The suffix scale is
        // _breakpoints.scss's (Bootstrap 5.3: md 768, lg 992) — do not
        // invent one. Adding a third tier later is one line per field here.
        //
        // Nothing else has to learn about the suffix: SectionContent::
        // hasContent() skips presentation keys BY NAME, never by value
        // shape, so listing a key here is the whole of making it
        // presentation. That is the property that made flat cheaper than
        // nested, and SectionHasContentTest pins it.
        'content_inset',
        'content_inset_md',
        'content_inset_lg',
        'content_width',
        'content_align',
        'content_align_md',
        'content_align_lg',
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
        // ── Style: the frame ──────────────────────────────────────────
        //
        // `style_padding_top` / `_bottom` REPLACE the retired `extra_padding`,
        // which was one token driving both edges at once. Splitting it is the
        // vertical half of a WordPress-style box control; the horizontal half
        // already exists as `content_inset` and is deliberately NOT duplicated
        // here.
        //
        // That narrowing is a correctness decision, not a scope cut. Padding on
        // the knob wrapper narrows `.sx-section`'s containing block, and the
        // section's own bleed is a fixed `-1 * --page-gutter` that recovers the
        // gutter but not the knob — so `style_padding_left: md` would leave
        // every self-painting band, the stats marquee and the hero stage inset
        // from the viewport edge by exactly the padding chosen. `content_inset`
        // acts inside `.sx-section` and can never do that. The roadmap says
        // "per-side padding"; this is the deliberate reading of it.
        //
        // Border and radius paint the SAME box the background does, which is
        // why the wrapper's bleed had to be fixed first (c43e9a7).
        'style_padding_top',
        'style_padding_top_md',
        'style_padding_top_lg',
        'style_padding_bottom',
        'style_padding_bottom_md',
        'style_padding_bottom_lg',
        // Holds a palette NAME, so it must also be in PaletteUsage::KEYS or
        // deleting that colour goes unblocked and the border silently vanishes.
        // PaletteDeletionGuardTest pins the two lists as a subset relation.
        'style_border_color',
        'style_border_width',
        'style_radius',
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
     * Knobs that no longer have a control, but whose stored values must still
     * be classified as presentation.
     *
     * REMOVING A KEY FROM KEYS IS NOT ENOUGH, and this list exists because
     * that assumption was caught doing damage. Retiring `extra_padding` left
     * its value sitting in 13 rows of stored JSON; the moment the key stopped
     * being a presentation key, SectionContent::hasContent() began counting it
     * as authored copy. An untouched scaffold section that an operator had
     * once nudged the padding on flipped from has_content:false to true — so
     * an EMPTY section would have rendered onto a live page, which is the
     * exact failure the flag was built to prevent. Measured: one section on
     * the Atlas bench flipped, and it was the deliberately-empty one.
     *
     * A retired key therefore stays here permanently, or until the values are
     * cleaned out of every row. Cheap insurance: the list only ever grows by
     * a line, and the alternative is a silent content leak that nothing else
     * in the stack can catch.
     *
     * @var list<string>
     */
    public const RETIRED_KEYS = [
        // Replaced by style_padding_top / style_padding_bottom, which can say
        // "generous above, tight below" — a thing one shared token could not.
        'extra_padding',
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
