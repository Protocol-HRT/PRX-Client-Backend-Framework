<?php

namespace App\Services\Cms;

use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves section image values to a frontend-ready media object.
 *
 * Dual-read: canonical values are curator Media ids (int), but legacy rows
 * still hold plain storage path strings — both resolve, so no data migration
 * is required before shipping (cms:backfill-section-media is optional hygiene).
 *
 * Call prime() with every id collected from a payload first, so resolution
 * is one query per request instead of one per field.
 */
class MediaResolver
{
    /** @var array<int, Media> */
    private array $primed = [];

    /**
     * @param  iterable<int>  $ids
     */
    public function prime(iterable $ids): void
    {
        $missing = array_diff(array_unique([...$ids]), array_keys($this->primed));

        if ($missing === []) {
            return;
        }

        foreach (Media::query()->whereIn('id', $missing)->get() as $media) {
            $this->primed[$media->id] = $media;
        }
    }

    /**
     * @return array{id: int|null, url: string, alt: string|null, width: int|null, height: int|null}|null
     */
    public function resolve(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_numeric($value)) {
            $media = $this->primed[(int) $value] ?? Media::query()->find((int) $value);

            if ($media === null) {
                return null;
            }

            $this->primed[$media->id] = $media;

            return [
                'id' => $media->id,
                'url' => $media->url,
                'alt' => $media->alt,
                'width' => $media->width !== null ? (int) $media->width : null,
                'height' => $media->height !== null ? (int) $media->height : null,
            ];
        }

        if (is_string($value)) {
            return [
                'id' => null,
                'url' => Storage::disk('public')->url(ltrim($value, '/')),
                'alt' => null,
                'width' => null,
                'height' => null,
            ];
        }

        return null;
    }
}
