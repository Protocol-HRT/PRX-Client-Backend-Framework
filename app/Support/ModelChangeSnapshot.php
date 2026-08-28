<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use WeakMap;

/**
 * Remembers what a model looked like BEFORE a write, for observers that run
 * after the transaction commits.
 *
 * ─── Why this has to exist ──────────────────────────────────────────────
 *
 * An observer with `$afterCommit = true` runs its handler from a commit
 * callback — which fires AFTER Eloquent has already called `syncOriginal()`.
 * By then `getOriginal()` returns the NEW values, so:
 *
 *   $lead->getOriginal('status') === $lead->status     // both 'handed_off'
 *
 * That is silent and total. A "moved from X to Y" event computed there sees
 * `$from === $to` and either reports nonsense or, if it guards on them being
 * different, never fires at all. It was shipped that way: every transactional
 * status write — including the prescribe-rx checkout handoff, the highest-value
 * transition in the funnel — dispatched nothing whatsoever, while direct writes
 * worked fine. Which is the worst shape for a bug: it passes every test that
 * does not wrap the write in a transaction.
 *
 * `getChanges()` is unaffected and survives the sync; only the previous VALUES
 * are lost, and they are unrecoverable afterwards. So they are captured at
 * `updating`, before the sync, and read back after the commit.
 *
 * ─── Why a WeakMap ─────────────────────────────────────────────────────
 *
 * The snapshot has to hang off the model instance, and neither obvious option
 * works: a dynamic property goes through Eloquent's `__set` and becomes a fake
 * ATTRIBUTE that tries to save to a column that does not exist, and an array
 * keyed by `spl_object_id` both leaks whenever a write rolls back before
 * `updated` and risks collisions, because PHP reuses object ids after garbage
 * collection. A WeakMap holds no reference: the entry disappears with the model
 * whether the write committed, rolled back, or threw.
 */
class ModelChangeSnapshot
{
    /** @var WeakMap<Model, array{original: array<string, mixed>, changed: list<string>, applied: array<string, mixed>}>|null */
    private static ?WeakMap $snapshots = null;

    /**
     * Record the pre-write state. Call from `updating`, never later.
     *
     * ─── Why this is not simply `getOriginal()` ────────────────────────
     *
     * In a CHAIN — a workflow action writing to the same model from inside the
     * previous write's handler — `syncOriginal()` may not have run yet when the
     * second write begins. (Precisely: with a real surrounding transaction the
     * sync HAS happened by commit time; on the non-transactional path an
     * "afterCommit" handler runs inline inside `performUpdate`, before
     * `finishSave()` does the sync. The merge below is correct either way, which
     * is why it is unconditional.) Left to `getOriginal()`, the second write
     * reports the value from before the FIRST one, so a workflow asking "did it
     * move from nurture?" is told "no, from new", and silently never matches.
     *
     * So the persisted state is tracked here rather than read back: after a
     * write, what is in the database is that write's starting point merged with
     * the values it set. Those are exactly `original` and `applied` from the
     * previous capture, which makes the next write's true "before" a merge of
     * the two.
     *
     * This is equivalent to `getOriginal()` whenever the sync HAS run, so the
     * ordinary single-write case is unaffected.
     */
    public static function capture(Model $model): void
    {
        $map = self::map();
        $prior = $map[$model] ?? null;
        $dirty = $model->getDirty();

        // IDEMPOTENT PER WRITE. A model can carry several observers and each
        // calls this — Lead carries two — so without the guard the second call
        // treats the first as a previous write and merges its values in,
        // collapsing the change to "was nurture, is nurture". Identical pending
        // changes mean this is the same write, not the next one.
        // STRICT comparison. Loose would treat a write setting a field to null
        // and a later one setting the same field to '' as the same write
        // (`null == ''` is true in PHP), suppressing the second capture and
        // reporting a stale `_original`. The legitimate same-write case compares
        // byte-identical getDirty() arrays, so strictness cannot regress it.
        if ($prior !== null && $prior['applied'] === $dirty) {
            return;
        }

        $map[$model] = [
            'original' => $prior === null
                ? $model->getOriginal()
                : array_merge($prior['original'], $prior['applied']),
            'changed' => array_keys($dirty),
            // What THIS write is setting, so the next one in the chain knows
            // where it started from.
            'applied' => $dirty,
        ];
    }

    /**
     * Read the pre-write state back.
     *
     * NON-DESTRUCTIVE, and that is not an optimisation. A model can carry more
     * than one observer — Lead carries LeadObserver and WorkflowSubjectObserver —
     * and a read that consumed the snapshot would starve whichever ran second,
     * silently handing it the post-sync values this class exists to avoid. There
     * is nothing to clean up: the next write re-captures, and the WeakMap entry
     * disappears with the model.
     *
     * Falls back to the model's own state when nothing was captured — a model
     * saved through a path that skipped `updating` still gets a coherent answer
     * rather than an exception, it is just the post-sync one.
     *
     * KNOWN LIMIT: a `saveQuietly()` / `withoutEvents()` / query-builder write
     * landing BETWEEN two observed writes on the same instance is invisible here,
     * so the merged `original` describes the state before that quiet write rather
     * than after it. Nothing in this codebase does that to a registered subject
     * today; if one starts to, that write needs its own `capture()`.
     *
     * @return array{original: array<string, mixed>, changed: list<string>, applied: array<string, mixed>}
     */
    public static function read(Model $model): array
    {
        return self::map()[$model] ?? [
            'original' => $model->getOriginal(),
            'changed' => array_keys($model->getChanges()),
            'applied' => [],
        ];
    }

    private static function map(): WeakMap
    {
        return self::$snapshots ??= new WeakMap;
    }
}
