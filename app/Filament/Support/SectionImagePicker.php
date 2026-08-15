<?php

namespace App\Filament\Support;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Curator picker preconfigured for section `data` image fields.
 *
 * Sections historically stored images as plain storage paths. The stock
 * CuratorPicker hydrates an unknown string to an empty state and dehydrates
 * empty to null — silently wiping legacy paths on the next save. This factory
 * resolves legacy path strings to their Media row at hydration time instead,
 * so old sections show their image and auto-migrate to a media ID on save.
 * Paths with no matching Media row are handled by cms:backfill-section-media.
 */
class SectionImagePicker
{
    public static function make(string $name): CuratorPicker
    {
        return CuratorPicker::make($name)
            ->constrained()
            ->afterStateHydrated(static function (CuratorPicker $component, mixed $state): void {
                if (is_string($state) && $state !== '' && ! is_numeric($state)) {
                    $state = self::resolveLegacyPath($state);
                }

                // Mirror CuratorPicker's stock hydration (uuid-keyed item arrays).
                if (blank($state)) {
                    $component->state([]);

                    return;
                }

                $items = [];

                foreach (get_media_items(Arr::wrap($state)) as $itemData) {
                    $items[(string) Str::uuid()] = $itemData instanceof Media ? $itemData->toArray() : $itemData;
                }

                $component->state($items);
            });
    }

    /**
     * Match a legacy storage path (with or without a /storage/ URL prefix)
     * to an existing Media row. Null when nothing matches.
     */
    public static function resolveLegacyPath(string $path): ?int
    {
        $normalized = Str::of($path)
            ->after('/storage/')
            ->ltrim('/')
            ->toString();

        return Media::query()
            ->where('path', $normalized)
            ->orWhere('path', ltrim($path, '/'))
            ->value('id');
    }
}
