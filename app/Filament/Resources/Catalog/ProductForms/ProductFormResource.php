<?php

namespace App\Filament\Resources\Catalog\ProductForms;

use App\Filament\Resources\Catalog\ProductForms\Pages\CreateProductForm;
use App\Filament\Resources\Catalog\ProductForms\Pages\EditProductForm;
use App\Filament\Resources\Catalog\ProductForms\Pages\ListProductForms;
use App\Filament\Resources\Catalog\ProductForms\Schemas\ProductFormForm;
use App\Filament\Resources\Catalog\ProductForms\Tables\ProductFormsTable;
use App\Models\Catalog\ProductForm as ProductFormModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProductFormResource extends Resource
{
    protected static ?string $model = ProductFormModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Product Forms';

    protected static ?int $navigationSort = 68;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductFormsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductForms::route('/'),
            'create' => CreateProductForm::route('/create'),
            'edit' => EditProductForm::route('/{record}/edit'),
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
