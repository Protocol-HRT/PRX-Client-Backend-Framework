<?php

namespace App\Models\Quiz;

use App\Enums\Privacy\DataClassification;
use App\Enums\Quiz\QuizQuestionKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One question. `slug` is the key its answer is filed under, for the life of
 * the quiz — see the migration.
 */
class QuizQuestion extends Model
{
    protected $fillable = [
        'quiz_step_id', 'quiz_id', 'slug', 'kind', 'data_class', 'prompt', 'help',
        'is_required', 'position', 'is_active', 'visible_when', 'config',
    ];

    protected function casts(): array
    {
        return [
            'kind' => QuizQuestionKind::class,
            'data_class' => DataClassification::class,
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'visible_when' => 'array',
            'config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // quiz_id is denormalised from the step and must never be set by hand
        // — an admin form that offered it could file a question under a quiz
        // its own step does not belong to, and the unique(quiz_id, slug)
        // guarantee would then be protecting the wrong thing.
        static::saving(function (self $question): void {
            if ($question->quiz_step_id) {
                $question->quiz_id = QuizStep::whereKey($question->quiz_step_id)->value('quiz_id');
            }
        });
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(QuizStep::class, 'quiz_step_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class)->orderBy('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * How sensitive this answer is, resolving the operator's choice against the
     * kind's default.
     *
     * ALWAYS ASK THIS, never read `data_class` directly. Null there means "not
     * classified by hand", which is the state almost every question is in — and
     * a caller reading the raw column would treat the commonest case as
     * unclassified rather than as the kind's default, which is how a health
     * question ends up looking like general data to a field mapper.
     */
    public function effectiveDataClass(): DataClassification
    {
        return $this->data_class ?? $this->kind->defaultDataClassification();
    }

    /** Whether the operator classified this by hand, or it is inheriting. */
    public function isDataClassExplicit(): bool
    {
        return $this->data_class !== null;
    }
}
