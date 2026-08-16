<?php

namespace App\Filament\Resources\Catalog\MeasurementUnits;

use App\Filament\Resources\Catalog\MeasurementUnits\Pages\CreateMeasurementUnit;
use App\Filament\Resources\Catalog\MeasurementUnits\Pages\EditMeasurementUnit;
use App\Filament\Resources\Catalog\MeasurementUnits\Pages\ListMeasurementUnits;
use App\Filament\Resources\Catalog\MeasurementUnits\Schemas\MeasurementUnitForm;
use App\Filament\Resources\Catalog\MeasurementUnits\Tables\MeasurementUnitsTable;
use App\Models\Catalog\MeasurementUnit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class MeasurementUnitResource extends Resource
{
    protected static ?string $model = MeasurementUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Measurement Units';

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MeasurementUnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeasurementUnitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeasurementUnits::route('/'),
            'create' => CreateMeasurementUnit::route('/create'),
            'edit' => EditMeasurementUnit::route('/{record}/edit'),
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
