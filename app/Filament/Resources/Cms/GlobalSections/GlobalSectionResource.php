<?php

namespace App\Filament\Resources\Cms\GlobalSections;

use App\Filament\Resources\Cms\GlobalSections\Pages\CreateGlobalSection;
use App\Filament\Resources\Cms\GlobalSections\Pages\EditGlobalSection;
use App\Filament\Resources\Cms\GlobalSections\Pages\ListGlobalSections;
use App\Filament\Resources\Cms\GlobalSections\Schemas\GlobalSectionForm;
use App\Filament\Resources\Cms\GlobalSections\Tables\GlobalSectionsTable;
use App\Models\Cms\GlobalSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GlobalSectionResource extends Resource
{
    protected static ?string $model = GlobalSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 16;

    protected static ?string $navigationLabel = 'Global Blocks';

    protected static ?string $modelLabel = 'global block';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GlobalSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GlobalSectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlobalSections::route('/'),
            'create' => CreateGlobalSection::route('/create'),
            'edit' => EditGlobalSection::route('/{record}/edit'),
        ];
    }
}
