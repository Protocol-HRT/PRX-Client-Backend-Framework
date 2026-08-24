<?php

namespace App\Cms\Support;

/**
 * Per-type design defaults for the layout knobs.
 *
 * These are the widths the theme's SCSS used to hardcode per section. Moving
 * them here is the point: the value an operator sees and can override lives
 * in the CMS payload, not in a stylesheet they will never open.
 *
 * MEASURE WHAT RENDERS, NOT WHAT THE PARTIAL SAYS. A frontend may override a
 * section's own max-width somewhere else in its cascade, in which case the
 * value written next to the section is dead and copying it here silently
 * resizes the section. Every default below was taken from the width actually
 * in effect.
 *
 * SEMANTIC TOKENS, NOT PIXELS. `wide` resolves to whatever the consuming
 * theme decides it measures; prx-backend ships to more than one frontend, so
 * a measurement belonging to any one of them may not appear in this repo's
 * code. The frontend owns the token => px map and can retune the whole scale
 * without a content edit or an admin change.
 *
 * ONE TABLE RATHER THAN A METHOD PER BLUEPRINT, deliberately: the seeded
 * shadow types in SectionTypeSeeder must carry byte-identical defaults to
 * their code blueprints (SectionTypeSeedParityTest), and both read this map,
 * so the two cannot drift. A blueprint with an unusual need may still
 * override layoutDefaults() directly.
 *
 * Types absent from the map declare no default: every knob stays null and the
 * frontend's own CSS decides, exactly as before.
 */
final class LayoutDefaults
{
    /**
     * type slug => layout knob defaults.
     *
     * @var array<string, array<string, string>>
     */
    private const MAP = [
        // Uncapped. These span the full frame, which is what they ALREADY
        // rendered: the theme lifted their stylesheet max-widths so section
        // content would line up uniformly, making the value written in each
        // partial dead. Reading a default off a dead value would silently
        // narrow the section — see the note above about measuring what is on
        // screen, not what the stylesheet says.
        'hero' => ['content_width' => 'full', 'media_width' => 'contained'],
        'stats-marquee' => ['content_width' => 'full'],
        'how-it-works' => ['content_width' => 'full'],
        'physicians' => ['content_width' => 'full'],
        'image-text-split' => ['content_width' => 'full'],
        'timeline' => ['content_width' => 'full'],
        'product-slider' => ['content_width' => 'full'],
        'image-callout-banner' => ['content_width' => 'full', 'media_width' => 'contained'],

        // The theme's dominant column, and genuinely capped there today.
        'testimonials' => ['content_width' => 'xwide'],
        'faq' => ['content_width' => 'xwide'],
        'final-cta' => ['content_width' => 'xwide'],
        'cta-banner' => ['content_width' => 'xwide'],
        'results-stats' => ['content_width' => 'xwide'],
        'story' => ['content_width' => 'xwide'],
        'benefits-him' => ['content_width' => 'xwide'],
        'benefits-her' => ['content_width' => 'xwide'],
        'category-grid' => ['content_width' => 'xwide'],

        // Was `full`, read off the section's inner column. But the element an
        // operator perceives as this section's width is the card wrapping it,
        // which carried its own cap — so `full` described something invisible.
        // `xwide` is the width that card previously hardcoded: unchanged on
        // screen, except the knob owns it now and `full` can genuinely widen.
        'package-slider' => ['content_width' => 'xwide', 'media_width' => 'contained'],

        // Narrower editorial columns.
        'text-block' => ['content_width' => 'wide'],
        'faq-categories' => ['content_width' => 'wide'],
        'highlight-banner' => ['content_width' => 'medium'],
        'benefits-diagram' => ['content_width' => 'medium'],
        'video-embed' => ['content_width' => 'narrow'],

        // Types with no frontend component yet; values are inert until one
        // exists and are a starting point, not a measured theme value.
        'features-grid' => ['content_width' => 'xwide'],
        'product-grid' => ['content_width' => 'xwide'],
        'pricing-tiers' => ['content_width' => 'xwide'],
        'package-pricing-comparison' => ['content_width' => 'xwide'],
        'transformed' => ['content_width' => 'xwide'],
        'product-callout' => ['content_width' => 'medium'],
    ];

    /**
     * @return array<string, string>
     */
    public static function for(string $type): array
    {
        return self::MAP[$type] ?? [];
    }

    /**
     * The whole table, so a test can assert every entry names a real knob and
     * a real token. Nothing validates these at runtime: an unknown key is
     * dropped by LayoutFields::applyDefaults and an unknown value is dropped
     * by the frontend's allow-list, so a typo is silently inert either way.
     *
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return self::MAP;
    }
}
