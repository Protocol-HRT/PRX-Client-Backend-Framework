<?php

namespace App\Models\Referral;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One referral arrival. The ledger a commission dispute is settled from.
 *
 * NO SoftDeletes and NO prunable, deliberately — an event that happened is never
 * edited and never reaped. Note the contrast with `Commerce\Cart`, which IS pruned
 * at 90 days: that is precisely why attribution does not live on the cart, and why
 * `Cart::prunable()` needs no new guard for referrals. Nothing here depends on a
 * cart surviving.
 *
 * `code` and `source_slug` duplicate the two foreign keys on purpose. When a
 * source is eventually deleted the FKs go null and these still name who was
 * credited — "reconstructable after the fact" has to mean after the rows it
 * pointed at are gone.
 */
class ReferralClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'referral_link_id',
        'referral_source_id',
        'code',
        'source_slug',
        'visitor_id',
        'ip_address',
        'user_agent',
        'referrer',
        'landing_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'clicked_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $click): void {
            $click->uuid ??= (string) Str::uuid();
            $click->clicked_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(ReferralLink::class, 'referral_link_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class, 'referral_source_id');
    }

    /** Leads attributed to this exact click — the conversion half of the ledger. */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'referral_click_id');
    }
}
