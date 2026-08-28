<?php

namespace App\Models\Workflow;

use App\Workflows\WorkflowRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * "When this happens and these things are true, do that."
 *
 * `trigger_target` is a REGISTRY KEY, never a class name — see WorkflowRegistry
 * for why that distinction is the security boundary of this feature.
 *
 * `conditions` reuses the VisibleWhen shape the CMS and quiz already use, so an
 * operator learns one condition vocabulary for the whole product.
 */
class Workflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'slug', 'description',
        'trigger_type', 'trigger_target', 'conditions',
        'is_active', 'priority', 'stop_on_first_match',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
            'stop_on_first_match' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workflow $workflow): void {
            if (blank($workflow->uuid)) {
                $workflow->uuid = (string) Str::uuid();
            }
            if (blank($workflow->slug)) {
                $workflow->slug = Str::slug($workflow->name ?: (string) Str::uuid());
            }
        });
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('sort_order')->orderBy('id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class)->latest('id');
    }

    /**
     * Active workflows for one trigger, in the order they must run.
     *
     * Ordered by priority then id so the sequence is TOTAL — two workflows at the
     * same priority would otherwise run in whatever order the database returned,
     * which makes `stop_on_first_match` non-deterministic and the resulting bug
     * impossible to reproduce.
     */
    public static function forTrigger(string $type, string $target)
    {
        return static::query()
            ->where('is_active', true)
            ->where('trigger_type', $type)
            ->where('trigger_target', $target)
            ->orderBy('priority')
            ->orderBy('id');
    }

    /** The subject registry key this workflow's conditions read against. */
    public function subjectKey(): ?string
    {
        $registry = app(WorkflowRegistry::class);

        if ($this->trigger_type === 'event_fired') {
            return $registry->event($this->trigger_target)['subject'] ?? null;
        }

        return $registry->subject($this->trigger_target) !== null ? $this->trigger_target : null;
    }
}
