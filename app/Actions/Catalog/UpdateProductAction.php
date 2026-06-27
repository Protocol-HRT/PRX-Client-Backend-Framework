<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\ProductData;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\Auth;

class UpdateProductAction
{
    use Transacts;

    public function execute(Product $product, ProductData $data): Product
    {
        return $this->tx(function () use ($product, $data) {
            $product->update([
                'name' => $data->name,
                'slug' => $data->slug ?: Product::generateUniqueSlug($data->name, $product->id),
                'subtitle' => $data->subtitle,
                'short_description' => $data->short_description,
                'description' => $data->description,
                'hero_image_path' => $data->hero_image_path,
                'gallery' => $data->gallery,
                'status' => $data->status,
                'retail_price' => $data->retail_price,
                'sale_price' => $data->sale_price,
                'price_suffix' => $data->price_suffix,
                'prescribe_rx_product_id' => $data->prescribe_rx_product_id,
                'prescribe_rx_product_number' => $data->prescribe_rx_product_number,
                'is_featured' => $data->is_featured,
                'requires_lab' => $data->requires_lab,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
                'og_image_path' => $data->og_image_path,
                'position' => $data->position,
                'updated_by' => Auth::id(),
            ]);

            $product->categories()->sync($data->category_ids);
            $product->tags()->sync($data->tag_ids);

            return $product->fresh();
        });
    }
}
