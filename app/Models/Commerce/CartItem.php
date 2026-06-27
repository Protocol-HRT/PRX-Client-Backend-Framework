<?php

namespace App\Models\Commerce;

use App\Models\Catalog\Plan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'itemable_type',
        'itemable_id',
        'plan_id',
        'quantity',
        'unit_price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_snapshot' => 'decimal:2',
        ];
    }

    protected static function newFactory(): \Database\Factories\Commerce\CartItemFactory
    {
        return \Database\Factories\Commerce\CartItemFactory::new();
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function lineTotal(): float
    {
        return (float) ($this->unit_price_snapshot ?? 0) * $this->quantity;
    }
}
