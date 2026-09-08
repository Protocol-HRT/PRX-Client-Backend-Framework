<?php

namespace App\Models\Referral;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One node's earnings on one conversion. See the migration for why the rate is
 * snapshotted rather than joined.
 */
class ReferralCommission extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'uuid', 'lead_id', 'referral_source_id', 'source_slug', 'source_name',
        'referral_code', 'tier', 'basis_amount', 'rate', 'amount', 'status',
        'approved_at', 'paid_at', 'payment_reference',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'basis_amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class, 'referral_source_id');
    }

    /** Everything still owed — anything not paid out and not written off. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
