<?php

namespace App\Actions\Catalog;

use App\Enums\BillingMode;
use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\FulfillmentCenterType;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\MeasurementUnit;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductClass;
use App\Models\Catalog\ProductType;
use App\Models\Commerce\FulfillmentCenter;
use App\Services\PrescribeRx\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls products and packages from the PRX sales-org catalog and upserts
 * them locally at Pending status for admin review before publishing.
 *
 * Re-sync rules:
 *   - Pending items  → all fields updated (name, description, pricing, SKU)
 *   - Published/Draft/Archived → pricing updated only; admin-curated content preserved
 *   - Provider-truth fields (classification mapping, rx_required, cost,
 *     ingredients) are updated on EVERY sync regardless of status — they are
 *     clinical/mapping data, not curated marketing content.
 *
 * Classification: product classes/types are upserted into the local lookup
 * tables keyed by provider UUID (from /product-classes + /product-types when
 * available, else from the objects embedded on each product). Ingredients are
 * upserted from the product detail payload; quantity strings like "50mg" or
 * "10 mg / 3 ml" are parsed into concentration/per-volume pivots, with the
 * raw string preserved as provider_quantity_label.
 *
 * Images are intentionally NOT imported — PRX image URLs are presigned S3
 * URLs with short TTLs. Admins upload brand-appropriate images locally.
 */
class SyncPrescribeRxCatalogAction
{
    /** @var array<string, int>|null provider uuid → local product_classes.id */
    private ?array $classMap = null;

    /** @var array<string, int>|null provider uuid → local product_types.id */
    private ?array $typeMap = null;

    /** @var array<string, int>|null lowercase abbreviation → measurement_units.id */
    private ?array $unitMap = null;

    public function __construct(private readonly Client $prx) {}

    /**
     * @return array{products: array{new: int, updated: int}, packages: array{new: int, updated: int}, plans: array{new: int, updated: int}, ingredients: array{synced: int}}
     */
    public function execute(): array
    {
        $fc = $this->resolveFulfillmentCenter();

        $this->syncClassificationLookups();

        $productStats = $this->syncProducts($fc);
        $packageStats = $this->syncPackages($fc);

        return [
            'products' => ['new' => $productStats['new'], 'updated' => $productStats['updated']],
            'packages' => $packageStats['packages'],
            'plans' => $packageStats['plans'],
            'ingredients' => ['synced' => $productStats['ingredients']],
        ];
    }

    // ─── Classification lookups ───────────────────────────────────────────────

    /**
     * Upserts local ProductClass/ProductType rows from the PRX list
     * endpoints. Tolerates the endpoints being unavailable (older PRX
     * deployments) — per-product embedded objects cover the gap.
     */
    private function syncClassificationLookups(): void
    {
        try {
            foreach ($this->prx->listPrxProductClasses() as $raw) {
                if (! empty($raw['id'])) {
                    $this->resolveClassId($raw);
                }
            }
        } catch (Throwable $e) {
            Log::info('Catalog sync: /product-classes unavailable, relying on embedded objects', ['error' => $e->getMessage()]);
        }

        try {
            foreach ($this->prx->listPrxProductTypes() as $raw) {
                if (! empty($raw['id'])) {
                    $this->resolveTypeId($raw);
                }
            }
        } catch (Throwable $e) {
            Log::info('Catalog sync: /product-types unavailable, relying on embedded objects', ['error' => $e->getMessage()]);
        }
    }

    /** @param  array<string, mixed>  $raw  provider payload with id + name (+ product_class_id for types) */
    private function resolveClassId(array $raw): ?int
    {
        if (empty($raw['id'])) {
            return null;
        }

        $this->classMap ??= ProductClass::withTrashed()
            ->whereNotNull('provider_product_class_id')
            ->pluck('id', 'provider_product_class_id')
            ->all();

        if (isset($this->classMap[$raw['id']])) {
            return $this->classMap[$raw['id']];
        }

        $class = ProductClass::withTrashed()->firstOrCreate(
            ['provider_product_class_id' => $raw['id']],
            ['name' => $raw['name'] ?? 'Unnamed class', 'description' => $raw['description'] ?? null],
        );

        return $this->classMap[$raw['id']] = $class->id;
    }

    /** @param  array<string, mixed>  $raw */
    private function resolveTypeId(array $raw): ?int
    {
        if (empty($raw['id'])) {
            return null;
        }

        $this->typeMap ??= ProductType::withTrashed()
            ->whereNotNull('provider_product_type_id')
            ->pluck('id', 'provider_product_type_id')
            ->all();

        if (isset($this->typeMap[$raw['id']])) {
            return $this->typeMap[$raw['id']];
        }

        $type = ProductType::withTrashed()->firstOrCreate(
            ['provider_product_type_id' => $raw['id']],
            [
                'name' => $raw['name'] ?? 'Unnamed type',
                'description' => $raw['description'] ?? null,
                'product_class_id' => isset($raw['product_class_id'])
                    ? $this->resolveClassId(['id' => $raw['product_class_id']])
                    : null,
            ],
        );

        return $this->typeMap[$raw['id']] = $type->id;
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    /** @return array{new: int, updated: int, ingredients: int} */
    private function syncProducts(FulfillmentCenter $fc): array
    {
        $prxProducts = $this->prx->listAllPrxProducts();
        $stats = ['new' => 0, 'updated' => 0, 'ingredients' => 0];

        foreach ($prxProducts as $raw) {
            if (empty($raw['id'])) {
                continue;
            }

            $ingredients = $this->fetchIngredients($raw);

            DB::transaction(function () use ($raw, $ingredients, $fc, &$stats): void {
                $existing = Product::where('provider_product_id', $raw['id'])->first();

                if ($existing) {
                    $this->updateProduct($existing, $raw, $fc);
                    $stats['updated']++;
                } else {
                    $existing = $this->createProduct($raw, $fc);
                    $stats['new']++;
                }

                if ($ingredients !== null) {
                    $stats['ingredients'] += $this->syncProductIngredients($existing, $ingredients);
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
            ...$this->providerTruthAttributes($raw),
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
            ...$this->providerTruthAttributes($raw),
        ];

        if ($isPending) {
            $updates['name'] = $raw['name'];
            $updates['short_description'] = $raw['short_description'] ?? $product->short_description;
            $updates['description'] = $raw['description'] ?? $product->description;
            $updates['requires_lab'] = $raw['rx_required'] ?? $product->requires_lab;
        }

        $product->update($updates);
    }

    /**
     * Fields owned by the provider — mapping, clinical flags, internal cost —
     * applied on every sync regardless of curation status. Keys are omitted
     * (not nulled) when the payload doesn't carry the value.
     *
     * @return array<string, mixed>
     */
    private function providerTruthAttributes(array $raw): array
    {
        $attributes = [];

        $classId = $this->resolveClassId($raw['product_class'] ?? ['id' => $raw['product_class_id'] ?? null]);
        if ($classId !== null) {
            $attributes['product_class_id'] = $classId;
        }

        $typeId = $this->resolveTypeId($raw['product_type'] ?? ['id' => $raw['product_type_id'] ?? null]);
        if ($typeId !== null) {
            $attributes['product_type_id'] = $typeId;
        }

        if (array_key_exists('rx_required', $raw)) {
            $attributes['rx_required'] = (bool) $raw['rx_required'];
        }

        if (isset($raw['pricing']['cost'])) {
            $attributes['cost'] = $raw['pricing']['cost'];
        }

        return $attributes;
    }

    // ─── Ingredients ──────────────────────────────────────────────────────────

    /**
     * The `/products` list payload omits ingredients; the detail endpoint
     * carries them. Returns null when ingredients cannot be determined (so
     * existing pivots are left untouched), or the raw ingredient list.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchIngredients(array $raw): ?array
    {
        if (array_key_exists('ingredients', $raw)) {
            return $raw['ingredients'] ?? [];
        }

        try {
            $detail = $this->prx->getPrxProduct($raw['id']);

            return $detail['ingredients'] ?? [];
        } catch (Throwable $e) {
            Log::info('Catalog sync: product detail unavailable, skipping ingredient sync', [
                'product' => $raw['id'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Upserts Ingredient lookups (keyed by provider uuid, falling back to a
     * case-insensitive name match for admin-created rows) and syncs the
     * potency pivot from the provider quantity string.
     *
     * @param  array<int, array<string, mixed>>  $prxIngredients
     * @return int number of pivot rows written
     */
    private function syncProductIngredients(Product $product, array $prxIngredients): int
    {
        $pivotData = [];

        foreach (array_values($prxIngredients) as $position => $raw) {
            if (empty($raw['id']) && empty($raw['name'])) {
                continue;
            }

            $ingredient = $this->resolveIngredient($raw);

            $pivotData[$ingredient->id] = [
                ...$this->parseQuantity($raw['quantity'] ?? null),
                'provider_quantity_label' => $raw['quantity'] ?? null,
                'position' => $position,
            ];
        }

        $product->ingredients()->sync($pivotData);

        return count($pivotData);
    }

    private function resolveIngredient(array $raw): Ingredient
    {
        if (! empty($raw['id'])) {
            $existing = Ingredient::withTrashed()->where('provider_ingredient_id', $raw['id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($raw['name'])) {
            $byName = Ingredient::withTrashed()
                ->whereNull('provider_ingredient_id')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($raw['name'])])
                ->first();

            if ($byName) {
                $byName->update(['provider_ingredient_id' => $raw['id'] ?? null]);

                return $byName;
            }
        }

        return Ingredient::create([
            'name' => $raw['name'] ?? 'Unnamed ingredient',
            'provider_ingredient_id' => $raw['id'] ?? null,
        ]);
    }

    /**
     * Parses provider quantity strings — "50mg", "50 mg", "10 mg / 3 ml",
     * "10mg/3ml" — into pivot columns. Unparseable strings yield nulls; the
     * raw label is always preserved alongside.
     *
     * @return array{concentration: float|null, concentration_unit_id: int|null, per_volume: float|null, per_volume_unit_id: int|null}
     */
    private function parseQuantity(?string $quantity): array
    {
        $empty = [
            'concentration' => null,
            'concentration_unit_id' => null,
            'per_volume' => null,
            'per_volume_unit_id' => null,
        ];

        if (blank($quantity)) {
            return $empty;
        }

        $pattern = '/^\s*(\d+(?:\.\d+)?)\s*([a-z%\/]+?)\s*(?:\/\s*(\d+(?:\.\d+)?)\s*([a-z%]+))?\s*$/i';

        if (! preg_match($pattern, trim($quantity), $m)) {
            return $empty;
        }

        return [
            'concentration' => (float) $m[1],
            'concentration_unit_id' => $this->resolveUnitId($m[2]),
            'per_volume' => isset($m[3]) && $m[3] !== '' ? (float) $m[3] : null,
            'per_volume_unit_id' => isset($m[4]) ? $this->resolveUnitId($m[4]) : null,
        ];
    }

    private function resolveUnitId(string $abbreviation): ?int
    {
        $this->unitMap ??= MeasurementUnit::withTrashed()
            ->pluck('id', 'abbreviation')
            ->mapWithKeys(fn ($id, $abbr) => [mb_strtolower($abbr) => $id])
            ->all();

        return $this->unitMap[mb_strtolower(trim($abbreviation))] ?? null;
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
            'cost' => $raw['pricing']['cost'] ?? null,
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

        if (isset($raw['pricing']['cost'])) {
            $updates['cost'] = $raw['pricing']['cost'];
        }

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
            'billing_mode' => $this->inferBillingMode($raw),
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

        $billingMode = $this->inferBillingMode($raw);
        if ($billingMode !== null) {
            $updates['billing_mode'] = $billingMode;
        }

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

    /**
     * PRX exposes billing_mode as its integer enum when present; otherwise
     * infer recurring from subscription_interval_days and leave the rest null
     * for the admin to classify.
     */
    private function inferBillingMode(array $raw): ?BillingMode
    {
        if (isset($raw['billing_mode'])) {
            return BillingMode::fromProviderValue((int) $raw['billing_mode']);
        }

        if (! empty($raw['subscription_interval_days'])) {
            return BillingMode::Recurring;
        }

        return null;
    }
}
