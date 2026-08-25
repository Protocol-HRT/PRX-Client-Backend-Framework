<?php

namespace App\Cms\Support;

/**
 * Per-block-type design defaults for the shared layout/style knobs, the
 * block-level twin of LayoutDefaults.
 *
 * SEPARATE TABLE, deliberately. LayoutDefaults::MAP is keyed by SECTION
 * slug, and block slugs share that namespace only by accident — `testimonial`
 * (a block) and `testimonials` (a section) are unrelated, and a single map
 * would let one silently inherit the other's widths the day someone names a
 * block after a section. Same tokens, same merge rules, different key space.
 *
 * Only LayoutFields::KEYS are merged (applyDefaults enforces it), so a
 * default can never smuggle content into a child and defeat has_content.
 */
final class BlockDefaults
{
    /**
     * block slug => layout knob defaults.
     *
     * @var array<string, array<string, string>>
     */
    private const MAP = [
        // The hero's highlight card is a fixed-width overlay panel, not a
        // page-width band: it should keep whatever width its own markup
        // gives it rather than being capped by the block width scale.
        'testimonial' => ['content_width' => 'full'],
    ];

    /**
     * @return array<string, string>
     */
    public static function for(string $type): array
    {
        return self::MAP[$type] ?? [];
    }
}
