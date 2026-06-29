<?php

namespace App\Filament\Resources\Catalog\Plans\Pages;

use App\Enums\CatalogStatus;
use App\Filament\Resources\Catalog\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CatalogStatus::Pending))
                ->badge(fn () => $this->getResource()::getModel()::where('status', CatalogStatus::Pending)->count())
                ->badgeColor('warning'),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CatalogStatus::Draft)),
            'published' => Tab::make('Published')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CatalogStatus::Published)),
            'archived' => Tab::make('Archived')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CatalogStatus::Archived)),
        ];
    }
}
