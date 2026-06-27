<?php

namespace App\Filament\Resources\Commerce\Encounters;

use App\Filament\Resources\Commerce\Encounters\Pages\EditEncounter;
use App\Filament\Resources\Commerce\Encounters\Pages\ListEncounters;
use App\Filament\Resources\Commerce\Encounters\Schemas\EncounterForm;
use App\Filament\Resources\Commerce\Encounters\Tables\EncountersTable;
use App\Models\Commerce\Encounter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EncounterResource extends Resource
{
    protected static ?string $model = Encounter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'prescribe_rx_encounter_id';

    public static function form(Schema $schema): Schema
    {
        return EncounterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EncountersTable::configure($table);
    }

    /** Encounters originate from PRX webhooks — no Create page in admin. */
    public static function getPages(): array
    {
        return [
            'index' => ListEncounters::route('/'),
            'edit' => EditEncounter::route('/{record}/edit'),
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
