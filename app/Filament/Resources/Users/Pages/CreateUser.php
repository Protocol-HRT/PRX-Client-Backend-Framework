<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Stamp invited_at on creation. The admin who creates the user is
     * effectively "inviting" them — even if we hand them the password
     * directly today, this gives us a hook for an actual invitation
     * email later (Filament has built-in password-reset routes
     * configured on the panel).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['invited_at'] ??= now();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
