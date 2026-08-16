<?php

namespace App\Filament\Resources\Catalog\Actions;

use App\Actions\Catalog\MapProviderCatalogItemAction;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Services\PrescribeRx\RemoteCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Table actions for mapping a local Product/Package to its PRX remote row:
 * "Match to PRX" (suggestion-ranked select, visible on unmapped rows) and
 * "Clear PRX mapping" (visible on mapped rows).
 */
class MatchToPrxTableAction
{
    public static function make(): Action
    {
        return Action::make('match_prx')
            ->label('Match to PRX')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->visible(fn (Model $record): bool => self::providerId($record) === null)
            ->schema(fn (Model $record): array => [
                Select::make('remote_id')
                    ->label('PRX item')
                    ->options(self::optionsFor($record))
                    ->default(self::topSuggestionId($record))
                    ->searchable()
                    ->required()
                    ->helperText('Best matches by SKU/name similarity are listed first. Use "Refresh from PRX" on the PRX Catalog page if inventory looks stale.'),
            ])
            ->action(function (Model $record, array $data): void {
                $remote = self::remoteRows($record)->firstWhere('id', $data['remote_id']);

                if ($remote === null) {
                    Notification::make()->danger()->title('PRX item no longer available')->send();

                    return;
                }

                app(MapProviderCatalogItemAction::class)->execute(
                    $record,
                    $remote['id'],
                    $remote['sku'] ?? $remote['package_number'] ?? null,
                );

                Notification::make()->success()->title("Mapped to {$remote['name']}")->send();
            });
    }

    public static function unmap(): Action
    {
        return Action::make('unmap_prx')
            ->label('Clear PRX mapping')
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('gray')
            ->visible(fn (Model $record): bool => self::providerId($record) !== null)
            ->requiresConfirmation()
            ->modalDescription('Removes the provider id/SKU mapping. The item stays local; it will no longer receive pricing or clinical updates from catalog syncs.')
            ->action(function (Model $record): void {
                app(MapProviderCatalogItemAction::class)->execute($record, null, null);

                Notification::make()->success()->title('PRX mapping cleared')->send();
            });
    }

    private static function providerId(Model $record): ?string
    {
        return $record instanceof Package
            ? $record->provider_package_id
            : $record->provider_product_id;
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function remoteRows(Model $record): Collection
    {
        $remote = app(RemoteCatalog::class);

        return $record instanceof Package ? $remote->packages() : $remote->products();
    }

    /** @return array<string, string> remote id → ranked option label */
    private static function optionsFor(Model $record): array
    {
        $remote = app(RemoteCatalog::class);

        /** @var Collection<int, array<string, mixed>> $suggestions */
        $suggestions = $record instanceof Package
            ? $remote->suggestionsForPackage($record)
            : $remote->suggestionsForProduct($record);

        $mapped = $record instanceof Package ? $remote->mappedPackageIndex() : $remote->mappedProductIndex();

        $label = fn (array $row): string => trim(($row['name'] ?? '—').' ('.($row['sku'] ?? $row['package_number'] ?? 'no SKU').')');

        $options = $suggestions
            ->filter(fn (array $row): bool => ($row['match_score'] ?? 0) > 0 && ! isset($mapped[$row['id']]))
            ->mapWithKeys(fn (array $row): array => [$row['id'] => "★ {$row['match_score']}% — {$label($row)}"]);

        $rest = self::remoteRows($record)
            ->filter(fn (array $row): bool => ! empty($row['id']) && ! $options->has($row['id']) && ! isset($mapped[$row['id']]))
            ->sortBy('name')
            ->mapWithKeys(fn (array $row): array => [$row['id'] => $label($row)]);

        return $options->merge($rest)->all();
    }

    private static function topSuggestionId(Model $record): ?string
    {
        $remote = app(RemoteCatalog::class);

        $suggestions = $record instanceof Package
            ? $remote->suggestionsForPackage($record, 1)
            : $remote->suggestionsForProduct($record, 1);

        $top = $suggestions->first();

        return ($top['match_score'] ?? 0) >= 60 ? $top['id'] : null;
    }
}
