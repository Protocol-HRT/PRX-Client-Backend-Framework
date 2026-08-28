<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempted step of one run, so a failure names itself.
 *
 * `action_type` is copied rather than read through the relation because the
 * action row may be deleted later, and a log that forgets what it did is not a
 * log.
 */
class WorkflowActionRun extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'workflow_run_id', 'workflow_action_id', 'action_type',
        'status', 'output', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'output' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(WorkflowAction::class, 'workflow_action_id');
    }
}
