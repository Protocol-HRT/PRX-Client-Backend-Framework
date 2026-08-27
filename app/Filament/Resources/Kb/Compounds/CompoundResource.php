<?php

namespace App\Filament\Resources\Kb\Compounds;

use App\Filament\Resources\Kb\Compounds\Pages\CreateCompound;
use App\Filament\Resources\Kb\Compounds\Pages\EditCompound;
use App\Filament\Resources\Kb\Compounds\Pages\ListCompounds;
use App\Filament\Resources\Kb\Compounds\Schemas\CompoundForm;
use App\Filament\Resources\Kb\Compounds\Tables\CompoundsTable;
use App\Models\Kb\Compound;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CompoundResource extends Resource
{
    protected static ?string $model = Compound::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Knowledge base';

    protected static ?string $modelLabel = 'compound';

    /**
     * Draws attention to the queue: how many monographs are not yet publishable.
     *
     * Same condition as `Compound::published()`, so the badge cannot point at
     * work the publish toggle would accept, or stay silent about work it
     * refuses.
     */
    public static function getNavigationBadge(): ?string
    {
        $awaiting = Compound::query()->whereNull('regulatory_status')->count();

        return $awaiting > 0 ? (string) $awaiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Monographs with no regulatory status — none of these can be published.';
    }

    public static function form(Schema $schema): Schema
    {
        return CompoundForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompoundsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompounds::route('/'),
            'create' => CreateCompound::route('/create'),
            'edit' => EditCompound::route('/{record}/edit'),
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
