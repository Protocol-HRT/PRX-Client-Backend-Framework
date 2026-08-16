<?php

namespace App\Filament\Resources\Catalog\ProductTypes;

use App\Filament\Resources\Catalog\ProductTypes\Pages\CreateProductType;
use App\Filament\Resources\Catalog\ProductTypes\Pages\EditProductType;
use App\Filament\Resources\Catalog\ProductTypes\Pages\ListProductTypes;
use App\Filament\Resources\Catalog\ProductTypes\Schemas\ProductTypeForm;
use App\Filament\Resources\Catalog\ProductTypes\Tables\ProductTypesTable;
use App\Models\Catalog\ProductType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProductTypeResource extends Resource
{
    protected static ?string $model = ProductType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Product Types';

    protected static ?int $navigationSort = 62;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTypes::route('/'),
            'create' => CreateProductType::route('/create'),
            'edit' => EditProductType::route('/{record}/edit'),
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
