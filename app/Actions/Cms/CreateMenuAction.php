<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\MenuData;
use App\Models\Cms\Menu;

class CreateMenuAction
{
    use Transacts;

    public function execute(MenuData $data): Menu
    {
        return $this->tx(fn () => Menu::create([
            'name' => $data->name,
            'slug' => $data->slug,
            'description' => $data->description,
        ]));
    }
}
