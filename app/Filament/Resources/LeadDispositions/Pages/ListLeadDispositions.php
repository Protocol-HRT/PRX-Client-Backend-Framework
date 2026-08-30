<?php

namespace App\Filament\Resources\LeadDispositions\Pages;

use App\Filament\Resources\LeadDispositions\LeadDispositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeadDispositions extends ListRecords
{
    protected static string $resource = LeadDispositionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
