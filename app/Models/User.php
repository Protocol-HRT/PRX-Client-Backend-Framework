<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Referral\ReferralSource;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'invited_at',
        'referral_source_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Filament panel gate. Two checks:
     *  - Account must be active (admin can soft-disable without deletion).
     *  - User must have at least one role (Shield super_admin grants all,
     *    other roles grant scoped resource access via policies).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // AUDIENCE IS A TYPE BOUNDARY, NOT A PERMISSION. Whether someone is a
        // partner is decided by `referral_source_id`, never by a role — so this
        // holds even if an operator later assigns a partner a staff role, or
        // ticks a permission onto a partner role. That is exactly the
        // distinction prescribe-rx draws between its portals, and it is why the
        // two panels are mutually exclusive rather than layered.
        //
        // A partner in the staff admin would see patient PII, credentials,
        // margin and every other partner's numbers. Staff in the partner portal
        // would see a dashboard scoped to an organisation they do not have,
        // which resolves to nothing — pointless rather than dangerous, but
        // denying it keeps the rule symmetrical and easy to state.
        $isPartnerPanel = $panel->getId() === 'partner';

        if ($isPartnerPanel !== $this->isPartner()) {
            return false;
        }

        return $this->roles()->exists();
    }

    /** Attached to a referral organisation, therefore not staff. */
    public function isPartner(): bool
    {
        return $this->referral_source_id !== null;
    }

    /**
     * Roles that may never be held by someone attached to a referral
     * organisation.
     *
     * WHY THIS EXISTS AT ALL. `canAccessPanel()` keeps the two audiences apart,
     * and Horizon's gate checks `isPartner()` too — but Shield registers a
     * `Gate::before` that grants `super_admin` EVERY ability, short-circuiting
     * both. So "a partner with a staff role still can't get in" is only true if
     * that combination cannot exist in the first place. It is enforced on save
     * rather than in a form, because a form is one of several writers.
     *
     * @var list<string>
     */
    public const STAFF_ONLY_ROLES = ['super_admin', 'admin', 'catalog_manager', 'content_editor', 'support'];

    /**
     * True when this user is a partner holding a role reserved for staff.
     */
    public function hasConflictingStaffRole(): bool
    {
        return $this->isPartner()
            && $this->roles()->whereIn('name', self::STAFF_ONLY_ROLES)->exists();
    }

    public function referralSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class, 'referral_source_id');
    }

    /**
     * Every referral source this user may see: their own organisation and its
     * entire downline. Staff get null, meaning "not scoped by this at all".
     *
     * THE SINGLE CHOKEPOINT. Every partner-facing query resolves visibility
     * through here, so widening or narrowing what a partner sees is one edit
     * rather than an audit of every screen.
     *
     * @return list<int>|null
     */
    public function visibleReferralSourceIds(): ?array
    {
        if (! $this->isPartner()) {
            return null;
        }

        $source = $this->referralSource;

        // Attached to a source that has since been deleted: no rows, never all
        // rows. A partner whose org vanished is the case where a permissive
        // default leaks every other partner's numbers.
        return $source?->descendantAndSelfIds() ?? [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
