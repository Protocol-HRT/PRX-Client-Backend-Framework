<?php

namespace App\Actions\Pages;

use App\Actions\Concerns\Transacts;
use App\Models\Page;

class DeletePageAction
{
    use Transacts;

    public function execute(Page $page): void
    {
        $this->tx(function () use ($page) {
            $page->delete();
        });
    }
}
