<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\MeasurementUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class MeasurementUnit extends Model implements Sortable
{
    use HasFactory, SoftDeletes, SortableTrait;

    protected $fillable = [
        'name',
        'abbreviation',
        'is_active',
        'position',
        'provider_value',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'provider_value' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): MeasurementUnitFactory
    {
        return MeasurementUnitFactory::new();
    }
}
