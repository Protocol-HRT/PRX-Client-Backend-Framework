<?php

namespace App\Services\Cms;

use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use Illuminate\Support\Collection;

/**
 * Assembles a menu's nested item tree for the API in one query.
 *
 * Entity links (page/product/category/…) are stored as morph references and
 * resolved to their CURRENT slug here — renames propagate automatically, and
 * items whose target is deleted or unpublished are dropped from the payload
 * (as are their children).
 */
class MenuTreeBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(Menu $menu): array
    {
        $items = $menu->items()
            ->where('enabled', true)
            ->with('linkable')
            ->orderBy('position')
            ->get();

        $byParent = $items->groupBy(fn (MenuItem $item) => $item->parent_id ?? 0);

        return $this->buildLevel($byParent, 0);
    }

    /**
     * @param  Collection<int|string, Collection<int, MenuItem>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function buildLevel($byParent, int|string $parentKey): array
    {
        $level = [];

        foreach ($byParent->get($parentKey, collect()) as $item) {
            $link = $this->resolveLink($item);

            if ($link === null) {
                continue;
            }

            $level[] = [
                'id' => $item->id,
                'label' => $item->label,
                'link' => $link,
                'target' => $item->target,
                'icon' => $item->icon,
                'badge' => $item->badge,
                'children' => $this->buildLevel($byParent, $item->id),
            ];
        }

        return $level;
    }

    /**
     * @return array<string, mixed>|null null = dead target, drop the item
     */
    private function resolveLink(MenuItem $item): ?array
    {
        $type = $item->link_type;

        if (! $type->isEntity()) {
            return [
                'type' => $type->value,
                'url' => $item->url,
            ];
        }

        $target = $item->linkable;

        if ($target === null || ! $type->targetIsAvailable($target)) {
            return null;
        }

        return [
            'type' => $type->value,
            'slug' => $target->{$target->getRouteKeyName()},
        ];
    }
}
