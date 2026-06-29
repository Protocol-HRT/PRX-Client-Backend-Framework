<?php

namespace App\Actions\Catalog;

use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\FulfillmentCenterType;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Commerce\FulfillmentCenter;
use App\Services\PrescribeRx\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pulls products and packages from the PRX sales-org catalog and upserts
 * them locally at Pending status for admin review before publishing.
 *
 * Re-sync rules:
 *   - Pending items  → all fields updated (name, description, pricing, SKU)
 *   - Published/Draft/Archived → pricing updated only; admin-curated content preserved
 *
 * Images are intentionally NOT imported — PRX image URLs are presigned S3
 * URLs with short TTLs. Admins upload brand-appropriate images locally.
 */
class SyncPrescribeRxCatalogAction
{
    public function __construct(private readonly Client $prx) {}

    /**
     * @return array{products: array{new: int, updated: int}, packages: array{new: int, updated: int}, plans: array{new: int, updated: int}}
     */
    public function execute(): array
    {
        $fc = $this->resolveFulfillmentCenter();

        $productStats = $this->syncProducts($fc);
        $packageStats = $this->syncPackages($fc);

        return [
            'products' => $productStats,
            'packages' => $packageStats['packages'],
            'plans' => $packageStats['plans'],
        ];
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    private function syncProducts(FulfillmentCenter $fc): array
    {
        $prxProducts = $this->prx->listAllPrxProducts();
        $stats = ['new' => 0, 'updated' => 0];

        foreach ($prxProducts as $raw) {
            if (empty($raw['id'])) {
                continue;
            }

            DB::transaction(function () use ($raw, $fc, &$stats): void {
                $existing = Product::where('provider_product_id', $raw['id'])->first();

                if ($existing) {
                    $this->updateProduct($existing, $raw, $fc);
                    $stats['updated']++;
                } else {
                    $this->createProduct($raw, $fc);
                    $stats['new']++;
                }
            });
        }

        return $stats;
    }

    private function createProduct(array $raw, FulfillmentCenter $fc): Product
    {
        return Product::create([
            'name' => $raw['name'],
            'short_description' => $raw['short_description'] ?? null,
            'description' => $raw['description'] ?? null,
            'status' => CatalogStatus::Pending,
            'retail_price' => $raw['pricing']['retail_price'] ?? 0,
            'sale_price' => $raw['pricing']['consumer_price'] ?? null,
            'provider_product_id' => $raw['id'],
            'provider_product_sku' => $raw['sku'] ?? null,
            'requires_lab' => $raw['rx_required'] ?? false,
            'default_fulfillment_center_id' => $fc->id,
            'last_synced_at' => now(),
        ]);
    }

    private function updateProduct(Product $product, array $raw, FulfillmentCenter $fc): void
    {
        $isPending = $product->status === CatalogStatus::Pending;

        $updates = [
            'retail_price' => $raw['pricing']['retail_price'] ?? $product->retail_price,
            'sale_price' => $raw['pricing']['consumer_price'] ?? $product->sale_price,
            'provider_product_sku' => $raw['sku'] ?? $product->provider_product_sku,
            'default_fulfillment_center_id' => $fc->id,
            'last_synced_at' => now(),
        ];

        if ($isPending) {
            $updates['name'] = $raw['name'];
            $updates['short_description'] = $raw['short_description'] ?? $product->short_description;
            $updates['description'] = $raw['description'] ?? $product->description;
            $updates['requires_lab'] = $raw['rx_required'] ?? $product->requires_lab;
        }

        $product->update($updates);
    }

    // ─── Packages & Plans ──────────────────────────────────────────────────────

    private function syncPackages(FulfillmentCenter $fc): array
    {
        $prxPackages = $this->prx->listAllPrxPackages();
        $stats = ['packages' => ['new' => 0, 'updated' => 0], 'plans' => ['new' => 0, 'updated' => 0]];

        foreach ($prxPackages as $raw) {
            if (empty($raw['id'])) {
                continue;
            }

            DB::transaction(function () use ($raw, $fc, &$stats): void {
                $existing = Package::where('provider_package_id', $raw['id'])->first();

                if ($existing) {
                    $this->updatePackage($existing, $raw, $fc);
                    $stats['packages']['updated']++;
                } else {
                    $existing = $this->createPackage($raw, $fc);
                    $stats['packages']['new']++;
                }

                $this->syncPackageProducts($existing, $raw);
                $planStats = $this->syncPlans($existing, $raw['plans'] ?? [], $fc);
                $stats['plans']['new'] += $planStats['new'];
                $stats['plans']['updated'] += $planStats['updated'];
            });
        }

        return $stats;
    }

    private function createPackage(array $raw, FulfillmentCenter $fc): Package
    {
        return Package::create([
            'name' => $raw['name'],
            'description' => $raw['description'] ?? null,
            'status' => CatalogStatus::Pending,
            'retail_price' => $raw['pricing']['retail_price'] ?? 0,
            'sale_price' => $raw['pricing']['consumer_price'] ?? null,
            'provider_package_id' => $raw['id'],
            'provider_package_sku' => $raw['package_number'] ?? null,
            'default_fulfillment_center_id' => $fc->id,
            'last_synced_at' => now(),
        ]);
    }

    private function updatePackage(Package $package, array $raw, FulfillmentCenter $fc): void
    {
        $isPending = $package->status === CatalogStatus::Pending;

        $updates = [
            'retail_price' => $raw['pricing']['retail_price'] ?? $package->retail_price,
            'sale_price' => $raw['pricing']['consumer_price'] ?? $package->sale_price,
            'provider_package_sku' => $raw['package_number'] ?? $package->provider_package_sku,
            'default_fulfillment_center_id' => $fc->id,
            'last_synced_at' => now(),
        ];

        if ($isPending) {
            $updates['name'] = $raw['name'];
            $updates['description'] = $raw['description'] ?? $package->description;
        }

        $package->update($updates);
    }

    private function syncPackageProducts(Package $package, array $raw): void
    {
        $items = $raw['items'] ?? [];
        if (empty($items)) {
            return;
        }

        $pivotData = [];

        foreach ($items as $index => $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $product = Product::where('provider_product_id', $item['product_id'])->first();

            if (! $product) {
                Log::info('Catalog sync: PRX package item product not found locally', [
                    'package' => $raw['id'],
                    'product_id' => $item['product_id'],
                    'name' => $item['product_name'] ?? null,
                ]);

                continue;
            }

            $pivotData[$product->id] = [
                'sort_order' => $item['sort_order'] ?? $index,
                'is_included' => ! ($item['is_optional'] ?? false),
            ];
        }

        if (! empty($pivotData)) {
            $package->products()->sync($pivotData);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $prxPlans
     * @return array{new: int, updated: int}
     */
    private function syncPlans(Package $package, array $prxPlans, FulfillmentCenter $fc): array
    {
        $stats = ['new' => 0, 'updated' => 0];

        foreach ($prxPlans as $raw) {
            if (empty($raw['id'])) {
                continue;
            }

            $existing = Plan::where('provider_plan_id', $raw['id'])->first();

            if ($existing) {
                $this->updatePlan($existing, $raw, $fc);
                $stats['updated']++;
            } else {
                $this->createPlan($package, $raw, $fc);
                $stats['new']++;
            }
        }

        return $stats;
    }

    private function createPlan(Package $package, array $raw, FulfillmentCenter $fc): Plan
    {
        $termMonths = $raw['term_months'] ?? null;

        return Plan::create([
            'package_id' => $package->id,
            'name' => $raw['name'],
            'status' => CatalogStatus::Pending,
            'retail_price' => $raw['price'] ?? 0,
            'billing_period' => $this->inferBillingPeriod($termMonths, $raw),
            'term_months' => $termMonths,
            'is_recurring' => ! empty($raw['subscription_interval_days']),
            'is_default' => $raw['is_default'] ?? false,
            'provider_plan_id' => $raw['id'],
            'default_fulfillment_center_id' => $fc->id,
            'last_synced_at' => now(),
        ]);
    }

    private function updatePlan(Plan $plan, array $raw, FulfillmentCenter $fc): void
    {
        $isPending = $plan->status === CatalogStatus::Pending;

        $updates = [
            'retail_price' => $raw['price'] ?? $plan->retail_price,
            'is_default' => $raw['is_default'] ?? $plan->is_default,
            'default_fulfillment_center_id' => $fc->id,
            'last_synced_at' => now(),
        ];

        if ($isPending) {
            $updates['name'] = $raw['name'];
            $updates['term_months'] = $raw['term_months'] ?? $plan->term_months;
            $updates['billing_period'] = $this->inferBillingPeriod($raw['term_months'] ?? null, $raw);
            $updates['is_recurring'] = ! empty($raw['subscription_interval_days']);
        }

        $plan->update($updates);
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

    private function inferBillingPeriod(?int $termMonths, array $raw): BillingPeriod
    {
        if (! empty($raw['subscription_interval_days'])) {
            return BillingPeriod::Monthly;
        }

        return match ($termMonths) {
            1 => BillingPeriod::Monthly,
            3 => BillingPeriod::Quarterly,
            6 => BillingPeriod::SemiAnnual,
            9 => BillingPeriod::NineMonth,
            12 => BillingPeriod::Annual,
            default => BillingPeriod::OneTime,
        };
    }
}
