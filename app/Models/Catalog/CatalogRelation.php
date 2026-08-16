<?php

namespace App\Models\Catalog;

use App\Enums\CatalogRelationType;
use Database\Factories\Catalog\CatalogRelationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * Double-polymorphic link between catalog items: a Product or Package
 * (source) points at another Product or Package (related) with a typed
 * relationship — "related" (similar items) or "pairs_with" (suggested
 * companions / stack-builders).
 */
class CatalogRelation extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    protected $fillable = [
        'source_type',
        'source_id',
        'related_type',
        'related_id',
        'relation_type',
        'position',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'relation_type' => CatalogRelationType::class,
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function buildSortQuery()
    {
        return static::query()
            ->where('source_type', $this->source_type)
            ->where('source_id', $this->source_id)
            ->where('relation_type', $this->relation_type);
    }

    protected static function newFactory(): CatalogRelationFactory
    {
        return CatalogRelationFactory::new();
    }
}
