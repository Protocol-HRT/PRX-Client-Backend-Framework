<?php

namespace App\Models\Quiz;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A named intake quiz. `/quiz` runs the default one.
 *
 * `is_default` is a column rather than a settings key pointing at an id,
 * because a settings key can outlive the row it names and there is no good
 * behaviour for "the configured quiz was deleted". Enforced as at-most-one by
 * the model on save.
 */
class Quiz extends Model
{
    use HasSlug, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'is_active', 'is_default',
        'result_heading', 'result_intro', 'result_restricted_body',
        'result_unmapped_body', 'result_empty_body',
    ];

    /**
     * The results-page copy, in the shape the plan endpoint serves it.
     *
     * Grouped here rather than assembled at the call site so the frontend
     * contract has one owner: adding a state to the results page means adding
     * a column and a key here, and every consumer sees it at once.
     *
     * @return array<string, string|null>
     */
    public function resultCopy(): array
    {
        return [
            'heading' => $this->result_heading,
            'intro' => $this->result_intro,
            'restricted' => $this->result_restricted_body,
            'unmapped' => $this->result_unmapped_body,
            'empty' => $this->result_empty_body,
        ];
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite();
    }

    protected static function booted(): void
    {
        // Exactly one default. Demoting the others on save is the only way to
        // keep that true without a partial unique index, which MySQL lacks —
        // and a UI that merely *asks* an operator to unset the old one leaves
        // two defaults the first time someone forgets.
        static::saved(function (self $quiz): void {
            if ($quiz->is_default) {
                static::query()
                    ->whereKeyNot($quiz->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function steps(): HasMany
    {
        return $this->hasMany(QuizStep::class)->orderBy('position');
    }

    /** Every question in the quiz, ordered by step then position. */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The quiz `/quiz` runs: the default one, or the oldest active one when
     * nobody has marked a default. Falling back rather than returning null
     * means a fresh install with one quiz works before anyone touches a
     * toggle.
     */
    public static function resolveDefault(): ?self
    {
        return static::query()->active()->where('is_default', true)->first()
            ?? static::query()->active()->orderBy('id')->first();
    }
}
