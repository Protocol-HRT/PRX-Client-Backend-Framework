<?php

namespace App\Workflows;

use App\Support\ModelChangeSnapshot;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns ordinary model writes into workflow triggers.
 *
 * ONE OBSERVER FOR EVERY REGISTERED SUBJECT, rather than one per model. An
 * install that registers a new subject gets triggers with no further code, which
 * is the difference between a framework and a feature.
 *
 * `$afterCommit` for the same reason LeadObserver has it: several actions move
 * models inside a transaction, and a workflow that pushed to a CRM for a write
 * that then rolled back cannot be undone. You cannot un-send a webhook.
 */
class WorkflowSubjectObserver
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly WorkflowRegistry $registry,
        private readonly WorkflowDispatcher $dispatcher,
    ) {}

    /**
     * Snapshot the pre-write state while it still exists.
     *
     * `$afterCommit` means the handlers below run after Eloquent has synced
     * original away, so `_original.*` would otherwise report the CURRENT value
     * and the documented "moved from X to Y" pattern could never match inside a
     * transaction. See App\Support\ModelChangeSnapshot.
     */
    public function updating(Model $model): void
    {
        ModelChangeSnapshot::capture($model);
    }

    public function created(Model $model): void
    {
        $this->fire('model_created', $model);
    }

    public function updated(Model $model): void
    {
        // A write that changed nothing is not an event. Filament saves a form
        // whether or not anything moved, and a workflow firing on every save of
        // an untouched record would be indistinguishable from one that is broken.
        if ($model->getChanges() === []) {
            return;
        }

        $this->fire('model_updated', $model);
    }

    public function deleted(Model $model): void
    {
        $this->fire('model_deleted', $model);
    }

    private function fire(string $triggerType, Model $model): void
    {
        $key = $this->registry->keyForModel($model);

        if ($key === null) {
            return;
        }

        $snapshot = ModelChangeSnapshot::read($model);

        $this->dispatcher->dispatchForModel($triggerType, new WorkflowContext(
            triggerType: $triggerType,
            triggerTarget: $key,
            subject: $model,
            subjectKey: $key,
            original: $snapshot['original'],
            // getChanges() survives the sync, so it stays authoritative for
            // WHAT moved; only the previous VALUES needed rescuing.
            changed: array_keys($model->getChanges()) ?: $snapshot['changed'],
        ));
    }
}
