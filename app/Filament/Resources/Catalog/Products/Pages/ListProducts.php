<?php

namespace App\Filament\Resources\Catalog\Products\Pages;

use App\Actions\Catalog\SyncPrescribeRxCatalogAction;
use App\Enums\CatalogStatus;
use App\Filament\Resources\Catalog\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_prx')
                ->visible(fn (): bool => auth()->user()?->can('Create:Product') ?? false)
                ->label('Sync from PRX')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync PRX catalog')
                ->modalDescription('This will import all products assigned to your sales org. New items land at Pending status. Pricing on existing items is always updated; admin-curated content is preserved.')
                ->modalSubmitActionLabel('Run sync')
                ->action(function (SyncPrescribeRxCatalogAction $action): void {
                    $stats = $action->execute();
                    $new = $stats['products']['new'];
                    $updated = $stats['products']['updated'];

                    Notification::make()
                        ->title('PRX catalog synced')
                        ->body("{$new} new · {$updated} updated (products)")
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
