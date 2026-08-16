<?php

namespace App\Filament\Pages;

use App\Actions\Catalog\ImportPrxCatalogItemAction;
use App\Actions\Catalog\MapProviderCatalogItemAction;
use App\Actions\Catalog\SyncPrescribeRxCatalogAction;
use App\Filament\Resources\Catalog\Packages\PackageResource;
use App\Filament\Resources\Catalog\Products\ProductResource;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Services\PrescribeRx\RemoteCatalog;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Browse the remote PRX inventory, see which rows are already mapped to
 * local catalog items, import unmapped rows as Pending items, or map a
 * remote row onto an existing local product/package.
 */
class PrxCatalog extends Page implements HasTable
{
    use HasPageShield, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'PRX Catalog';

    protected static ?int $navigationSort = 55;

    protected static ?string $title = 'PRX Catalog';

    protected static ?string $slug = 'prx-catalog';

    protected string $view = 'filament.pages.prx-catalog';

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $rows = $this->remoteRows($filters);

                if (filled($search)) {
                    $needle = mb_strtolower($search);
                    $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['name'].' '.$row['sku']), $needle));
                }

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->keyBy('id'),
                    total: $rows->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('sku')->label('SKU')->searchable()->copyable(),
                TextColumn::make('retail_price')->label('Retail')->money('usd')->placeholder('—'),
                IconColumn::make('rx_required')->label('Rx')->boolean(),
                TextColumn::make('mapped_name')
                    ->label('Mapped to')
                    ->badge()
                    ->color(fn (?string $state): string => $state === null ? 'warning' : 'success')
                    ->placeholder('Unmapped'),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->options(['products' => 'Products', 'packages' => 'Packages'])
                    ->default('products')
                    ->selectablePlaceholder(false),
            ])
            ->recordActions([
                Action::make('import')
                    ->label('Import')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (array $record): bool => $record['mapped_id'] === null)
                    ->requiresConfirmation()
                    ->modalDescription('Creates a local Pending item mapped to this PRX row. Classification, ingredients, package items, and plans are filled in by the next full catalog sync.')
                    ->action(function (array $record): void {
                        $record['kind'] === 'packages'
                            ? app(ImportPrxCatalogItemAction::class)->executePackage($record['raw'])
                            : app(ImportPrxCatalogItemAction::class)->executeProduct($record['raw']);

                        Notification::make()->success()->title('Imported as Pending')->send();
                        $this->resetTable();
                    }),
                Action::make('map')
                    ->label('Map to existing')
                    ->icon(Heroicon::OutlinedLink)
                    ->visible(fn (array $record): bool => $record['mapped_id'] === null)
                    ->schema(fn (array $record): array => [
                        Select::make('local_id')
                            ->label($record['kind'] === 'packages' ? 'Local package' : 'Local product')
                            ->options(
                                $record['kind'] === 'packages'
                                    ? Package::query()->whereNull('provider_package_id')->orderBy('name')->pluck('name', 'id')
                                    : Product::query()->whereNull('provider_product_id')->orderBy('name')->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Only currently unmapped local items are listed.'),
                    ])
                    ->action(function (array $record, array $data): void {
                        $local = $record['kind'] === 'packages'
                            ? Package::findOrFail($data['local_id'])
                            : Product::findOrFail($data['local_id']);

                        app(MapProviderCatalogItemAction::class)->execute($local, $record['id'], $record['sku'] ?: null);

                        Notification::make()->success()->title("Mapped to {$local->name}")->send();
                        $this->resetTable();
                    }),
                Action::make('open_local')
                    ->label('Open local')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (array $record): bool => $record['mapped_id'] !== null)
                    ->url(fn (array $record): string => $record['kind'] === 'packages'
                        ? PackageResource::getUrl('edit', ['record' => $record['mapped_id']])
                        : ProductResource::getUrl('edit', ['record' => $record['mapped_id']])),
            ])
            ->headerActions([
                Action::make('refresh_remote')
                    ->label('Refresh from PRX')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (): void {
                        app(RemoteCatalog::class)->refresh();
                        Notification::make()->success()->title('Remote catalog cache cleared')->send();
                        $this->resetTable();
                    }),
                Action::make('full_sync')
                    ->label('Run full sync')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->requiresConfirmation()
                    ->modalDescription('Imports every PRX product/package and refreshes provider-truth fields (pricing, classification, ingredients) on all mapped rows. Admin-curated content is preserved.')
                    ->action(function (SyncPrescribeRxCatalogAction $sync): void {
                        $stats = $sync->execute();
                        Notification::make()
                            ->success()
                            ->title('PRX catalog synced')
                            ->body("Products {$stats['products']['new']} new / {$stats['products']['updated']} updated · Packages {$stats['packages']['new']} new / {$stats['packages']['updated']} updated")
                            ->send();
                        $this->resetTable();
                    }),
            ])
            ->emptyStateHeading('No remote PRX rows')
            ->emptyStateDescription('Check the PrescribeRx integration settings (token + environment), then use "Refresh from PRX".');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function remoteRows(array $filters): Collection
    {
        $kind = $filters['kind']['value'] ?? 'products';
        $remote = app(RemoteCatalog::class);

        $rows = $kind === 'packages' ? $remote->packages() : $remote->products();
        $mapped = $kind === 'packages' ? $remote->mappedPackageIndex() : $remote->mappedProductIndex();

        return $rows
            ->filter(fn (array $row): bool => ! empty($row['id']))
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'kind' => $kind,
                'name' => $row['name'] ?? '—',
                'sku' => (string) ($row['sku'] ?? $row['package_number'] ?? ''),
                'retail_price' => $row['pricing']['retail_price'] ?? null,
                'rx_required' => (bool) ($row['rx_required'] ?? false),
                'mapped_id' => $mapped[$row['id']]['id'] ?? null,
                'mapped_name' => $mapped[$row['id']]['name'] ?? null,
                'raw' => $row,
            ])
            ->values();
    }
}
