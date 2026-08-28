<?php

namespace App\Models\Quiz;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One screen of the quiz.
 *
 * A step can be conditional in its own right, not only its questions: the
 * "roughly where are you today" screen is asked for some goals and skipped
 * for others, and hiding its questions one by one would leave an empty screen
 * with a Continue button.
 */
class QuizStep extends Model
{
    protected $fillable = [
        'quiz_id', 'slug', 'name', 'heading', 'description',
        'position', 'is_active', 'visible_when',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'visible_when' => 'array'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
