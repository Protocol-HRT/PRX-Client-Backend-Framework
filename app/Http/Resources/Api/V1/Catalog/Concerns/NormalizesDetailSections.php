<?php

namespace App\Http\Resources\Api\V1\Catalog\Concerns;

/**
 * Normalizes the Filament Repeater detail_sections shape into the stable
 * API contract: [{title, placement, content}], dropping malformed rows.
 */
trait NormalizesDetailSections
{
    /**
     * @param  array<int, array<string, string>>|null  $sections
     * @return list<array{title: string, placement: string, content: string}>
     */
    private function normalizeDetailSections(?array $sections): array
    {
        if (empty($sections)) {
            return [];
        }

        return collect($sections)
            ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null) && filled($row['content'] ?? null))
            ->map(fn (array $row) => [
                'title' => $row['title'],
                'placement' => in_array($row['placement'] ?? null, ['accordion', 'tab'], true) ? $row['placement'] : 'accordion',
                'content' => $row['content'],
            ])
            ->values()
            ->all();
    }
}
