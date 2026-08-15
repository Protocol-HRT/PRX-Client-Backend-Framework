<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApiClient extends CreateRecord
{
    protected static string $resource = ApiClientResource::class;
}
