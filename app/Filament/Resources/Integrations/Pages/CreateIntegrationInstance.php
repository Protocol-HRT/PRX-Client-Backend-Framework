<?php

namespace App\Filament\Resources\Integrations\Pages;

use App\Filament\Resources\Integrations\IntegrationInstanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegrationInstance extends CreateRecord
{
    protected static string $resource = IntegrationInstanceResource::class;
}
