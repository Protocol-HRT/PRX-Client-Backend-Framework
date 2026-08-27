<?php

namespace App\Filament\Resources\Kb\HealthGoals;

use App\Filament\Resources\Kb\HealthGoals\Pages\CreateHealthGoal;
use App\Filament\Resources\Kb\HealthGoals\Pages\EditHealthGoal;
use App\Filament\Resources\Kb\HealthGoals\Pages\ListHealthGoals;
use App\Filament\Resources\Kb\HealthGoals\RelationManagers\CompoundsRelationManager;
use App\Filament\Resources\Kb\HealthGoals\RelationManagers\IngredientsRelationManager;
use App\Filament\Resources\Kb\HealthGoals\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\Kb\HealthGoals\Schemas\HealthGoalForm;
use App\Filament\Resources\Kb\HealthGoals\Tables\HealthGoalsTable;
use App\Models\Kb\HealthGoal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class HealthGoalResource extends Resource
{
    protected static ?string $model = HealthGoal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 38;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Health goals';

    protected static ?string $modelLabel = 'health goal';

    /**
     * Counts goals offered in the quiz with nothing mapped to them.
     *
     * A goal a visitor can pick that recommends nothing is the one failure of
     * this module a person will actually see, and it is invisible from the
     * list until you open each row. It is counted on the INGREDIENT edge
     * because that is what recommendations resolve through — a goal with only
     * compounds attached teaches the knowledge base but sells nothing.
     */
    public static function getNavigationBadge(): ?string
    {
        $unmapped = HealthGoal::query()
            ->forQuiz()
            ->whereDoesntHave('ingredients')
            ->whereDoesntHave('products')
            ->count();

        return $unmapped > 0 ? (string) $unmapped : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Goals offered in the quiz with nothing mapped — a visitor picking one gets no recommendation.';
    }

    public static function form(Schema $schema): Schema
    {
        return HealthGoalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HealthGoalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            IngredientsRelationManager::class,
            ProductsRelationManager::class,
            CompoundsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHealthGoals::route('/'),
            'create' => CreateHealthGoal::route('/create'),
            'edit' => EditHealthGoal::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
