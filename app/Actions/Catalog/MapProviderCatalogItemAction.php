<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;

/**
 * Sets (or clears) the provider mapping on a local Product or Package.
 * Mapping only — provider-truth fields (pricing, classification,
 * ingredients) flow in on the next catalog sync.
 */
class MapProviderCatalogItemAction
{
    use Transacts;

    public function execute(Product|Package $item, ?string $providerId, ?string $providerSku = null): Product|Package
    {
        return $this->tx(function () use ($item, $providerId, $providerSku) {
            $item->update($item instanceof Product
                ? ['provider_product_id' => $providerId, 'provider_product_sku' => $providerSku]
                : ['provider_package_id' => $providerId, 'provider_package_sku' => $providerSku]);

            return $item->fresh();
        });
    }
}
