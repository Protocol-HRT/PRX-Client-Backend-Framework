<?php

namespace App\Models\Commerce;

use App\Enums\FulfillmentCenterType;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FulfillmentCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'system_type',
        'environment',
        'is_active',
        'is_default',
        'is_default_rx',
        'is_default_non_rx',
        'prescribe_rx_fc_id',
        'street_1',
        'street_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'phone',
        'email',
        'main_contact',
        'api_endpoint',
        'api_key',
        'api_secret',
        'api_token',
        'shipstation_warehouse_id',
        'settings',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'system_type' => FulfillmentCenterType::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_default_rx' => 'boolean',
            'is_default_non_rx' => 'boolean',
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'api_token' => 'encrypted',
            'settings' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FulfillmentCenter $fc): void {
            if (blank($fc->uuid)) {
                $fc->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'fulfillmentable', 'fulfillment_center_skus')
            ->withPivot(['sku', 'is_active'])
            ->withTimestamps();
    }

    public function packages(): MorphToMany
    {
        return $this->morphedByMany(Package::class, 'fulfillmentable', 'fulfillment_center_skus')
            ->withPivot(['sku', 'is_active'])
            ->withTimestamps();
    }

    public function plans(): MorphToMany
    {
        return $this->morphedByMany(Plan::class, 'fulfillmentable', 'fulfillment_center_skus')
            ->withPivot(['sku', 'is_active'])
            ->withTimestamps();
    }
}
