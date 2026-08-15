<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Models\Cms\Menu;

class DeleteMenuAction
{
    use Transacts;

    public function execute(Menu $menu): void
    {
        $this->tx(function () use ($menu): void {
            // Items (and their nested children) cascade at the database level.
            $menu->delete();
        });
    }
}
