<?php

namespace App\Filament\Resources\Integrations\Pages;

use App\Filament\Resources\Integrations\IntegrationInstanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntegrationInstances extends ListRecords
{
    protected static string $resource = IntegrationInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
