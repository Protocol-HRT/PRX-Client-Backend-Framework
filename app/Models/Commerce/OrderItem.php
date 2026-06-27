<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'prescribe_rx_product_id',
        'prescribe_rx_product_number',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
        'billing_period',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            // Product name encrypted at rest — when paired with shipping
            // address it can imply a prescribed treatment.
            'name' => 'encrypted',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(OrderShipment::class, 'order_shipment_items')
            ->withPivot('quantity');
    }
}
