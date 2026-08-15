<?php

namespace App\Filament\Resources\Cms\RegionItems;

use App\Filament\Resources\Cms\RegionItems\Pages\CreateRegionItem;
use App\Filament\Resources\Cms\RegionItems\Pages\EditRegionItem;
use App\Filament\Resources\Cms\RegionItems\Pages\ListRegionItems;
use App\Filament\Resources\Cms\RegionItems\Schemas\RegionItemForm;
use App\Filament\Resources\Cms\RegionItems\Tables\RegionItemsTable;
use App\Models\Cms\RegionItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RegionItemResource extends Resource
{
    protected static ?string $model = RegionItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 18;

    protected static ?string $navigationLabel = 'Site Layout';

    protected static ?string $modelLabel = 'layout item';

    public static function form(Schema $schema): Schema
    {
        return RegionItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegionItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegionItems::route('/'),
            'create' => CreateRegionItem::route('/create'),
            'edit' => EditRegionItem::route('/{record}/edit'),
        ];
    }
}
