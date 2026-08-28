<?php

namespace App\Models\Integrations;

use App\Workflows\Actions\PushToIntegrationAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One record's identifier at one destination.
 *
 * Written by the workflow action immediately after a driver returns an id, never
 * by a driver: drivers are stateless HTTP and knowing about our tables would
 * make every new one responsible for remembering to persist.
 *
 * @see PushToIntegrationAction
 * @see IntegrationInstance::identities()
 */
class IntegrationIdentity extends Model
{
    protected $fillable = [
        'integration_instance_id',
        'subject_type',
        'subject_id',
        'remote_id',
        'last_pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_pushed_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(IntegrationInstance::class, 'integration_instance_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record what this destination now calls this subject.
     *
     * An upsert, because a re-push is the normal case and a second row for the
     * same pair would leave two answers to a question with one. The remote id
     * itself can legitimately CHANGE — a vendor merging two profiles hands back
     * the surviving one — so the newest answer wins rather than being rejected
     * as a conflict.
     */
    public static function record(IntegrationInstance $instance, object $subject, string $remoteId): self
    {
        return static::query()->updateOrCreate(
            [
                'integration_instance_id' => $instance->getKey(),
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ],
            [
                'remote_id' => $remoteId,
                'last_pushed_at' => now(),
            ],
        );
    }
}
