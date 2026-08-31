<?php

namespace App\Http\Resources\Api\V1\Catalog\Concerns;

/**
 * Normalizes the `highlights` column into the stable API contract:
 * `[{text, icon}]`, icon being a Tabler class or null.
 *
 * TWO SHAPES ARE STORED, and both are real. The Filament Repeater authors
 * `[{"item": "text"}]`, but the fill scripts wrote a bare `["text", ...]`
 * and those rows are still live. The original implementation was a plain
 * `pluck('item')`, which returns null for a string entry and then filtered
 * it away — so a product authored before the repeater existed served
 * `highlights: []` while its content sat intact in the database. That was
 * indistinguishable from "the operator never wrote any", and it is why
 * `performance-stack` (4 stored highlights) rendered nothing on a page whose
 * component was working perfectly.
 *
 * Read-side normalization on purpose: a data migration to one shape would
 * touch the production catalog to fix a display bug, and the repeater keeps
 * writing its own shape regardless.
 *
 * The shape gained an icon when highlights became the credibility list under
 * the buy box — an icon list needs an icon per row, and the repeater stored a
 * bare string with nothing to draw. Rows authored before the field exists
 * carry `icon: null` and the frontend falls back to a default mark, so adding
 * the field did not require touching a single record.
 *
 * Covered by ProductEndpointTest's highlights cases.
 */
trait NormalizesHighlights
{
    /**
     * @param  array<int, array<string, string>|string>|null  $highlights
     * @return list<array{text: string, icon: string|null}>
     */
    private function normalizeHighlights(?array $highlights): array
    {
        if (empty($highlights)) {
            return [];
        }

        return collect($highlights)
            ->map(function ($entry): ?array {
                // Three shapes reach here. The current repeater writes
                // {item, icon}; an older one wrote {item} alone; the fill
                // scripts wrote a bare string. All three are live.
                if (is_string($entry)) {
                    $text = trim($entry);
                    $icon = null;
                } elseif (is_array($entry)) {
                    $text = is_string($entry['item'] ?? null) ? trim($entry['item']) : '';
                    $icon = is_string($entry['icon'] ?? null) ? trim($entry['icon']) : '';
                    $icon = $icon === '' ? null : $icon;
                } else {
                    return null;
                }

                return $text === '' ? null : ['text' => $text, 'icon' => $icon];
            })
            ->filter()
            ->values()
            ->all();
    }
}
