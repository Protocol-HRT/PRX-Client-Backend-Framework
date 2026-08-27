<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\IngredientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Kb\HealthGoal;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Ingredient extends Model implements Sortable
{
    use HasFactory, HasSlug, SoftDeletes, SortableTrait;

    protected $fillable = [
        'name',
        'slug',
        'short_name',
        'description',
        'is_active',
        'position',
        'provider_ingredient_id',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Health goals this ingredient serves — the edge the quiz recommends
     * through. Weighted on the pivot; see HealthGoal for why the edge is here
     * rather than on the compound.
     */
    public function healthGoals(): BelongsToMany
    {
        return $this->belongsToMany(HealthGoal::class, 'health_goal_ingredient')
            ->withPivot(['relevance_weight', 'evidence_level', 'is_first_line', 'relevance_note', 'position'])
            ->withTimestamps();
    }


    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(IngredientProduct::class)
            ->withPivot([
                'concentration',
                'concentration_unit_id',
                'per_volume',
                'per_volume_unit_id',
                'provider_quantity_label',
                'position',
            ])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): IngredientFactory
    {
        return IngredientFactory::new();
    }
}
