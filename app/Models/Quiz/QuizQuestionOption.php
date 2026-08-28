<?php

namespace App\Models\Quiz;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One choice. `price_source` names where a live price range is computed FROM;
 * the range itself is never stored, because an authored price next to a buying
 * decision goes stale silently.
 */
class QuizQuestionOption extends Model
{
    protected $fillable = [
        'quiz_question_id', 'value', 'label', 'description', 'icon',
        'is_exclusive', 'price_source', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_exclusive' => 'boolean', 'is_active' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
