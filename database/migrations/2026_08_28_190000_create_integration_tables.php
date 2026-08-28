<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configured integration instances, and the attestations that let them receive
 * health data.
 *
 * ─── Which layer names a vendor ────────────────────────────────────────
 *
 * The workflow action registry never names one: a `push_to_klaviyo` case in a
 * shipped enum means every install that uses a different CRM has to fork the
 * product to add it. But vendors have to be nameable SOMEWHERE — an operator
 * needs to see Klaviyo, switch it on and paste their keys, and a Klaviyo driver
 * needs Klaviyo-specific code. The split is:
 *
 *   workflow action registry  no vendor   one generic `push_to_integration`
 *   provider/driver registry  vendor      code, registered like actions are
 *   THIS TABLE                vendor      "Klaviyo — Marketing" + the keys
 *   workflow action config    the ROW     {"integration": "klaviyo-marketing"}
 *
 * So enabling a vendor is what makes it appear in the action palette, and the
 * palette is a query against these rows rather than a hardcoded list.
 *
 * ─── Why capabilities are a JSON array and not columns ─────────────────
 *
 * One Twilio account may be authorised for SMS but not voice; one email vendor
 * for transactional but not marketing. So the capability set belongs to the
 * INSTANCE, not to the vendor. `MerchantAccount` models the same idea as boolean
 * columns (`allows_recurring_payments`, `allows_rx_processing`, …), and that is
 * the precedent NOT to follow here: this backend ships to installs whose vendors
 * and channels we have not met, and a new capability must not cost them a
 * migration. Same reasoning as `lead_consents.channel` being a string.
 *
 * The lookup this has to serve is "which enabled instances offer capability X",
 * asked when an admin form renders, against a table that holds single digits of
 * rows per install. `whereJsonContains` over five rows does not need an index;
 * `provider` and `is_active` get one because they are the coarse filters.
 *
 * ─── Why the PHI flag has a table behind it ────────────────────────────
 *
 * The flag is an ATTESTATION, not a verification: this system cannot know
 * whether a BAA exists, only that an operator said one does. That makes WHO said
 * so and WHEN the entire value of the record — and a pair of columns on the
 * instance loses the previous answer the second time it is toggled, which is
 * precisely when the question ("who authorised health data to this destination
 * during the window the leak happened?") gets asked. Same shape as
 * `lead_consents`: an append-only history, with a fast current-state boolean
 * cached on the parent row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_instances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The operator's own name for it: "Klaviyo — Marketing".
            $table->string('name');

            // What a workflow action's config points at. Referenced from a JSON
            // column, so there is no foreign key in either direction — the model
            // guards renames and deletes instead, the way LeadDisposition and the
            // theme palette already do. A rename IS a removal when references are
            // by name, and this project has learned that twice.
            $table->string('slug')->unique();

            // A registry key — 'klaviyo', 'twilio', 'local_mail' — never a class
            // name. These rows are operator-editable, and a class name in one
            // that later gets instantiated is arbitrary class instantiation in a
            // codebase many companies will deploy. Same boundary as
            // WorkflowRegistry's.
            $table->string('provider', 64)->index();

            $table->boolean('is_active')->default(true)->index();

            // What the OPERATOR enabled, which is the intersection of what the
            // driver can do and what their account is authorised for. Empty means
            // configured but offering nothing — deliberate, not a bug.
            $table->json('capabilities')->nullable();

            // Secrets. `encrypted:array` on the model; the column is text because
            // ciphertext is not JSON. Never rendered as a key/value editor — the
            // driver declares its credential fields so each one gets a masked
            // input, per the MerchantAccount precedent.
            $table->text('credentials')->nullable();

            // Non-secret provider config — a region, a from-number, a default
            // list id. Split from credentials so an admin table can show it
            // without decrypting anything.
            $table->json('settings')->nullable();

            // Cached current state of the attestation history below. The table is
            // the truth; this is what a query filters on.
            $table->boolean('phi_permitted')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('integration_phi_attestations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_instance_id')->constrained()->cascadeOnDelete();

            // false is a REVOCATION. "Revoked" and "never attested" are different
            // facts and must not collapse into one another — the same reason
            // lead_consents records withdrawals as rows.
            $table->boolean('permitted');

            // The operator's claim, in their words: "BAA signed with Twilio
            // 2026-08-01, ref #1234". This is the part a lawyer reads.
            $table->text('note')->nullable();

            // Never nullable by flow, unlike a consent: there is no visitor path
            // here, so every row is an authenticated operator acting. Nullable
            // only so deleting a user does not delete the audit trail.
            $table->foreignId('attested_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // No updated_at. Append-only, enforced in the model.
            $table->timestamp('created_at')->nullable();

            // NAMED EXPLICITLY, and not for tidiness. The name Laravel would
            // generate here — table + both columns + "_index" — is 69 characters,
            // and MySQL's identifier limit is 64. The CREATE TABLE and the
            // foreign keys succeed, then this one statement fails, leaving a real
            // table behind with no migration row recorded.
            //
            // The test suite runs on SQLite, which has no such limit, so this
            // passes every test and fails only on the deployment it matters on.
            // Any composite index on a long table name needs a name of its own.
            $table->index(['integration_instance_id', 'created_at'], 'integration_phi_attest_instance_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_phi_attestations');
        Schema::dropIfExists('integration_instances');
    }
};
