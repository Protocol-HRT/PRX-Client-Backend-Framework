<?php

namespace App\Workflows\Actions;

use App\Workflows\Contracts\WorkflowActionHandler;
use App\Workflows\WorkflowContext;
use App\Workflows\WorkflowRegistry;
use RuntimeException;

/**
 * Dispatch one of this install's registered jobs.
 *
 * THE MOST DANGEROUS THING THIS ENGINE CAN OFFER, and the reason the registry has
 * a separate job allow-list. The job to run comes from an operator-editable
 * database row; if that row held a class name that got instantiated and
 * dispatched, then anyone who reached the admin — or any bug that let a row be
 * written — would have arbitrary code execution in a product hundreds of
 * companies are running. `registerJob()` is the whole boundary: a key that was
 * never registered resolves to nothing and throws.
 *
 * Config: {"job": "registry_key"}
 */
class DispatchJobAction implements WorkflowActionHandler
{
    public function __construct(private readonly WorkflowRegistry $registry) {}

    public function handle(WorkflowContext $context, array $config): array
    {
        $key = $config['job'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('No job configured for the dispatch-job action.');
        }

        $job = $this->registry->resolveJob($key);

        if ($job === null) {
            throw new RuntimeException("Job [{$key}] is not registered and will not be dispatched.");
        }

        dispatch(new $job($context->subject));

        return ['job' => $key, 'class' => $job];
    }
}
