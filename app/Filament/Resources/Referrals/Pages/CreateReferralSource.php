<?php

namespace App\Filament\Resources\Referrals\Pages;

use App\Filament\Resources\Referrals\ReferralSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReferralSource extends CreateRecord
{
    protected static string $resource = ReferralSourceResource::class;

    /**
     * Straight to the edit page, because the source is only half the job — the
     * operator still needs to mint a code, and that lives on the edit page's
     * Links panel.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
