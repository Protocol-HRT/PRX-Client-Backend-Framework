<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\PlanData;
use App\Models\Catalog\Plan;
use Illuminate\Support\Facades\Auth;

class CreatePlanAction
{
    use Transacts;

    public function execute(PlanData $data): Plan
    {
        return $this->tx(function () use ($data) {
            $userId = Auth::id();

            $plan = Plan::create([
                'package_id' => $data->package_id,
                'name' => $data->name,
                'slug' => $data->slug,
                'subtitle' => $data->subtitle,
                'short_description' => $data->short_description,
                'description' => $data->description,
                'hero_image_path' => $data->hero_image_path,
                'gallery' => $data->gallery,
                'status' => $data->status,
                'billing_period' => $data->billing_period,
                'billing_mode' => $data->billing_mode,
                'retail_price' => $data->retail_price,
                'sale_price' => $data->sale_price,
                'intro_price' => $data->intro_price,
                'cost' => $data->cost,
                'price_suffix' => $data->price_suffix,
                'provider_plan_id' => $data->provider_plan_id,
                'provider_plan_sku' => $data->provider_plan_sku,
                'provider_product_ids' => $data->provider_product_ids,
                'badge_text' => $data->badge_text,
                'term_months' => $data->term_months,
                'is_recurring' => $data->is_recurring,
                'rebill_strategy' => $data->rebill_strategy,
                'trial_days' => $data->trial_days,
                'is_default' => $data->is_default,
                'is_featured' => $data->is_featured,
                'requires_lab' => $data->requires_lab,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
                'og_image_path' => $data->og_image_path,
                'position' => $data->position,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $plan->categories()->sync($data->category_ids);
            $plan->tags()->sync($data->tag_ids);

            return $plan->fresh();
        });
    }
}
