<?php

namespace App\Http\Resources\Api\V1\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->ulid,
            'email' => $this->email,
            'coupon_code' => $this->coupon_code,
            'item_count' => $this->itemCount(),
            'subtotal' => $this->subtotal(),
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}
