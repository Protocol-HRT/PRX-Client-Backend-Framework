<?php

namespace App\Filament\Resources\Commerce\FulfillmentCenters;

use App\Filament\Resources\Commerce\FulfillmentCenters\Pages\CreateFulfillmentCenter;
use App\Filament\Resources\Commerce\FulfillmentCenters\Pages\EditFulfillmentCenter;
use App\Filament\Resources\Commerce\FulfillmentCenters\Pages\ListFulfillmentCenters;
use App\Filament\Resources\Commerce\FulfillmentCenters\Schemas\FulfillmentCenterForm;
use App\Filament\Resources\Commerce\FulfillmentCenters\Tables\FulfillmentCentersTable;
use App\Models\Commerce\FulfillmentCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FulfillmentCenterResource extends Resource
{
    protected static ?string $model = FulfillmentCenter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return FulfillmentCenterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FulfillmentCentersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFulfillmentCenters::route('/'),
            'create' => CreateFulfillmentCenter::route('/create'),
            'edit' => EditFulfillmentCenter::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
