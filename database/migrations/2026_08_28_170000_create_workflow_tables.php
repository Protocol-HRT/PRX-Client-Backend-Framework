<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workflow engine: when THIS happens and THESE things are true, do THAT.
 *
 * This backend is configured by its operators, not by us, and it ships to
 * installs whose funnels we will never see. So the engine stores intent as data
 * and resolves everything else through a registry (App\Workflows\WorkflowRegistry)
 * — an install adds a triggerable model or an action type by registering it, not
 * by editing an enum here.
 *
 * FOUR TABLES, and the split is deliberate:
 *
 *   workflows            what to watch, and whether it applies
 *   workflow_actions     an ordered list of typed steps, each a type + config blob
 *   workflow_runs        one row per evaluation, including the ones that did not match
 *   workflow_action_runs one row per step attempted, so a failure names itself
 *
 * CONDITIONS ARE A JSON COLUMN, NOT A TABLE, and they reuse the shape the CMS and
 * the quiz already use (App\Cms\Support\VisibleWhen). That is not laziness: this
 * install already has an operator-facing condition builder producing exactly this
 * shape, and a second condition vocabulary would mean two things to learn, two
 * things to document, and two evaluators to keep in agreement. VisibleWhen is
 * reused, never forked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();

            // 'model_created' | 'model_updated' | 'model_deleted' | 'event_fired' | 'manual'.
            // A string, not an enum column: an install may register a trigger
            // kind this codebase has never heard of.
            $table->string('trigger_type', 32);

            // WHAT is being watched, as a REGISTRY KEY — 'lead',
            // 'lead.disposition_changed' — never a class name. A class name in a
            // database row that later gets instantiated is a remote-code-execution
            // shaped hole in a product that strangers will deploy; a registry key
            // resolves only to something the install deliberately registered.
            $table->string('trigger_target');

            // VisibleWhen shape: [{field, operator, value}, …], ANDed.
            // Null or [] means "always", which is a legitimate workflow.
            $table->json('conditions')->nullable();

            $table->boolean('is_active')->default(true);

            // Lower runs first. Ties break on id so ordering is total and stable
            // rather than whatever the database felt like returning.
            $table->integer('priority')->default(0);

            // When true, a MATCH here stops later workflows on the same trigger.
            // This is how an operator expresses "route quiz completions here and
            // everything else to the fallback" without conditions that have to
            // exclude each other by hand.
            $table->boolean('stop_on_first_match')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['trigger_type', 'trigger_target', 'is_active'], 'workflows_trigger_idx');
            $table->index(['is_active', 'priority']);
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();

            // Registry key again — 'update_field', 'webhook', 'push_to_integration'.
            $table->string('action_type', 64);

            // The whole point: an action is a TYPE plus a CONFIG BLOB, which is
            // how "send an email", "fire a webhook" and "push to a CRM" coexist
            // without a table each and without this migration knowing any of them.
            $table->json('config')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // Stop the run when this step fails, rather than carrying on. Off by
            // default: a marketing push that fails should not prevent the status
            // update behind it.
            $table->boolean('halt_on_failure')->default(false);

            $table->timestamps();

            $table->index(['workflow_id', 'sort_order']);
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();

            // What it ran against. Nullable because a scheduled or manual run may
            // have no subject at all.
            $table->nullableMorphs('subject');

            $table->string('trigger_type', 32);

            // 'running' | 'skipped' | 'completed' | 'failed'.
            // SKIPPED RUNS ARE RECORDED, and that is the feature. "Why didn't my
            // workflow fire?" is the question operators actually ask, and a log
            // that only records successes cannot answer it.
            $table->string('status', 16);

            // Which condition rejected it, in words. Null when it matched.
            $table->string('skip_reason')->nullable();

            // The attribute snapshot the conditions were evaluated against, so a
            // run can be explained after the subject has moved on.
            $table->json('context')->nullable();

            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['workflow_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('workflow_action_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();

            // Nullable + nullOnDelete: deleting an action must not erase the
            // history of the times it ran. `action_type` is copied here for the
            // same reason.
            $table->foreignId('workflow_action_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('action_type', 64);

            // 'completed' | 'failed' | 'skipped'
            $table->string('status', 16);

            // Whatever the handler chose to record — a webhook's response code, a
            // provider's message id. Keeps the log useful without a column per
            // action type.
            $table->json('output')->nullable();

            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['workflow_run_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_action_runs');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflows');
    }
};
