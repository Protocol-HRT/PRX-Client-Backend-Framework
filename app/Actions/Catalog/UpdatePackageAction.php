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
                'slug' => $data->slug ?: Package::generateUniqueSlug($data->name, $package->id),
                'subtitle' => $data->subtitle,
                'short_description' => $data->short_description,
                'description' => $data->description,
                'hero_image_path' => $data->hero_image_path,
                'gallery' => $data->gallery,
                'status' => $data->status,
                'retail_price' => $data->retail_price,
                'sale_price' => $data->sale_price,
                'price_suffix' => $data->price_suffix,
                'prescribe_rx_package_id' => $data->prescribe_rx_package_id,
                'prescribe_rx_package_number' => $data->prescribe_rx_package_number,
                'is_featured' => $data->is_featured,
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
