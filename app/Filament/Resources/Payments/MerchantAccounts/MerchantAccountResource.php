<?php

namespace App\Filament\Resources\Payments\MerchantAccounts;

use App\Filament\Resources\Payments\MerchantAccounts\Pages\CreateMerchantAccount;
use App\Filament\Resources\Payments\MerchantAccounts\Pages\EditMerchantAccount;
use App\Filament\Resources\Payments\MerchantAccounts\Pages\ListMerchantAccounts;
use App\Filament\Resources\Payments\MerchantAccounts\Schemas\MerchantAccountForm;
use App\Filament\Resources\Payments\MerchantAccounts\Tables\MerchantAccountsTable;
use App\Models\Payments\MerchantAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class MerchantAccountResource extends Resource
{
    protected static ?string $model = MerchantAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MerchantAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchantAccounts::route('/'),
            'create' => CreateMerchantAccount::route('/create'),
            'edit' => EditMerchantAccount::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
