<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One immutable record of a consent decision.
 *
 * APPEND-ONLY, ENFORCED IN THE MODEL rather than left to discipline. `updating`
 * and `deleting` throw. A withdrawal is a new row with `granted = false`; a
 * correction is a new row.
 *
 * THE LIMIT OF THAT GUARANTEE, stated so nobody relies on more than it gives:
 * these are Eloquent model events, so they cover every path through a model
 * INSTANCE and nothing else. `LeadConsent::query()->update(...)`,
 * `DB::table('lead_consents')->delete()` and `withoutEvents()` all bypass them,
 * because query-builder bulk operations never fire model events. Real
 * enforcement would need a database trigger or revoked UPDATE/DELETE privileges;
 * that has not been judged worth it, so the honest claim is "no path through
 * this class", not "impossible".
 *
 * `consent_text` is the sentence the human actually saw, snapshotted at capture.
 * Null means the wording is genuinely UNKNOWN and must never be read as "no
 * wording was shown". Two things currently produce null: rows backfilled from
 * before this table existed, and checkout leads — the checkout frontend still
 * hardcodes its consent labels and does not send `consent_disclosures` yet. The
 * quiz does. Closing the checkout half is outstanding.
 *
 * @see Lead::consents()
 */
class LeadConsent extends Model
{
    use HasFactory;

    /**
     * Append-only: `created_at` is written on insert, and there is no
     * `updated_at` column at all.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'channel',
        'granted',
        'consent_text',
        'consent_version',
        'source',
        'ip_address',
        'user_agent',
        'recorded_by_user_id',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'consented_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException(
                'Consent records are append-only. Record a new consent row instead of editing this one.'
            );
        });

        static::deleting(function (LeadConsent $consent): void {
            // The cascade from a deleted lead is the one legitimate removal:
            // erasing a person should not leave their consent history behind.
            // Eloquent does not route that through here, so reaching this at all
            // means someone deleted a consent row directly.
            throw new RuntimeException(
                'Consent records are append-only and cannot be deleted. Record a withdrawal instead.'
            );
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** The operator who recorded this, or null when the visitor gave it themselves. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }
}
