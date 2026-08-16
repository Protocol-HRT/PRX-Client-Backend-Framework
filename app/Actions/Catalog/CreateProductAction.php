<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\ProductData;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\Auth;

class CreateProductAction
{
    use Transacts;

    public function execute(ProductData $data): Product
    {
        return $this->tx(function () use ($data) {
            $userId = Auth::id();

            $product = Product::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'subtitle' => $data->subtitle,
                'short_description' => $data->short_description,
                'description' => $data->description,
                'hero_image_path' => $data->hero_image_path,
                'gallery' => $data->gallery,
                'status' => $data->status,
                'product_class_id' => $data->product_class_id,
                'product_type_id' => $data->product_type_id,
                'product_form_id' => $data->product_form_id,
                'administration_method_id' => $data->administration_method_id,
                'volume' => $data->volume,
                'volume_unit_id' => $data->volume_unit_id,
                'inventory_status' => $data->inventory_status,
                'is_controlled_substance' => $data->is_controlled_substance,
                'rx_required' => $data->rx_required,
                'retail_price' => $data->retail_price,
                'sale_price' => $data->sale_price,
                'cost' => $data->cost,
                'price_suffix' => $data->price_suffix,
                'provider_product_id' => $data->provider_product_id,
                'provider_product_sku' => $data->provider_product_sku,
                'provider_encounter_type_id' => $data->provider_encounter_type_id,
                'badge_text' => $data->badge_text,
                'highlights' => $data->highlights,
                'is_featured' => $data->is_featured,
                'is_in_stock' => $data->is_in_stock,
                'requires_lab' => $data->requires_lab,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
                'og_image_path' => $data->og_image_path,
                'position' => $data->position,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $product->categories()->sync($data->category_ids);
            $product->tags()->sync($data->tag_ids);

            return $product->fresh();
        });
    }
}
