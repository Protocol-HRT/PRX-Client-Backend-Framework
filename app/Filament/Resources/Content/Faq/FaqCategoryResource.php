<?php

namespace App\Filament\Resources\Content\Faq;

use App\Filament\Resources\Content\Faq\Pages\CreateFaqCategory;
use App\Filament\Resources\Content\Faq\Pages\EditFaqCategory;
use App\Filament\Resources\Content\Faq\Pages\ListFaqCategories;
use App\Filament\Resources\Content\Faq\Schemas\FaqCategoryForm;
use App\Filament\Resources\Content\Faq\Tables\FaqCategoriesTable;
use App\Models\Content\FaqCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FaqCategoryResource extends Resource
{
    protected static ?string $model = FaqCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'FAQ Categories';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FaqCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FaqCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqCategories::route('/'),
            'create' => CreateFaqCategory::route('/create'),
            'edit' => EditFaqCategory::route('/{record}/edit'),
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
