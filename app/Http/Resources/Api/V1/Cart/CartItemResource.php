<?php

namespace App\Http\Resources\Api\V1\Cart;

use App\Http\Resources\Api\V1\Catalog\PackageResource;
use App\Http\Resources\Api\V1\Catalog\PlanResource;
use App\Http\Resources\Api\V1\Catalog\ProductResource;
use App\Models\Catalog\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->itemable_type),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price_snapshot,
            'line_total' => $this->lineTotal(),
            'item' => $this->whenLoaded('itemable', function () {
                if ($this->itemable_type === Product::class) {
                    return new ProductResource($this->itemable);
                }

                return new PackageResource($this->itemable);
            }),
            'plan' => $this->whenLoaded('plan', function () {
                return $this->plan ? new PlanResource($this->plan) : null;
            }),
        ];
    }
}
