<?php

namespace App\Models\Catalog;

use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\RebillStrategy;
use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Plan extends Model implements Sortable
{
    use GeneratesUniqueSlug, HasFactory, SoftDeletes, SortableTrait;

    protected $fillable = [
        'package_id',
        'name',
        'slug',
        'subtitle',
        'short_description',
        'description',
        'hero_image_path',
        'gallery',
        'status',
        'billing_period',
        'retail_price',
        'sale_price',
        'price_suffix',
        'provider_plan_id',
        'provider_plan_sku',
        'badge_text',
        'term_months',
        'is_recurring',
        'rebill_strategy',
        'trial_days',
        'is_default',
        'provider_product_ids',
        'is_featured',
        'requires_lab',
        'meta_title',
        'meta_description',
        'og_image_path',
        'position',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => CatalogStatus::class,
            'billing_period' => BillingPeriod::class,
            'gallery' => 'array',
            'retail_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_featured' => 'boolean',
            'requires_lab' => 'boolean',
            'is_recurring' => 'boolean',
            'is_default' => 'boolean',
            'rebill_strategy' => RebillStrategy::class,
            'provider_product_ids' => 'array',
            'term_months' => 'integer',
        ];
    }

    protected static function newFactory(): \Database\Factories\Catalog\PlanFactory
    {
        return \Database\Factories\Catalog\PlanFactory::new();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', CatalogStatus::Published->value);
    }

    public function isPublished(): bool
    {
        return $this->status === CatalogStatus::Published;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
