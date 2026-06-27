<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    /**
     * No create button — leads are created from the public site, not in admin.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
