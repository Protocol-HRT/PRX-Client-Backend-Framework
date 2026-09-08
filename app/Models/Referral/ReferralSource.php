<?php

namespace App\Models\Referral;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * Who gets credited: an affiliate, a sales group, a partner.
 *
 * Attribution is entirely local — see the migration for why riding on
 * prescribe-rx's sales-org tree was never available, and why a generic backend
 * could not have shipped it anyway.
 *
 * `parent_id` is an ARBITRARY-DEPTH tree, via the same package prescribe-rx uses
 * (`staudenmeir/laravel-adjacency-list`), so the two systems describe a sales
 * organisation the same way and anyone moving between them reads one model. A
 * group owns sub-groups owns individual affiliates, as deep as the commercial
 * structure goes. It was one level in the first cut and that was wrong: a real
 * sales org has managers under managers, and flattening them makes "my whole
 * downline's numbers" unanswerable.
 *
 * The package gives `descendants()`, `descendantsAndSelf()`, `ancestors()` and a
 * `depth` on every recursive query — real Eloquent relations backed by ONE
 * recursive CTE. That matters beyond tidiness: scoping composes into the query
 * (`whereIn(... ->select('id'))`) instead of loading an id array into PHP first,
 * so a wide tree costs one round trip rather than one per level.
 *
 * `scopeVisibleTo` is the one place that fans out, so "what may this portal user
 * see" has a single answer rather than one per resource.
 */
class ReferralSource extends Model
{
    use HasFactory, HasRecursiveRelationships, SoftDeletes;

    protected $fillable = [
        'uuid',
        'type',
        'name',
        'slug',
        'parent_id',
        'code_prefix',
        'company_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'commission_rate',
        'commission_notes',
        'portal_enabled',
        'is_active',
        'settings',
    ];

    /**
     * CYCLE DETECTION ON — this is not optional here, and it is off by default.
     *
     * A recursive CTE over a cycle does not return an empty set, it recurses
     * until the driver stops it: on MySQL that is an error, on SQLite it simply
     * does not terminate. It hung the test suite the moment the hand-rolled walk
     * (which carried its own `$seen` guard) was replaced by the CTE.
     *
     * `preventCycles()` on `saving` is the first line and should mean this never
     * fires. It is the second line, for a cycle that arrives some other way — a
     * raw query, an import, a restored backup — because an admin page that hangs
     * is worse than one that shows a truncated tree.
     */
    public function enableCycleDetection(): bool
    {
        return true;
    }

    protected static function booted(): void
    {
        static::creating(function (self $source): void {
            $source->uuid ??= (string) Str::uuid();
        });

        static::saving(fn (self $source) => static::preventCycles($source));

        // Any write can reshape the tree, so the downline memo cannot outlive one.
        static::saved(fn () => static::forgetDownlines());
        static::deleted(fn () => static::forgetDownlines());
    }

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            // Cast so preventCycles' strict comparisons cannot be defeated by a
            // string id from an import or an API writer.
            'parent_id' => 'integer',
            'portal_enabled' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The people attached to this organisation — its reps.
     *
     * Direct members only. "Everyone in my downline" is
     * `User::whereIn('referral_source_id', $source->descendantAndSelfIds())`,
     * deliberately not a relation: a parent listing its whole downline's staff
     * is a report, and making it a relation invites it into an eager load on
     * pages that only wanted the org's own team.
     */
    public function reps(): HasMany
    {
        return $this->hasMany(User::class, 'referral_source_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ReferralLink::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    /** @var array<int, list<int>> */
    private static array $downlineMemo = [];

    /**
     * Every id at or beneath this source — the whole downline.
     *
     * Backed by the package's recursive CTE, so this is ONE query at any depth.
     * Kept as a method returning ids because most callers want to feed a
     * `whereIn` against another table (clicks, leads), where a relation would not
     * help; `descendantsAndSelf()` is there for when a real relation is wanted.
     *
     * MEMOISED PER PROCESS, because a single dashboard render asks for this from
     * the table, the funnel widget and the breakdown widget independently — three
     * recursive CTEs for one answer that cannot change mid-request. Cleared by
     * `forgetDownlines()`, which the model's own save hook calls, so a re-parent
     * is never served from a stale tree.
     *
     * @return list<int>
     */
    public function descendantAndSelfIds(): array
    {
        return self::$downlineMemo[$this->id] ??= $this->descendantsAndSelf()->pluck('id')->all();
    }

    /**
     * Drop the memo. Called whenever a source is saved or deleted, since either
     * can reshape the tree.
     */
    public static function forgetDownlines(): void
    {
        self::$downlineMemo = [];
    }

    /**
     * This source and everything above it, NEAREST FIRST — the order a
     * commission roll-up walks, from the earning leaf up to the root.
     *
     * `orderByDesc`, and the direction is a trap worth naming: the package gives
     * ancestors a NEGATIVE depth (self 0, parent -1, grandparent -2), so plain
     * `orderBy('depth')` returns the ROOT first — the exact opposite of what this
     * method says it does, and silently, because both orderings return the same
     * ids. prescribe-rx has two adjacent methods with opposite orderings and the
     * same "closest ancestor first" comment; one of them is wrong. There is a
     * test pinning this order.
     *
     * @return list<int>
     */
    public function ancestorAndSelfIds(): array
    {
        return $this->ancestorsAndSelf()
            ->orderByDesc('depth')
            ->pluck('id')
            ->all();
    }

    /**
     * The sources one portal user may see: their own, and their entire downline.
     *
     * This is the single scoping point for the partner panel. Every query it
     * runs must pass through here, so that adding a screen cannot accidentally
     * widen what an affiliate can see.
     */
    public function scopeVisibleTo($query, ?self $viewer)
    {
        if (! $viewer) {
            // No viewer resolves to no rows, never to all rows. A scope that
            // degrades to "everything" when identity is missing is how a portal
            // leaks.
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $viewer->descendantAndSelfIds());
    }

    /**
     * Refuse a parent that would create a cycle.
     *
     * A cycle is not a theoretical concern here: an operator reorganising a sales
     * structure drags rows around, and pointing a group at one of its own
     * descendants is an easy mistake to make in a searchable select. Left
     * unchecked it makes the downline walk loop and the commission roll-up
     * meaningless.
     */
    public static function preventCycles(self $source): void
    {
        if ($source->parent_id === null) {
            return;
        }

        if ($source->parent_id === $source->id) {
            throw new \InvalidArgumentException('A referral source cannot report into itself.');
        }

        if ($source->exists && in_array($source->parent_id, $source->descendantAndSelfIds(), true)) {
            throw new \InvalidArgumentException('That would put this source underneath one of its own sub-accounts.');
        }

        // Prevention is the FIRST line; `enableCycleDetection()` on this model is
        // the second. Both are needed: a CTE over a cycle does not terminate on
        // its own, and this check cannot see a cycle written by a raw query.
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
