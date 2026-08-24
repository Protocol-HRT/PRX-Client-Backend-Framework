<?php

namespace App\Cms\Support;

/**
 * The "Layout & spacing" knobs every section type carries.
 *
 * These are written into a section's own `data` payload alongside its content
 * fields, but they are presentation, not content — a section holding nothing
 * but these was never authored. Named here rather than in the form builder so
 * the API layer can consult the list without depending on Filament.
 */
final class LayoutFields
{
    /** @var list<string> */
    public const KEYS = [
        'extra_padding',
        'content_inset',
        'content_width',
        'content_align',
    ];
}
