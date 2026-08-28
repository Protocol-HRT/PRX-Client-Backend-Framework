<?php

namespace App\Filament\Resources\Integrations;

use App\Filament\Resources\Integrations\Pages\CreateIntegrationInstance;
use App\Filament\Resources\Integrations\Pages\EditIntegrationInstance;
use App\Filament\Resources\Integrations\Pages\ListIntegrationInstances;
use App\Filament\Resources\Integrations\Schemas\IntegrationInstanceForm;
use App\Filament\Resources\Integrations\Tables\IntegrationInstancesTable;
use App\Models\Integrations\IntegrationInstance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Where an operator connects this install to the services it sends data to.
 *
 * The provider list comes from IntegrationRegistry, so this screen grows when a
 * driver is registered and needs no edit here. What an operator sets is which
 * vendor, their own credentials, what they are authorised to use it for, and —
 * separately and deliberately — whether it may receive health data.
 */
class IntegrationInstanceResource extends Resource
{
    protected static ?string $model = IntegrationInstance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Automation';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'integration';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return IntegrationInstanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntegrationInstancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationInstances::route('/'),
            'create' => CreateIntegrationInstance::route('/create'),
            'edit' => EditIntegrationInstance::route('/{record}/edit'),
        ];
    }
}
