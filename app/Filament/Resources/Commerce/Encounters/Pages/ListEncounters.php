<?php

namespace App\Filament\Resources\Commerce\Encounters\Pages;

use App\Filament\Resources\Commerce\Encounters\EncounterResource;
use Filament\Resources\Pages\ListRecords;

class ListEncounters extends ListRecords
{
    protected static string $resource = EncounterResource::class;

    /** Encounters originate from PRX webhooks; no manual create. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
