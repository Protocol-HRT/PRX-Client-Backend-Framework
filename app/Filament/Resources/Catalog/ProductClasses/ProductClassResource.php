<?php

namespace App\Filament\Resources\Catalog\ProductClasses;

use App\Filament\Resources\Catalog\ProductClasses\Pages\CreateProductClass;
use App\Filament\Resources\Catalog\ProductClasses\Pages\EditProductClass;
use App\Filament\Resources\Catalog\ProductClasses\Pages\ListProductClasses;
use App\Filament\Resources\Catalog\ProductClasses\Schemas\ProductClassForm;
use App\Filament\Resources\Catalog\ProductClasses\Tables\ProductClassesTable;
use App\Models\Catalog\ProductClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProductClassResource extends Resource
{
    protected static ?string $model = ProductClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Product Classes';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductClassesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductClasses::route('/'),
            'create' => CreateProductClass::route('/create'),
            'edit' => EditProductClass::route('/{record}/edit'),
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
