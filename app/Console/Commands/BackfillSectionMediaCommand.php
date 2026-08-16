<?php

namespace App\Console\Commands;

use App\Models\Catalog\CatalogItemSection;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\SectionRegistry;
use Awcodes\Curator\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rewrites legacy image path strings in section `data` payloads to curator
 * media ids — the canonical stored value since the CuratorPicker migration.
 *
 * For each image-kind field (per the blueprint's fieldKinds()) holding a
 * string path: match it to an existing curator row by path, or create a Media
 * row for the file if it exists on the public disk, then store the id.
 * Unresolvable paths are left untouched (the API dual-reads them) and listed.
 */
class BackfillSectionMediaCommand extends Command
{
    protected $signature = 'cms:backfill-section-media {--dry-run : Report what would change without writing}';

    protected $description = 'Convert legacy image path strings in page section data to curator media ids';

    private int $converted = 0;

    /** @var list<string> */
    private array $unresolved = [];

    public function handle(SectionRegistry $registry): int
    {
        $dry = (bool) $this->option('dry-run');

        $sections = PageSection::query()->whereNotNull('data')->get()
            ->concat(CatalogItemSection::query()->whereNotNull('data')->get());

        foreach ($sections as $section) {
            $definition = $registry->resolve($section->type);

            if ($definition === null) {
                continue;
            }

            $data = $section->data;
            $changed = false;

            foreach ($definition->fieldKinds() as $path => $kind) {
                if ($kind !== 'image') {
                    continue;
                }

                $this->walk($data, explode('.', $path), function (mixed $value) use (&$changed, $dry): mixed {
                    if (! is_string($value) || $value === '' || is_numeric($value)) {
                        return $value;
                    }

                    $id = $this->resolveOrCreateMedia($value, $dry);

                    if ($id === null) {
                        $this->unresolved[] = $value;

                        return $value;
                    }

                    $changed = true;
                    $this->converted++;

                    return $id;
                });
            }

            if ($changed && ! $dry) {
                DB::transaction(function () use ($section, $data) {
                    $section->update(['data' => $data]);
                });
            }
        }

        $this->backfillTitleBanners($dry);

        $label = $dry ? 'would be converted' : 'converted';
        $this->info("{$this->converted} image value(s) {$label} to media ids.");

        foreach (array_unique($this->unresolved) as $path) {
            $this->warn("Unresolved (no curator row, file missing on public disk): {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * Pages store one image outside section data: title_banner.background_image.
     * Same legacy-path→media-id conversion so the admin picker can hydrate it.
     */
    private function backfillTitleBanners(bool $dry): void
    {
        foreach (Page::query()->whereNotNull('title_banner')->get() as $page) {
            $banner = $page->title_banner;
            $value = $banner['background_image'] ?? null;

            if (! is_string($value) || $value === '' || is_numeric($value)) {
                continue;
            }

            $id = $this->resolveOrCreateMedia($value, $dry);

            if ($id === null) {
                $this->unresolved[] = $value;

                continue;
            }

            $this->converted++;

            if (! $dry) {
                $banner['background_image'] = $id;
                $page->update(['title_banner' => $banner]);
            }
        }
    }

    private function resolveOrCreateMedia(string $value, bool $dry): ?int
    {
        $normalized = Str::of($value)->after('/storage/')->ltrim('/')->toString();

        $existing = Media::query()
            ->where('path', $normalized)
            ->orWhere('path', ltrim($value, '/'))
            ->value('id');

        if ($existing !== null) {
            return $existing;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($normalized)) {
            return null;
        }

        if ($dry) {
            // Signal convertibility without creating anything.
            return 0;
        }

        [$width, $height] = @getimagesize($disk->path($normalized)) ?: [null, null];

        $media = Media::query()->create([
            'disk' => 'public',
            'directory' => Str::beforeLast($normalized, '/'),
            'visibility' => 'public',
            'name' => Str::of($normalized)->afterLast('/')->beforeLast('.')->toString(),
            'path' => $normalized,
            'width' => $width,
            'height' => $height,
            'size' => $disk->size($normalized),
            'type' => $disk->mimeType($normalized) ?: 'image/jpeg',
            'ext' => Str::afterLast($normalized, '.'),
        ]);

        return $media->id;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $segments
     */
    private function walk(array &$data, array $segments, callable $fn): void
    {
        $key = array_shift($segments);

        if ($key === '*') {
            foreach ($data as &$item) {
                if ($segments === []) {
                    $item = $fn($item);
                } elseif (is_array($item)) {
                    $this->walk($item, $segments, $fn);
                }
            }

            return;
        }

        if ($segments === []) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $fn($data[$key]);
            }

            return;
        }

        if (isset($data[$key]) && is_array($data[$key])) {
            $this->walk($data[$key], $segments, $fn);
        }
    }
}
