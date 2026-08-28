<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a destination calls one of our records.
 *
 * ─── Why a table and not a column per vendor ───────────────────────────
 *
 * A lead can exist in several destinations at once — a CRM, a marketing
 * platform, an SMS provider — and a column per vendor means a migration every
 * time an operator connects a new one, in a backend that ships to companies
 * whose vendors we have not met. It also invents a naming convention, and this
 * codebase already carries three that compete (`prescribe_rx_*`, `provider_*`,
 * `prx_*`). One polymorphic table, keyed by the INSTANCE rather than the vendor,
 * ends that: the operator's own row names the destination.
 *
 * ─── Keyed by instance + subject, not by remote id ─────────────────────
 *
 * The unique constraint is `(instance, subject)`: one identity per record per
 * destination, so a re-push refreshes rather than duplicates. It is deliberately
 * NOT unique on `remote_id` — Klaviyo merges profiles on email, so two of our
 * subjects can legitimately end up pointing at one remote profile, and a
 * constraint forbidding that would fail a push for being correct.
 *
 * Polymorphic because the workflow engine's subjects already are, and because
 * progressive identify (capturing somebody on email blur, before a lead exists)
 * needs to write here too.
 *
 * ─── The foreign key cascades on FORCE-delete only ─────────────────────
 *
 * `IntegrationInstance` uses soft deletes, so switching a destination off or
 * removing it from the panel leaves these rows intact and a restored instance
 * keeps its ids. Only a hard delete takes them, which is the right moment: at
 * that point nothing can ever ask the far end about them again.
 *
 * INDEX NAMES ARE EXPLICIT because the generated ones overflow. MySQL caps an
 * identifier at 64 characters and `integration_identities` plus three columns
 * plus the suffix is past it — the same trap that created
 * `integration_phi_attestations` with its index silently missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_identities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_instance_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->morphs('subject');

            // The destination's own identifier, verbatim. Never parsed — a
            // vendor may change its id format and we would have no business
            // having an opinion about it.
            $table->string('remote_id', 191);

            // When we last confirmed this mapping against the far end. Distinct
            // from `updated_at`, which also moves when nothing was pushed.
            $table->timestamp('last_pushed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['integration_instance_id', 'subject_type', 'subject_id'],
                'int_identity_instance_subject_unique',
            );

            // Reverse lookup: given what a vendor calls somebody — a webhook, a
            // reporting pull — find our record.
            $table->index(
                ['integration_instance_id', 'remote_id'],
                'int_identity_instance_remote_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_identities');
    }
};
