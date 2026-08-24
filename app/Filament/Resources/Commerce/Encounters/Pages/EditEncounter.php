<?php

namespace App\Filament\Resources\Commerce\Encounters\Pages;

use App\Filament\Resources\Commerce\Encounters\EncounterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEncounter extends EditRecord
{
    protected static string $resource = EncounterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
