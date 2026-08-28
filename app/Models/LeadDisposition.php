<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * An operator-defined stage in the lead funnel.
 *
 * Dispositions were a PHP enum (App\Enums\LeadStatus) until the funnel needed to
 * be the operator's to shape — a workflow that fires when a lead moves to
 * "quiz complete" is worthless if adding "quiz complete" is a code change.
 *
 * The enum did not go away. It is now the set of slugs THE CODE ITSELF writes
 * (MarkLeadHandedOffAction writes 'handed_off' literally), and the rows carrying
 * those slugs are flagged `is_system`: renameable, recolourable, reorderable,
 * but never deletable and never re-slugged. Everything else is the operator's.
 *
 * @see LeadStatus
 */
class LeadDisposition extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'color',
        'is_default',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetMap());
        static::deleted(fn () => static::forgetMap());

        // Exactly one default. Done here rather than as a partial unique index
        // because those are not portable across the drivers prx-backend ships
        // against, and a second default is a silent bug: Lead::creating would
        // pick whichever row came back first.
        static::saving(function (LeadDisposition $disposition): void {
            if ($disposition->is_default) {
                static::query()
                    ->when($disposition->exists, fn ($q) => $q->whereKeyNot($disposition->getKey()))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // A slug is a foreign key in all but name — `leads.status` holds it. The
        // palette taught this lesson already: when the reference is by name, a
        // RENAME IS A REMOVAL, and the damage is silent (leads point at a
        // disposition that no longer exists and render as raw slug).
        static::updating(function (LeadDisposition $disposition): void {
            if (! $disposition->isDirty('slug')) {
                return;
            }

            $original = $disposition->getOriginal('slug');

            if ($disposition->is_system) {
                throw new RuntimeException(
                    "The '{$original}' disposition is written by application code and its slug cannot be changed."
                );
            }

            if (static::leadsUsing($original) > 0) {
                throw new RuntimeException(
                    "Cannot re-slug '{$original}': leads reference it. Create a new disposition and move them instead."
                );
            }
        });

        static::deleting(function (LeadDisposition $disposition): void {
            if ($disposition->is_system) {
                throw new RuntimeException(
                    "The '{$disposition->slug}' disposition is written by application code and cannot be deleted."
                );
            }

            if (static::leadsUsing($disposition->slug) > 0) {
                throw new RuntimeException(
                    "Cannot delete '{$disposition->slug}': leads reference it. Move them to another disposition first."
                );
            }
        });
    }

    /** How many leads currently sit on a given slug. */
    public static function leadsUsing(string $slug): int
    {
        return DB::table('leads')->where('status', $slug)->count();
    }

    /**
     * The disposition a new lead starts on.
     *
     * Falls back to the LeadStatus::New_ slug rather than to "the first row",
     * because an install whose operator cleared `is_default` should still create
     * leads on the stage the code understands rather than on whatever sorts
     * first.
     */
    public static function defaultSlug(): string
    {
        return static::query()->where('is_default', true)->value('slug')
            ?? LeadStatus::New_->value;
    }

    /**
     * slug => name for a Filament select, optionally keeping a slug that is no
     * longer selectable.
     *
     * Deactivating a disposition must not trap the leads already on it. Without
     * `$current`, opening such a lead shows an empty required Select and refuses
     * to save until the operator moves it — turning "hide this from the pickers"
     * into "force a transition on every lead that was there", which is not what
     * the toggle says it does.
     *
     * @return array<string, string>
     */
    public static function optionsFor(?string $current = null): array
    {
        $options = static::options();

        if ($current !== null && $current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = static::labelFor($current);
        }

        return $options;
    }

    /** @return array<string, string> slug => name, active dispositions only. */
    public static function options(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * slug => ['name', 'color'], memoized for the life of the request.
     *
     * A lead table renders one badge per row and each needs a label and a
     * colour. Resolving those through the `disposition` relation would be an
     * N+1 on every page of the list, and eager-loading a belongsTo on a
     * non-key column is more machinery than a four-row lookup deserves.
     *
     * PER-PROCESS, and `forgetMap()` only runs in the process that did the
     * writing. Under php-fpm that is exactly right — one request, one map. A
     * long-lived worker (Horizon, Octane) that called labelFor() would hold
     * pre-edit labels until restart. Nothing queued reads it today; if something
     * ever renders a disposition label in a job, call forgetMap() when it boots.
     *
     * @var array<string, array{name: string, color: string}>|null
     */
    protected static ?array $map = null;

    /** @return array<string, array{name: string, color: string}> */
    public static function map(): array
    {
        return static::$map ??= static::query()
            ->get(['slug', 'name', 'color'])
            ->mapWithKeys(fn (self $d) => [$d->slug => ['name' => $d->name, 'color' => $d->color]])
            ->all();
    }

    /** Forget the memoized map. Tests mutate dispositions mid-request. */
    public static function forgetMap(): void
    {
        static::$map = null;
    }

    /**
     * A human label for a slug, falling back to the slug itself.
     *
     * The fallback is deliberate and should be visible: a lead showing
     * `quiz_complete` rather than "Quiz complete" means its disposition row is
     * gone, which is a real problem an operator should see rather than one
     * papered over with a prettified slug.
     */
    public static function labelFor(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return '—';
        }

        return static::map()[$slug]['name'] ?? $slug;
    }

    public static function colorFor(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return 'gray';
        }

        return static::map()[$slug]['color'] ?? 'gray';
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'status', 'slug');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
