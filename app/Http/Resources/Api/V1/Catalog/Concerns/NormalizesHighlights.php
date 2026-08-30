<?php

namespace App\Http\Resources\Api\V1\Catalog\Concerns;

/**
 * Normalizes the `highlights` column into the stable API contract: a flat
 * list of strings.
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
 * touch the production catalog to fix a two-line display bug, and the
 * repeater keeps writing its own shape regardless.
 *
 * Covered by ProductEndpointTest's highlights cases.
 */
trait NormalizesHighlights
{
    /**
     * @param  array<int, array<string, string>|string>|null  $highlights
     * @return list<string>
     */
    private function normalizeHighlights(?array $highlights): array
    {
        if (empty($highlights)) {
            return [];
        }

        return collect($highlights)
            ->map(fn ($entry) => is_array($entry) ? ($entry['item'] ?? null) : $entry)
            ->map(fn ($entry) => is_string($entry) ? trim($entry) : null)
            ->filter()
            ->values()
            ->all();
    }
}
