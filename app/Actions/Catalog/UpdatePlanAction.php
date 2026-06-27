<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\PlanData;
use App\Models\Catalog\Plan;
use Illuminate\Support\Facades\Auth;

class UpdatePlanAction
{
    use Transacts;

    public function execute(Plan $plan, PlanData $data): Plan
    {
        return $this->tx(function () use ($plan, $data) {
            $plan->update([
                'package_id' => $data->package_id,
                'name' => $data->name,
                'slug' => $data->slug ?: Plan::generateUniqueSlug($data->name, $plan->id),
                'subtitle' => $data->subtitle,
                'short_description' => $data->short_description,
                'description' => $data->description,
                'hero_image_path' => $data->hero_image_path,
                'gallery' => $data->gallery,
                'status' => $data->status,
                'billing_period' => $data->billing_period,
                'retail_price' => $data->retail_price,
                'sale_price' => $data->sale_price,
                'price_suffix' => $data->price_suffix,
                'prescribe_rx_plan_id' => $data->prescribe_rx_plan_id,
                'prescribe_rx_plan_number' => $data->prescribe_rx_plan_number,
                'is_featured' => $data->is_featured,
                'requires_lab' => $data->requires_lab,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
                'og_image_path' => $data->og_image_path,
                'position' => $data->position,
                'updated_by' => Auth::id(),
            ]);

            $plan->categories()->sync($data->category_ids);
            $plan->tags()->sync($data->tag_ids);

            return $plan->fresh();
        });
    }
}
