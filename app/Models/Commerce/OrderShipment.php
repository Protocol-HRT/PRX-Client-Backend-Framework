<?php

namespace App\Models\Commerce;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'prescribe_rx_shipment_id',
        'status',
        'carrier',
        'tracking_number',
        'tracking_url',
        'fulfillment_center',
        'shipped_at',
        'delivered_at',
        'exception_at',
        'exception_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'exception_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'order_shipment_items')
            ->withPivot('quantity');
    }
}
