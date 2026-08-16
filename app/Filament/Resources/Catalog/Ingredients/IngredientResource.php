<?php

namespace App\Filament\Resources\Catalog\Ingredients;

use App\Filament\Resources\Catalog\Ingredients\Pages\CreateIngredient;
use App\Filament\Resources\Catalog\Ingredients\Pages\EditIngredient;
use App\Filament\Resources\Catalog\Ingredients\Pages\ListIngredients;
use App\Filament\Resources\Catalog\Ingredients\Schemas\IngredientForm;
use App\Filament\Resources\Catalog\Ingredients\Tables\IngredientsTable;
use App\Models\Catalog\Ingredient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class IngredientResource extends Resource
{
    protected static ?string $model = Ingredient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Ingredients';

    protected static ?int $navigationSort = 64;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return IngredientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngredientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIngredients::route('/'),
            'create' => CreateIngredient::route('/create'),
            'edit' => EditIngredient::route('/{record}/edit'),
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
