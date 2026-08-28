<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One typed step in a workflow: an action type plus its configuration.
 *
 * The config blob is what lets "send an email", "fire a webhook" and "push to a
 * CRM" live in one table. The shape of it belongs to the handler, not here.
 */
class WorkflowAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'name', 'action_type', 'config',
        'is_active', 'sort_order', 'halt_on_failure',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'halt_on_failure' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
