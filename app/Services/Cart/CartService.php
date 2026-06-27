<?php

namespace App\Services\Cart;

use App\Data\Cart\CartItemViewData;
use App\Data\Cart\CartViewData;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelData\DataCollection;

/**
 * Session-backed cart store for the public site.
 *
 * Storage shape (session key 'cart'):
 *   [ ['resource_type' => 'product|package|plan', 'resource_id' => int, 'quantity' => int], ... ]
 *
 * On read, items are hydrated by looking up the underlying Catalog model and
 * folding name/image/price into a CartItemViewData for the UI. Items whose
 * underlying record was deleted/unpublished are returned with available=false
 * so the UI can flag them; subtotal excludes them.
 *
 * Swap point: when we add a logged-in patient store, this service can read
 * from a `cart_items` DB table instead of session — Livewire layer untouched.
 */
class CartService
{
    private const SESSION_KEY = 'cart';

    private const ALLOWED_TYPES = ['product', 'package', 'plan'];

    private const MAX_QUANTITY = 99;

    /**
     * Return the current hydrated cart view.
     */
    public function view(): CartViewData
    {
        $stored = $this->stored();
        $items = [];
        $totalQty = 0;
        $subtotal = 0.0;
        $hasUnavailable = false;

        $models = $this->loadModels($stored);

        foreach ($stored as $row) {
            $key = $row['resource_type'].':'.$row['resource_id'];
            $model = $models[$key] ?? null;

            if (! $model) {
                $hasUnavailable = true;
                $items[] = new CartItemViewData(
                    resource_type: $row['resource_type'],
                    resource_id: $row['resource_id'],
                    quantity: $row['quantity'],
                    name: 'Item no longer available',
                    slug: '',
                    url: '#',
                    hero_image_path: null,
                    unit_price: null,
                    line_total: null,
                    price_suffix: null,
                    billing_period: null,
                    prescribe_rx_id: null,
                    prescribe_rx_number: null,
                    available: false,
                );

                continue;
            }

            $unitPrice = $this->priceFor($model);
            $lineTotal = $unitPrice !== null ? round($unitPrice * $row['quantity'], 2) : null;
            $totalQty += $row['quantity'];
            $subtotal += $lineTotal ?? 0.0;

            $items[] = new CartItemViewData(
                resource_type: $row['resource_type'],
                resource_id: $row['resource_id'],
                quantity: $row['quantity'],
                name: $model->name,
                slug: $model->slug,
                url: $this->urlFor($row['resource_type'], $model->slug),
                hero_image_path: $model->hero_image_path,
                unit_price: $unitPrice,
                line_total: $lineTotal,
                price_suffix: $this->priceSuffixFor($model),
                billing_period: $row['resource_type'] === 'plan' ? $model->billing_period?->value : null,
                prescribe_rx_id: $this->prescribeRxIdFor($row['resource_type'], $model),
                prescribe_rx_number: $this->prescribeRxNumberFor($row['resource_type'], $model),
            );
        }

        return new CartViewData(
            items: new DataCollection(CartItemViewData::class, $items),
            total_quantity: $totalQty,
            subtotal: round($subtotal, 2),
            has_unavailable_items: $hasUnavailable,
        );
    }

    public function totalQuantity(): int
    {
        return collect($this->stored())->sum('quantity');
    }

    public function add(string $resourceType, int $resourceId, int $quantity = 1): void
    {
        $this->guardType($resourceType);
        $quantity = max(1, $quantity);

        $stored = $this->stored();
        $existing = $this->findRowKey($stored, $resourceType, $resourceId);

        if ($existing !== null) {
            $stored[$existing]['quantity'] = min(self::MAX_QUANTITY, $stored[$existing]['quantity'] + $quantity);
        } else {
            $stored[] = [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'quantity' => min(self::MAX_QUANTITY, $quantity),
            ];
        }

        $this->store($stored);
    }

    public function setQuantity(string $resourceType, int $resourceId, int $quantity): void
    {
        $this->guardType($resourceType);

        if ($quantity < 1) {
            $this->remove($resourceType, $resourceId);

            return;
        }

        $stored = $this->stored();
        $existing = $this->findRowKey($stored, $resourceType, $resourceId);

        if ($existing === null) {
            return;
        }

        $stored[$existing]['quantity'] = min(self::MAX_QUANTITY, $quantity);
        $this->store($stored);
    }

    public function increment(string $resourceType, int $resourceId): void
    {
        $this->add($resourceType, $resourceId, 1);
    }

    public function decrement(string $resourceType, int $resourceId): void
    {
        $stored = $this->stored();
        $existing = $this->findRowKey($stored, $resourceType, $resourceId);

        if ($existing === null) {
            return;
        }

        $next = $stored[$existing]['quantity'] - 1;

        if ($next < 1) {
            $this->remove($resourceType, $resourceId);

            return;
        }

        $stored[$existing]['quantity'] = $next;
        $this->store($stored);
    }

    public function remove(string $resourceType, int $resourceId): void
    {
        $this->guardType($resourceType);
        $stored = $this->stored();
        $stored = array_values(array_filter(
            $stored,
            fn (array $row) => ! ($row['resource_type'] === $resourceType && $row['resource_id'] === $resourceId),
        ));
        $this->store($stored);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Raw cart rows, sanitized for downstream serialization (e.g. into Lead.cart_items).
     *
     * @return array<int, array{resource_type: string, resource_id: int, quantity: int}>
     */
    public function snapshot(): array
    {
        return $this->stored();
    }

    /* ----------------------- internals ----------------------- */

    /**
     * @return array<int, array{resource_type: string, resource_id: int, quantity: int}>
     */
    private function stored(): array
    {
        /** @var array<int, array<string, mixed>> $raw */
        $raw = Session::get(self::SESSION_KEY, []);

        return array_values(array_filter(array_map(
            fn ($row) => $this->normalizeRow($row),
            $raw,
        )));
    }

    /**
     * @param  array<int, array{resource_type: string, resource_id: int, quantity: int}>  $rows
     */
    private function store(array $rows): void
    {
        Session::put(self::SESSION_KEY, $rows);
    }

    /**
     * @param  mixed  $row
     * @return array{resource_type: string, resource_id: int, quantity: int}|null
     */
    private function normalizeRow($row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $type = $row['resource_type'] ?? null;
        $id = $row['resource_id'] ?? null;
        $qty = (int) ($row['quantity'] ?? 0);

        if (! in_array($type, self::ALLOWED_TYPES, true) || ! is_int($id) && ! ctype_digit((string) $id)) {
            return null;
        }

        if ($qty < 1) {
            return null;
        }

        return [
            'resource_type' => (string) $type,
            'resource_id' => (int) $id,
            'quantity' => min(self::MAX_QUANTITY, $qty),
        ];
    }

    /**
     * @param  array<int, array{resource_type: string, resource_id: int, quantity: int}>  $stored
     */
    private function findRowKey(array $stored, string $resourceType, int $resourceId): ?int
    {
        foreach ($stored as $key => $row) {
            if ($row['resource_type'] === $resourceType && $row['resource_id'] === $resourceId) {
                return $key;
            }
        }

        return null;
    }

    private function guardType(string $resourceType): void
    {
        if (! in_array($resourceType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException("Unsupported cart resource type: {$resourceType}");
        }
    }

    /**
     * Eager-load all referenced models in 3 grouped queries.
     *
     * @param  array<int, array{resource_type: string, resource_id: int, quantity: int}>  $stored
     * @return array<string, Product|Package|Plan>  keyed by "type:id"
     */
    private function loadModels(array $stored): array
    {
        $byType = collect($stored)->groupBy('resource_type');
        $out = [];

        if ($ids = $byType->get('product')?->pluck('resource_id')->all()) {
            foreach (Product::query()->whereIn('id', $ids)->get() as $m) {
                $out['product:'.$m->id] = $m;
            }
        }

        if ($ids = $byType->get('package')?->pluck('resource_id')->all()) {
            foreach (Package::query()->whereIn('id', $ids)->get() as $m) {
                $out['package:'.$m->id] = $m;
            }
        }

        if ($ids = $byType->get('plan')?->pluck('resource_id')->all()) {
            foreach (Plan::query()->whereIn('id', $ids)->get() as $m) {
                $out['plan:'.$m->id] = $m;
            }
        }

        return $out;
    }

    private function priceFor(Model $model): ?float
    {
        $sale = $model->sale_price !== null ? (float) $model->sale_price : null;
        $retail = $model->retail_price !== null ? (float) $model->retail_price : null;

        return $sale ?? $retail;
    }

    private function priceSuffixFor(Model $model): ?string
    {
        if (! empty($model->price_suffix)) {
            return $model->price_suffix;
        }

        if ($model instanceof Plan) {
            return $model->billing_period?->suffix() ?: null;
        }

        return null;
    }

    private function prescribeRxIdFor(string $type, Model $model): ?string
    {
        return match ($type) {
            'product' => $model->prescribe_rx_product_id,
            'package' => $model->prescribe_rx_package_id,
            'plan' => $model->prescribe_rx_plan_id,
            default => null,
        };
    }

    private function prescribeRxNumberFor(string $type, Model $model): ?string
    {
        return match ($type) {
            'product' => $model->prescribe_rx_product_number,
            'package' => $model->prescribe_rx_package_number,
            'plan' => $model->prescribe_rx_plan_number,
            default => null,
        };
    }

    private function urlFor(string $type, string $slug): string
    {
        return match ($type) {
            'product' => '/shop/products/'.$slug,
            'package' => '/shop/packages/'.$slug,
            'plan' => '/shop/plans/'.$slug,
            default => '#',
        };
    }
}
