<?php

namespace App\Models\Integrations;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One immutable record that an operator declared a destination fit — or unfit —
 * to receive health data.
 *
 * THIS IS AN ATTESTATION, NOT A VERIFICATION, and the distinction is the whole
 * point of the table. Nothing here can check whether a BAA exists; all it can
 * record is that a named person said so, in writing, at a known moment. That is
 * also all that is needed later — the question asked after an incident is never
 * "was there a BAA" (the contract answers that), it is "who turned this on, and
 * when, and what did they believe they were relying on".
 *
 * APPEND-ONLY, ENFORCED IN THE MODEL, exactly as `LeadConsent` is. A revocation
 * is a new row with `permitted = false`; a correction is a new row. The same
 * honest limit applies: these are Eloquent model events, so they cover every
 * path through a model instance and nothing else — a query-builder `update()` or
 * `withoutEvents()` bypasses them. Real enforcement would need a database
 * trigger or revoked privileges. The claim is "no path through this class", not
 * "impossible".
 *
 * `permitted = false` is a genuine record, not an absence. "Revoked on the 3rd"
 * and "never attested" are different facts and must never collapse into one.
 *
 * @see IntegrationInstance::attestPhi()
 * @see App\Enums\Privacy\DataClassification
 */
class IntegrationPhiAttestation extends Model
{
    use HasFactory;

    /** Append-only: written once, and there is no `updated_at` column at all. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'integration_instance_id',
        'permitted',
        'note',
        'attested_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'permitted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException(
                'PHI attestations are append-only. Record a new attestation instead of editing this one.'
            );
        });

        static::deleting(function (): void {
            // The cascade from a deleted instance is the one legitimate removal,
            // and Eloquent does not route that through here — so reaching this
            // means someone deleted an attestation directly.
            throw new RuntimeException(
                'PHI attestations are append-only and cannot be deleted. Record a revocation instead.'
            );
        });
    }

    /** @return BelongsTo<IntegrationInstance, self> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(IntegrationInstance::class, 'integration_instance_id');
    }

    /** @return BelongsTo<User, self> */
    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attested_by_user_id');
    }
}
