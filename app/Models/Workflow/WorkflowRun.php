<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One evaluation of one workflow — INCLUDING THE ONES THAT DID NOT MATCH.
 *
 * Recording skips is the point. "Why didn't my workflow fire?" is the question
 * operators actually ask, and a log of successes cannot answer it; `skip_reason`
 * names the condition that rejected the run, and `context` snapshots what it was
 * judged against so the answer survives the subject moving on.
 */
class WorkflowRun extends Model
{
    use HasFactory;

    /** In flight. A row left on this after a crash is visibly incomplete. */
    public const STATUS_RUNNING = 'running';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'workflow_id', 'subject_type', 'subject_id',
        'trigger_type', 'status', 'skip_reason', 'context', 'error',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkflowRun $run): void {
            if (blank($run->uuid)) {
                $run->uuid = (string) Str::uuid();
            }
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionRuns(): HasMany
    {
        return $this->hasMany(WorkflowActionRun::class)->orderBy('id');
    }
}
