<?php

namespace App\Filament\Resources\Commerce\FulfillmentCenters\Pages;

use App\Filament\Resources\Commerce\FulfillmentCenters\FulfillmentCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentCenters extends ListRecords
{
    protected static string $resource = FulfillmentCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
