<?php

namespace App\Filament\Resources\Payments\MerchantAccounts\Pages;

use App\Filament\Resources\Payments\MerchantAccounts\MerchantAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchantAccount extends EditRecord
{
    protected static string $resource = MerchantAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
