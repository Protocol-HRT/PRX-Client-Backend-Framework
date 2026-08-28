<?php

namespace App\Filament\Resources\LeadDispositions;

use App\Filament\Resources\LeadDispositions\Pages\CreateLeadDisposition;
use App\Filament\Resources\LeadDispositions\Pages\EditLeadDisposition;
use App\Filament\Resources\LeadDispositions\Pages\ListLeadDispositions;
use App\Filament\Resources\LeadDispositions\Schemas\LeadDispositionForm;
use App\Filament\Resources\LeadDispositions\Tables\LeadDispositionsTable;
use App\Models\LeadDisposition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The operator's own funnel vocabulary.
 *
 * This resource exists so that adding a stage — "quiz complete", "nurture",
 * "booked a call" — is a row rather than a deployment. Workflows trigger on
 * movement between these, so the set of dispositions IS the shape of the funnel.
 */
class LeadDispositionResource extends Resource
{
    protected static ?string $model = LeadDisposition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'disposition';

    public static function form(Schema $schema): Schema
    {
        return LeadDispositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadDispositionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadDispositions::route('/'),
            'create' => CreateLeadDisposition::route('/create'),
            'edit' => EditLeadDisposition::route('/{record}/edit'),
        ];
    }
}
