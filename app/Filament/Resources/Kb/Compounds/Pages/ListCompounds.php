<?php

namespace App\Filament\Resources\Kb\Compounds\Pages;

use App\Filament\Resources\Kb\Compounds\CompoundResource;
use App\Models\Kb\Compound;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCompounds extends ListRecords
{
    protected static string $resource = CompoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * The blocking queue leads deliberately. After an import this list is a
     * hundred summarised monographs nobody has looked at, and the first thing
     * an operator needs is what is stopping them going live, not an
     * alphabetical library.
     */
    public function getTabs(): array
    {
        return [
            // The same condition the publish toggle and the API enforce, so the
            // tab is the actual to-do list rather than an approximation of it.
            'needs_status' => Tab::make('Needs a status')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('regulatory_status'))
                ->badge(fn (): int => Compound::query()->whereNull('regulatory_status')->count())
                ->badgeColor('warning'),
            'peptides' => Tab::make('Peptides')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_peptide', true))
                ->badge(fn (): int => Compound::query()->where('is_peptide', true)->count()),
            'published' => Tab::make('Published')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_published', true))
                ->badge(fn (): int => Compound::query()->where('is_published', true)->count())
                ->badgeColor('success'),
            'all' => Tab::make('All'),
        ];
    }
}
