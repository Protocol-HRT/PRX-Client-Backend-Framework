<?php

namespace App\Filament\Resources\Kb\HealthGoals\Pages;

use App\Filament\Resources\Kb\HealthGoals\HealthGoalResource;
use App\Models\Kb\HealthGoal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListHealthGoals extends ListRecords
{
    protected static string $resource = HealthGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getTabs(): array
    {
        return [
            'quiz' => Tab::make('In the quiz')
                ->modifyQueryUsing(fn (Builder $query) => $query->forQuiz())
                ->badge(fn (): int => HealthGoal::query()->forQuiz()->count()),

            // Leads the eye to the only failure a visitor sees: picking a goal
            // that recommends nothing.
            'unmapped' => Tab::make('Recommends nothing')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereDoesntHave('ingredients')
                    ->whereDoesntHave('products'))
                ->badge(fn (): int => HealthGoal::query()
                    ->whereDoesntHave('ingredients')
                    ->whereDoesntHave('products')
                    ->count())
                ->badgeColor('danger'),

            'all' => Tab::make('All'),
        ];
    }
}
