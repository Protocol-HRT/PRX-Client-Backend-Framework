<?php

namespace App\Models\Concerns;

use App\Models\Commerce\FulfillmentCenter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasFulfillmentCenter
{
    public function defaultFulfillmentCenter(): BelongsTo
    {
        return $this->belongsTo(FulfillmentCenter::class, 'default_fulfillment_center_id');
    }

    public function fulfillmentCenters(): MorphToMany
    {
        return $this->morphToMany(FulfillmentCenter::class, 'fulfillmentable', 'fulfillment_center_skus')
            ->withPivot(['sku', 'is_active'])
            ->withTimestamps();
    }

    /** Resolve the FC SKU for this item on a given fulfillment center. */
    public function fulfillmentSkuFor(FulfillmentCenter $fc): ?string
    {
        $pivot = $this->fulfillmentCenters()
            ->wherePivot('fulfillment_center_id', $fc->id)
            ->first();

        return $pivot?->pivot?->sku;
    }
}
