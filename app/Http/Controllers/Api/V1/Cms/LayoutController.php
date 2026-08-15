<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Enums\Cms\Region;
use App\Enums\Cms\RegionItemKind;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Cms\RegionItem;
use App\Services\Cms\CmsCache;
use App\Services\Cms\MenuTreeBuilder;
use App\Services\Cms\SectionDataTransformer;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/layout — global site chrome for every page load.
 */
class LayoutController extends ApiController
{
    public function __construct(
        private readonly CmsCache $cache,
        private readonly SectionDataTransformer $sections,
        private readonly MenuTreeBuilder $trees,
    ) {}

    /**
     * Get the global layout regions.
     *
     * Returns every fixed region (top_bar, header, pre_footer, footer,
     * sidebar_left, sidebar_right — all keys always present) with its ordered
     * items. Each item is `{kind: "section", section: {...}}` (same envelope
     * as page sections, including inlined media/catalog data) or
     * `{kind: "menu", menu: {name, slug, items: [...]}}`. Global blocks are
     * emitted as sections with a `global` reference. Fetch once per app load
     * alongside the page payload. Cached; invalidated instantly on save.
     *
     * @tags CMS
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        $payload = $this->cache->remember('layout', function (): array {
            $items = RegionItem::query()
                ->where('enabled', true)
                ->with(['globalSection', 'menu'])
                ->orderBy('position')
                ->get()
                ->groupBy(fn (RegionItem $item): string => $item->region->value);

            $regions = [];

            foreach (Region::cases() as $region) {
                $regions[$region->value] = ($items->get($region->value) ?? collect())
                    ->map(fn (RegionItem $item): ?array => $this->buildItem($item))
                    ->filter()
                    ->values()
                    ->all();
            }

            return ['regions' => $regions];
        });

        return $this->success($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildItem(RegionItem $item): ?array
    {
        if ($item->kind === RegionItemKind::Menu) {
            if ($item->menu === null) {
                return null;
            }

            return [
                'kind' => 'menu',
                'menu' => [
                    'name' => $item->menu->name,
                    'slug' => $item->menu->slug,
                    'items' => $this->trees->build($item->menu),
                ],
            ];
        }

        $section = $item->kind === RegionItemKind::GlobalSection
            ? $this->sections->envelopeFor(null, null, $item->globalSection)
            : $this->sections->envelopeFor($item->section_type, $item->data);

        if ($section === null) {
            return null;
        }

        return [
            'kind' => 'section',
            'section' => $section,
        ];
    }
}
