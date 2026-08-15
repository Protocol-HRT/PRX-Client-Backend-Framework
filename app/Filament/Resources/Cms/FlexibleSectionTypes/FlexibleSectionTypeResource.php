<?php

namespace App\Filament\Resources\Cms\FlexibleSectionTypes;

use App\Filament\Resources\Cms\FlexibleSectionTypes\Pages\CreateFlexibleSectionType;
use App\Filament\Resources\Cms\FlexibleSectionTypes\Pages\EditFlexibleSectionType;
use App\Filament\Resources\Cms\FlexibleSectionTypes\Pages\ListFlexibleSectionTypes;
use App\Filament\Resources\Cms\FlexibleSectionTypes\Schemas\FlexibleSectionTypeForm;
use App\Filament\Resources\Cms\FlexibleSectionTypes\Tables\FlexibleSectionTypesTable;
use App\Models\Cms\FlexibleSectionType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FlexibleSectionTypeResource extends Resource
{
    protected static ?string $model = FlexibleSectionType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Custom Section Types';

    protected static ?string $modelLabel = 'custom section type';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FlexibleSectionTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlexibleSectionTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlexibleSectionTypes::route('/'),
            'create' => CreateFlexibleSectionType::route('/create'),
            'edit' => EditFlexibleSectionType::route('/{record}/edit'),
        ];
    }
}
