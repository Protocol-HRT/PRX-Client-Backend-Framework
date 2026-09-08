<?php

namespace App\Models\Referral;

use App\Settings\BrandSettings;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A trackable code belonging to one referral source.
 *
 * Codes are stored and compared LOWERCASE. A referral code is typed by humans off
 * printed material and pasted between systems, so treating `LT-4F2A` and `lt-4f2a`
 * as different codes would drop commissions for a reason no one could see. The
 * column is `unique`, so normalising on write is what makes that guarantee real —
 * `resolve()` lowercases too, and the two must stay in step.
 *
 * There are no click or conversion counters here on purpose; see the migration.
 */
class ReferralLink extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'referral_source_id',
        'code',
        'campaign_name',
        'destination_path',
        'expires_at',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->uuid ??= (string) Str::uuid();
        });

        // Normalise on every write, not just creation — an admin editing the code
        // must land in the same case space as the resolver.
        static::saving(function (self $link): void {
            if ($link->code !== null) {
                // Same normaliser the resolver uses — the two must never drift,
                // or the unique index and lookups disagree.
                $link->code = static::normalizeCode($link->code) ?? $link->code;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class, 'referral_source_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    /**
     * Usable right now: active, unexpired, and belonging to an active source.
     *
     * The source check is why this is a scope rather than an accessor — a
     * deactivated affiliate must stop earning immediately, and their links are
     * not individually switched off when that happens.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('source', fn ($q) => $q->where('is_active', true));
    }

    /**
     * The full URL to hand an affiliate.
     *
     * Built from `BrandSettings::$site_url` — this backend drives a decoupled
     * storefront and must not invent its own origin. `destination_path` lets one
     * partner point at a specific page (a stack, a goal) rather than the home
     * page; the `ref` param is appended either way, and appended CORRECTLY when
     * the path already carries a query string.
     */
    public function url(): ?string
    {
        $base = rtrim((string) app(BrandSettings::class)->site_url, '/');

        if ($base === '') {
            return null;
        }

        $path = '/'.ltrim((string) ($this->destination_path ?? ''), '/');
        $separator = str_contains($path, '?') ? '&' : '?';

        return $base.$path.$separator.'ref='.rawurlencode((string) $this->code);
    }

    /**
     * The link's URL as a QR code, an SVG data URI.
     *
     * SVG rather than PNG because these get printed — on flyers, table tents,
     * pull-up banners — and a raster code that looks fine on screen is the one
     * that fails to scan at A3. Rendering is deterministic from the URL, so
     * nothing is stored: a QR code is a VIEW of the code, not another record
     * that could drift out of step with it.
     */
    public function qrCodeSvg(): ?string
    {
        $url = $this->url();

        if ($url === null) {
            return null;
        }

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            // High correction, because a printed code gets creased, and a logo
            // may be dropped in the middle later.
            'eccLevel' => QRCode::ECC_H,
            'scale' => 8,
            'imageBase64' => true,
        ]);

        return (new QRCode($options))->render($url);
    }

    /**
     * The single normalisation for a referral code, everywhere.
     *
     * Lowercase FIRST, then bound — never the other way round. Unicode
     * lowercasing can LENGTHEN a string (`İ` U+0130 becomes `i` + U+0307), so
     * bounding first lets a 64-character code become 128 afterwards, which then
     * fails `max:64` validation on every later lead submit and overflows the
     * varchar(64) column. That is not theoretical: it makes a crafted `?ref=`
     * link a denial of checkout for whoever clicks it, for as long as the cookie
     * lives.
     *
     * Returns null for anything that cannot be a real code — empty, or still too
     * long once lowercased. Callers treat null as "no referral", never as an
     * error, because attribution may not fail the thing it is attached to.
     */
    public static function normalizeCode(?string $code): ?string
    {
        $code = Str::lower(trim((string) $code));

        if ($code === '' || mb_strlen($code) > 64) {
            return null;
        }

        return $code;
    }

    /**
     * Look up a usable link by its public code.
     *
     * Returns null for unknown, expired, inactive or deactivated-source codes —
     * all four are the same thing to a visitor, and the click is still recorded
     * against the raw code either way, so a mistyped or retired code is
     * answerable later rather than vanishing.
     */
    public static function resolve(?string $code): ?self
    {
        $code = static::normalizeCode($code);

        if ($code === null) {
            return null;
        }

        return static::usable()->where('code', $code)->first();
    }
}
