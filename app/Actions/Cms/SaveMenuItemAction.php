<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\MenuItemData;
use App\Enums\Cms\MenuLinkType;
use App\Models\Cms\MenuItem;
use InvalidArgumentException;

/**
 * Creates or updates a menu item. Owns all tree integrity rules: link target
 * coherence, cross-menu parenting, cycle prevention, and the configured
 * maximum nesting depth.
 */
class SaveMenuItemAction
{
    use Transacts;

    public function execute(MenuItemData $data, ?MenuItem $existing = null): MenuItem
    {
        return $this->tx(function () use ($data, $existing) {
            $type = MenuLinkType::tryFrom($data->link_type);

            if ($type === null) {
                throw new InvalidArgumentException("Unknown link type \"{$data->link_type}\".");
            }

            if ($type->isEntity() && $data->linkable_id === null) {
                throw new InvalidArgumentException("A \"{$type->label()}\" link must reference a target record.");
            }

            if (! $type->isEntity() && blank($data->url)) {
                throw new InvalidArgumentException("A \"{$type->label()}\" link requires a URL.");
            }

            $parent = $this->resolveParent($data, $existing);

            $this->guardDepth($parent, $existing);

            $attributes = [
                'menu_id' => $data->menu_id,
                'parent_id' => $parent?->id,
                'label' => $data->label,
                'link_type' => $type,
                'target' => $data->target,
                'icon' => $data->icon,
                'badge' => $data->badge,
                'enabled' => $data->enabled,
            ];

            if ($type->isEntity()) {
                $attributes['linkable_type'] = (new ($type->modelClass()))->getMorphClass();
                $attributes['linkable_id'] = $data->linkable_id;
                $attributes['url'] = null;
            } else {
                $attributes['linkable_type'] = null;
                $attributes['linkable_id'] = null;
                $attributes['url'] = $data->url;
            }

            if ($existing !== null) {
                $existing->update($attributes);

                return $existing->fresh();
            }

            return MenuItem::create($attributes);
        });
    }

    private function resolveParent(MenuItemData $data, ?MenuItem $existing): ?MenuItem
    {
        if ($data->parent_id === null) {
            return null;
        }

        $parent = MenuItem::query()->find($data->parent_id);

        if ($parent === null) {
            throw new InvalidArgumentException('The selected parent item no longer exists.');
        }

        if ($parent->menu_id !== $data->menu_id) {
            throw new InvalidArgumentException('The selected parent item belongs to a different menu.');
        }

        if ($existing !== null && ($parent->id === $existing->id || $this->isWithinSubtree($parent, $existing))) {
            throw new InvalidArgumentException('An item cannot be nested under itself or one of its own children.');
        }

        return $parent;
    }

    /**
     * Whether $candidate sits inside $root's subtree (walks candidate's
     * ancestor chain — menu trees are small).
     */
    private function isWithinSubtree(MenuItem $candidate, MenuItem $root): bool
    {
        $current = $candidate->parent;

        while ($current !== null) {
            if ($current->id === $root->id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    private function guardDepth(?MenuItem $parent, ?MenuItem $existing): void
    {
        $maxDepth = (int) config('cms.menu.max_depth', 3);

        $newDepth = $parent === null ? 1 : $parent->depth() + 1;

        // On update the item may carry a subtree; its deepest descendant must
        // also stay within the limit after the move.
        $subtreeDepth = $existing === null ? 1 : $this->subtreeDepth($existing);

        if (($newDepth + $subtreeDepth - 1) > $maxDepth) {
            throw new InvalidArgumentException("Menus support a maximum nesting depth of {$maxDepth} levels.");
        }
    }

    /**
     * Depth of the item's subtree relative to itself (1 = no children).
     */
    private function subtreeDepth(MenuItem $item): int
    {
        $max = 1;

        foreach ($item->children as $child) {
            $max = max($max, 1 + $this->subtreeDepth($child));
        }

        return $max;
    }
}
