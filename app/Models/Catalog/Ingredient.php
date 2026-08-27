<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\SexEligibility;
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
        'sex_eligibility',
        'min_age',
        'max_age',
        'eligibility_note',
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
            'sex_eligibility' => SexEligibility::class,
            'min_age' => 'integer',
            'max_age' => 'integer',
        ];
    }

    /**
     * Whether this ingredient may be recommended to a visitor.
     *
     * Both arguments are nullable and a null is PERMISSIVE — "not asked" is
     * not the same as "answered nothing", and a visitor who reached a product
     * page without taking the quiz has not been asked at all. The quiz is the
     * only thing that should narrow anyone, and only once it holds an answer.
     */
    public function permits(?string $sex, ?int $age): bool
    {
        if (! $this->sex_eligibility->permits($sex)) {
            return false;
        }

        if ($age === null) {
            return true;
        }

        return ($this->min_age === null || $age >= $this->min_age)
            && ($this->max_age === null || $age <= $this->max_age);
    }

    /**
     * Narrow a query to what a visitor may be offered.
     *
     * Written as SQL rather than a filter over hydrated models because the
     * resolver walks goal -> ingredient -> product and this is the first hop:
     * excluding here means the products behind an ineligible ingredient are
     * never materialised at all.
     */
    public function scopeEligibleFor(Builder $query, ?string $sex, ?int $age): Builder
    {
        $bucket = SexEligibility::normalize($sex);

        if ($bucket !== null) {
            $query->whereIn('sex_eligibility', [SexEligibility::Any->value, $bucket->value]);
        }

        if ($age !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('min_age')->orWhere('min_age', '<=', $age))
                ->where(fn (Builder $q) => $q->whereNull('max_age')->orWhere('max_age', '>=', $age));
        }

        return $query;
    }

    /**
     * The age rule in words — "18 and over", "Under 40", "35 to 55".
     *
     * Derived, never stored, so it cannot drift from the two integers it
     * describes. Feeds the admin table, and the protocol PDF's rationale
     * alongside the operator's own `eligibility_note`.
     */
    public function ageRangeLabel(): ?string
    {
        return match (true) {
            $this->min_age === null && $this->max_age === null => null,
            $this->max_age === null => "{$this->min_age} and over",
            $this->min_age === null => "Under {$this->max_age}",
            default => "{$this->min_age} to {$this->max_age}",
        };
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
