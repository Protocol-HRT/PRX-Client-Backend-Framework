<?php

namespace App\Filament\Resources\Catalog\Packages\Pages;

use App\Actions\Catalog\SyncPrescribeRxCatalogAction;
use App\Enums\CatalogStatus;
use App\Filament\Resources\Catalog\Packages\PackageResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_prx')
                ->label('Sync from PRX')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync PRX catalog')
                ->modalDescription('Imports all packages, their products, and plans from your PRX sales org. New items land at Pending status. Pricing is always updated; admin-curated content is preserved. Images are not imported.')
                ->modalSubmitActionLabel('Run sync')
                ->action(function (SyncPrescribeRxCatalogAction $action): void {
                    $stats = $action->execute();
                    $packages = $stats['packages'];
                    $products = $stats['products'];
                    $plans = $stats['plans'];

                    Notification::make()
                        ->title('PRX catalog synced')
                        ->body(implode(' · ', [
                            "Packages: {$packages['new']} new, {$packages['updated']} updated",
                            "Products: {$products['new']} new, {$products['updated']} updated",
                            "Plans: {$plans['new']} new, {$plans['updated']} updated",
                        ]))
                        ->success()
                        ->send();
                }),
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
