<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Enums\CatalogStatus;
use App\Enums\FulfillmentCenterType;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Commerce\FulfillmentCenter;

/**
 * Imports a single remote PRX catalog row as a local Pending item.
 *
 * Creates the minimal mapped shell (name, descriptions, pricing, provider
 * ids); classification, ingredients, package items, and plans are enriched
 * by the next full catalog sync, which updates every mapped row.
 */
class ImportPrxCatalogItemAction
{
    use Transacts;

    public function executeProduct(array $remote): Product
    {
        return $this->tx(fn () => Product::create([
            'name' => $remote['name'],
            'short_description' => $remote['short_description'] ?? null,
            'description' => $remote['description'] ?? null,
            'status' => CatalogStatus::Pending,
            'retail_price' => $remote['pricing']['retail_price'] ?? 0,
            'sale_price' => $remote['pricing']['consumer_price'] ?? null,
            'cost' => $remote['pricing']['cost'] ?? null,
            'rx_required' => $remote['rx_required'] ?? false,
            'requires_lab' => $remote['rx_required'] ?? false,
            'provider_product_id' => $remote['id'],
            'provider_product_sku' => $remote['sku'] ?? null,
            'default_fulfillment_center_id' => $this->resolveFulfillmentCenter()->id,
            'last_synced_at' => now(),
        ]));
    }

    public function executePackage(array $remote): Package
    {
        return $this->tx(fn () => Package::create([
            'name' => $remote['name'],
            'description' => $remote['description'] ?? null,
            'status' => CatalogStatus::Pending,
            'retail_price' => $remote['pricing']['retail_price'] ?? 0,
            'sale_price' => $remote['pricing']['consumer_price'] ?? null,
            'cost' => $remote['pricing']['cost'] ?? null,
            'provider_package_id' => $remote['id'],
            'provider_package_sku' => $remote['package_number'] ?? null,
            'default_fulfillment_center_id' => $this->resolveFulfillmentCenter()->id,
            'last_synced_at' => now(),
        ]));
    }

    private function resolveFulfillmentCenter(): FulfillmentCenter
    {
        return FulfillmentCenter::firstOrCreate(
            [
                'system_type' => FulfillmentCenterType::PrescribeRx->value,
                'environment' => app()->isProduction() ? 'production' : 'sandbox',
            ],
            [
                'name' => 'Prescribe-Rx',
                'is_active' => true,
                'is_default_rx' => true,
            ]
        );
    }
}
