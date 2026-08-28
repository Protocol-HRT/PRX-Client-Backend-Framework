<?php

namespace Tests\Feature\Workflows;

use Tests\TestCase;

/**
 * Configuration invariants for the workflow queue.
 *
 * These are two numbers in two different files, and they are only correct
 * relative to each other. Nothing in a normal test run touches them, and the
 * failure they cause is not an exception — it is a chain that ran perfectly
 * being recorded as failed, which nobody investigates because the log already
 * "explains" it.
 *
 * So they are pinned here rather than left to a comment. A comment is what the
 * person retuning one side does not read.
 */
class WorkflowQueueConfigurationTest extends TestCase
{
    public function test_a_worker_cannot_outlive_the_reservation_it_holds(): void
    {
        $timeout = config('horizon.defaults.supervisor-workflows.timeout');
        $retryAfter = config('queue.connections.workflows.retry_after');

        $this->assertNotNull($timeout, 'supervisor-workflows must exist, or nothing runs the workflows queue');
        $this->assertNotNull($retryAfter, 'the workflows connection must exist');

        // Strictly less, with room. Equal is not safe: Redis decides a job is
        // abandoned the moment the reservation lapses, and a worker finishing at
        // exactly that instant is a race, not a success.
        $this->assertLessThan(
            $retryAfter,
            $timeout,
            "A workflow chain may run for up to {$timeout}s, but its Redis reservation lapses after "
            ."{$retryAfter}s. A chain that outlives its reservation is handed to a second worker, which "
            .'marks it failed WITHOUT running it while the first worker finishes it successfully — a '
            .'failure in the log that never happened. Raise retry_after, or lower the timeout.'
        );
    }

    public function test_the_workflows_queue_is_actually_supervised(): void
    {
        // The silent-hole check. A job pushed to a queue no supervisor watches is
        // not an error anywhere — it simply never runs, and the admin shows no
        // runs and no failures. This asserts the name the job uses and the name
        // Horizon watches are still the same string.
        $watched = config('horizon.defaults.supervisor-workflows.queue');

        $this->assertContains(
            'workflows',
            (array) $watched,
            'RunWorkflowChain pushes to the "workflows" queue; if no supervisor watches it, '
            .'every trigger enqueues a job that is never collected.'
        );
    }

    public function test_the_workflows_supervisor_reads_the_connection_that_carries_the_longer_reservation(): void
    {
        // Pointing the supervisor at 'redis' would look identical — same Redis
        // server, same queue key, jobs still run — while silently restoring the
        // 90s reservation the connection above exists to widen.
        $this->assertSame(
            'workflows',
            config('horizon.defaults.supervisor-workflows.connection'),
        );
    }
}
