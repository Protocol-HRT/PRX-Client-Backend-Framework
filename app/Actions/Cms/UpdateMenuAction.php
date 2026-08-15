<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\MenuData;
use App\Models\Cms\Menu;

class UpdateMenuAction
{
    use Transacts;

    public function execute(Menu $menu, MenuData $data): Menu
    {
        return $this->tx(function () use ($menu, $data) {
            // The slug is immutable after creation: it is the stable identifier
            // the frontend requests the menu by.
            $menu->update([
                'name' => $data->name,
                'description' => $data->description,
            ]);

            return $menu->fresh();
        });
    }
}
