<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'user_id',
        'email',
        'coupon_code',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Cart $cart): void {
            $cart->ulid ??= (string) Str::ulid();
            $cart->expires_at ??= now()->addDays(30);
        });
    }

    protected static function newFactory(): \Database\Factories\Commerce\CartFactory
    {
        return \Database\Factories\Commerce\CartFactory::new();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function isEmpty(): bool
    {
        return $this->items()->doesntExist();
    }

    public function itemCount(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function subtotal(): float
    {
        return (float) $this->items()
            ->whereNotNull('unit_price_snapshot')
            ->get()
            ->sum(fn ($i) => $i->unit_price_snapshot * $i->quantity);
    }
}
