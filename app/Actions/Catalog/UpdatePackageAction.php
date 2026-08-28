<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\PackageData;
use App\Models\Catalog\Package;
use Illuminate\Support\Facades\Auth;

class UpdatePackageAction
{
    use Transacts;

    public function execute(Package $package, PackageData $data): Package
    {
        return $this->tx(function () use ($package, $data) {
            $package->update([
                'name' => $data->name,
                'slug' => $data->slug ?: $package->slug,
                'subtitle' => $data->subtitle,
                'short_description' => $data->short_description,
                'description' => $data->description,
                'hero_image_path' => $data->hero_image_path,
                'banner_image_path' => $data->banner_image_path,
                'gallery' => $data->gallery,
                'status' => $data->status,
                'tier' => $data->tier,
                'retail_price' => $data->retail_price,
                'sale_price' => $data->sale_price,
                'cost' => $data->cost,
                'price_suffix' => $data->price_suffix,
                'provider_package_id' => $data->provider_package_id,
                'provider_package_sku' => $data->provider_package_sku,
                'provider_encounter_type_id' => $data->provider_encounter_type_id,
                'badge_text' => $data->badge_text,
                'highlights' => $data->highlights,
                'detail_sections' => $data->detail_sections,
                'is_featured' => $data->is_featured,
                'is_in_stock' => $data->is_in_stock,
                'requires_lab' => $data->requires_lab,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
                'og_image_path' => $data->og_image_path,
                'position' => $data->position,
                'updated_by' => Auth::id(),
            ]);

            $package->categories()->sync($data->category_ids);
            $package->tags()->sync($data->tag_ids);

            return $package->fresh();
        });
    }
}
