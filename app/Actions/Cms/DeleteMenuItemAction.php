<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Models\Cms\MenuItem;

class DeleteMenuItemAction
{
    use Transacts;

    public function execute(MenuItem $menuItem): void
    {
        $this->tx(function () use ($menuItem): void {
            // Nested children cascade at the database level.
            $menuItem->delete();
        });
    }
}
