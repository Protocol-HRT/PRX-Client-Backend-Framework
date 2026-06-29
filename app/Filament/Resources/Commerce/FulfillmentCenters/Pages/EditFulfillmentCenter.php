<?php

namespace App\Filament\Resources\Commerce\FulfillmentCenters\Pages;

use App\Filament\Resources\Commerce\FulfillmentCenters\FulfillmentCenterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFulfillmentCenter extends EditRecord
{
    protected static string $resource = FulfillmentCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
