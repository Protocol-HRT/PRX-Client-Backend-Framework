<?php

namespace App\Http\Resources\Api\V1\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'short_description' => $this->short_description,
            'badge_text' => $this->badge_text,
            'status' => $this->status->value,
            'is_default' => $this->is_default,
            'sort_order' => $this->position,
            'price' => [
                'retail' => $this->retail_price,
                'sale' => $this->sale_price,
                'effective' => $this->sale_price ?? $this->retail_price,
                'suffix' => $this->price_suffix ?? $this->billing_period?->suffix(),
            ],
            'billing' => [
                'period' => $this->billing_period?->value,
                'period_label' => $this->billing_period?->label(),
                'term_months' => $this->term_months,
                'is_recurring' => $this->is_recurring,
                'rebill_strategy' => $this->rebill_strategy?->value,
                'rebill_strategy_label' => $this->rebill_strategy?->label(),
                'trial_days' => $this->trial_days,
            ],
            'requires_lab' => $this->requires_lab,
            'provider' => [
                'plan_id' => $this->provider_plan_id,
                'plan_sku' => $this->provider_plan_sku,
                'product_ids' => $this->provider_product_ids ?? [],
            ],
        ];
    }
}
