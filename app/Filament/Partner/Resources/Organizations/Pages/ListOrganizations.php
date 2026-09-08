<?php

namespace App\Filament\Partner\Resources\Organizations\Pages;

use App\Filament\Partner\Resources\Organizations\OrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New sub-organisation')];
    }
}
