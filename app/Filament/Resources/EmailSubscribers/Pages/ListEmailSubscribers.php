<?php

namespace App\Filament\Resources\EmailSubscribers\Pages;

use App\Filament\Resources\EmailSubscribers\EmailSubscriberResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailSubscribers extends ListRecords
{
    protected static string $resource = EmailSubscriberResource::class;

    /** Subscribers come from the public site, not the admin. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
