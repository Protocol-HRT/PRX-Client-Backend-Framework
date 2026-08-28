<?php

namespace App\Models\Quiz;

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
        'quiz_step_id', 'quiz_id', 'slug', 'kind', 'prompt', 'help',
        'is_required', 'position', 'is_active', 'visible_when', 'config',
    ];

    protected function casts(): array
    {
        return [
            'kind' => QuizQuestionKind::class,
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
}
