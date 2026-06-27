<?php

namespace App\Filament\Resources\Payments\MerchantAccounts\Pages;

use App\Filament\Resources\Payments\MerchantAccounts\MerchantAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMerchantAccounts extends ListRecords
{
    protected static string $resource = MerchantAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
