<?php

namespace App\Models\Integrations;

use App\Models\User;
use App\Models\Workflow\WorkflowAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One configured integration: a vendor, this operator's credentials for it, and
 * the capabilities they have switched on.
 *
 * ─── This is the layer that names vendors ──────────────────────────────
 *
 * `provider` is a key from `IntegrationRegistry` — 'klaviyo', 'twilio',
 * 'local_mail'. Never a class name: these rows are operator-editable, and a
 * class name in an editable row that later gets instantiated is arbitrary class
 * instantiation in a product many companies deploy. Same security boundary as
 * `WorkflowRegistry`'s, for the same reason.
 *
 * ─── Capabilities are the operator's, not the driver's ─────────────────
 *
 * A driver declares what it CAN do by implementing capability interfaces. This
 * row declares what the operator has authorised it FOR — one Twilio account may
 * be cleared for SMS but not voice. What an action is actually offered is the
 * intersection, checked in two places on purpose: at save time so the form
 * cannot store nonsense, and again at run time so a capability withdrawn after a
 * workflow was authored fails loudly instead of silently doing nothing.
 *
 * ─── Why the slug is guarded ───────────────────────────────────────────
 *
 * Workflow actions reference an instance by slug inside a JSON `config` column,
 * so the database cannot hold that relationship for us. This project has met the
 * consequence twice — a renamed palette colour blanks every section using it, a
 * re-slugged disposition orphans its workflows — and the lesson each time was
 * the same: WHEN REFERENCES ARE BY NAME, A RENAME IS A REMOVAL. So both are
 * blocked while anything points here, and the operator deactivates instead.
 */
class IntegrationInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'provider',
        'is_active',
        'capabilities',
        'credentials',
        'settings',
        // `phi_permitted` is deliberately ABSENT. It is a cache of the newest
        // attestation, and mass-assigning it would mint a permission with no
        // attestation row behind it — the exact thing this design exists to
        // prevent. attestPhi() writes it with forceFill().
    ];

    /**
     * `credentials` is `encrypted:array`; everything about it is server-side.
     *
     * It is never rendered as a key/value editor. A whole-blob editor shows
     * every secret at once, cannot mask one field, and — worse — writes the
     * whole blob back on save, so a masked placeholder would overwrite the real
     * value with the mask. The driver declares its credential fields instead and
     * each becomes its own masked input, as MerchantAccountForm already does.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capabilities' => 'array',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'phi_permitted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $instance): void {
            $instance->uuid ??= (string) Str::uuid();
            $instance->slug ??= Str::slug($instance->name);
        });

        static::updating(function (self $instance): void {
            if (! $instance->isDirty('slug')) {
                return;
            }

            $used = static::referenceCount($instance->getOriginal('slug'));

            if ($used > 0) {
                throw new RuntimeException(
                    "This integration is used by {$used} workflow action(s), which refer to it by its "
                    .'identifier. Renaming the identifier would silently disconnect them. Change the '
                    .'display name instead, or remove it from those actions first.'
                );
            }
        });

        static::deleting(function (self $instance): void {
            // A soft delete is still a disappearance from the palette, and the
            // actions pointing here would fail at run time with no explanation.
            $used = static::referenceCount($instance->slug);

            if ($used > 0) {
                throw new RuntimeException(
                    "This integration is used by {$used} workflow action(s). Switch it off instead of "
                    .'deleting it, or remove it from those actions first.'
                );
            }
        });
    }

    /** @return HasMany<IntegrationPhiAttestation> */
    public function attestations(): HasMany
    {
        return $this->hasMany(IntegrationPhiAttestation::class)->latest('id');
    }

    /**
     * What this destination calls the records we have pushed to it.
     *
     * Survives a soft delete on purpose — switching a destination off must not
     * lose the ids, or turning it back on would create every profile again.
     *
     * @return HasMany<IntegrationIdentity>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(IntegrationIdentity::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Instances the operator has enabled for a given capability.
     *
     * The action palette's whole question. A JSON containment scan over a table
     * that holds single digits of rows costs nothing, and buys a capability
     * vocabulary that can grow without a migration.
     */
    public function scopeOffering(Builder $query, string $capability): Builder
    {
        return $query->whereJsonContains('capabilities', $capability);
    }

    public function offers(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    /**
     * Record an operator's attestation about health data, and cache the result.
     *
     * ALWAYS THROUGH HERE, never by writing `phi_permitted` directly. The boolean
     * on this row is a cache of the newest row in an append-only history; setting
     * it alone produces a permission nobody is recorded as having granted, which
     * is the one thing this design exists to prevent.
     */
    public function attestPhi(bool $permitted, ?string $note = null, ?User $by = null): IntegrationPhiAttestation
    {
        // One transaction: a cached flag that disagrees with the newest row is
        // either a permission nobody granted or a withdrawal that did not take.
        return DB::transaction(function () use ($permitted, $note, $by): IntegrationPhiAttestation {
            $attestation = $this->attestations()->create([
                'permitted' => $permitted,
                'note' => $note,
                'attested_by_user_id' => $by?->getKey() ?? auth()->id(),
                'created_at' => now(),
            ]);

            $this->forceFill(['phi_permitted' => $permitted])->save();

            return $attestation;
        });
    }

    /** How many workflow actions point at this slug. */
    private static function referenceCount(?string $slug): int
    {
        if ($slug === null || $slug === '') {
            return 0;
        }

        // The reference lives inside a JSON column, so this is a scan rather than
        // a join. Workflow actions number in the dozens at most.
        return WorkflowAction::query()
            ->get(['id', 'config'])
            ->filter(fn (WorkflowAction $action): bool => ($action->config['integration'] ?? null) === $slug)
            ->count();
    }
}
