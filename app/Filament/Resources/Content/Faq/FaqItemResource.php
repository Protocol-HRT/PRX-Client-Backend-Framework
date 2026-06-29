<?php

namespace App\Filament\Resources\Content\Faq;

use App\Filament\Resources\Content\Faq\Pages\CreateFaqItem;
use App\Filament\Resources\Content\Faq\Pages\EditFaqItem;
use App\Filament\Resources\Content\Faq\Pages\ListFaqItems;
use App\Filament\Resources\Content\Faq\Schemas\FaqItemForm;
use App\Filament\Resources\Content\Faq\Tables\FaqItemsTable;
use App\Models\Content\FaqItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FaqItemResource extends Resource
{
    protected static ?string $model = FaqItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return FaqItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FaqItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqItems::route('/'),
            'create' => CreateFaqItem::route('/create'),
            'edit' => EditFaqItem::route('/{record}/edit'),
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
