<?php

namespace App\Filament\Resources\Catalog\AdministrationMethods;

use App\Filament\Resources\Catalog\AdministrationMethods\Pages\CreateAdministrationMethod;
use App\Filament\Resources\Catalog\AdministrationMethods\Pages\EditAdministrationMethod;
use App\Filament\Resources\Catalog\AdministrationMethods\Pages\ListAdministrationMethods;
use App\Filament\Resources\Catalog\AdministrationMethods\Schemas\AdministrationMethodForm;
use App\Filament\Resources\Catalog\AdministrationMethods\Tables\AdministrationMethodsTable;
use App\Models\Catalog\AdministrationMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AdministrationMethodResource extends Resource
{
    protected static ?string $model = AdministrationMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Administration Methods';

    protected static ?int $navigationSort = 66;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AdministrationMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdministrationMethodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdministrationMethods::route('/'),
            'create' => CreateAdministrationMethod::route('/create'),
            'edit' => EditAdministrationMethod::route('/{record}/edit'),
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
