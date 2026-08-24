<?php

namespace App\Filament\Resources\Commerce\Orders\Pages;

use App\Filament\Resources\Commerce\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** Orders originate from PRX webhooks; no manual create. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
