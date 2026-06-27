<?php

namespace App\Filament\Resources\Payments\MerchantAccounts\Pages;

use App\Filament\Resources\Payments\MerchantAccounts\MerchantAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantAccount extends CreateRecord
{
    protected static string $resource = MerchantAccountResource::class;
}
