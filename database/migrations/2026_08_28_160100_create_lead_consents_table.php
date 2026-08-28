<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only consent audit.
 *
 * `leads.email_consent` / `sms_consent` / `consent_given_at` record THAT someone
 * consented. They cannot record WHAT they consented to, and that is the gap this
 * closes: the sentence a visitor actually agreed to lives in editable operator
 * copy (`quiz_questions.config`) or, for checkout, in frontend JSX. Edit either
 * and every consent already given silently changes meaning. A consent you cannot
 * reproduce the wording of is not evidence of anything.
 *
 * So the TEXT IS SNAPSHOTTED HERE AT CAPTURE, alongside the request metadata that
 * makes it attributable. The booleans on `leads` stay as the fast current-state
 * read; this table is the history behind them.
 *
 * APPEND-ONLY BY CONSTRUCTION: there is no `updated_at`, and the model blocks
 * updates and deletes. A withdrawal is a NEW row with `granted = false`, not an
 * edit of the row that granted it. An audit trail you can rewrite is a log, not
 * an audit trail — and the admin form currently lets an operator flip a consent
 * boolean and backdate the timestamp by hand, which is exactly the hole.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_consents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            // 'email' | 'sms'. A string rather than an enum column so a future
            // channel (postal, push) needs no migration — prx-backend ships to
            // installs whose channels we do not know.
            $table->string('channel', 32);

            // false is a WITHDRAWAL, and is why this is not an "opt-ins" table.
            $table->boolean('granted');

            // The exact sentence shown. Nullable ONLY because consents captured
            // before this table existed genuinely have no recoverable wording —
            // see the backfill below. Null means "we do not know", never "there
            // was none".
            $table->text('consent_text')->nullable();

            // Free-form: a semver, a date, or a hash of the text. The operator's
            // vocabulary, because the copy is theirs.
            $table->string('consent_version', 64)->nullable();

            // Where the consent was taken: quiz | checkout | admin | api | backfill.
            $table->string('source', 32)->nullable();

            // Server-derived at capture, never client-supplied. A client-sent IP
            // is worth nothing as evidence.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // Set when an OPERATOR recorded the consent rather than the visitor,
            // so an admin-side change is never indistinguishable from a real one.
            $table->foreignId('recorded_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // When the human consented, which is not always when the row was
            // written (a queued import, a backfill).
            $table->timestamp('consented_at');

            // Deliberately no updated_at. See the class doc.
            $table->timestamp('created_at')->nullable();

            $table->index(['lead_id', 'channel']);
            $table->index('consented_at');
        });

        $this->backfill();
    }

    /**
     * Carry existing consents across so the audit does not start empty and
     * pretend nothing was ever consented to.
     *
     * `consent_text` is left NULL on purpose. We know these people consented and
     * when, and we have their IP and user-agent — but the wording they saw is
     * genuinely unrecoverable, because it was never stored and the copy has been
     * editable ever since. Writing today's wording onto a consent given last
     * month would manufacture evidence, which is worse than admitting the gap.
     */
    private function backfill(): void
    {
        $now = now();

        DB::table('leads')
            ->select('id', 'email_consent', 'sms_consent', 'consent_given_at', 'ip_address', 'user_agent', 'created_at')
            ->where(function ($q) {
                $q->where('email_consent', true)->orWhere('sms_consent', true);
            })
            ->orderBy('id')
            ->chunkById(500, function ($leads) use ($now) {
                $rows = [];

                foreach ($leads as $lead) {
                    foreach (['email' => $lead->email_consent, 'sms' => $lead->sms_consent] as $channel => $granted) {
                        if (! $granted) {
                            continue;
                        }

                        $rows[] = [
                            'lead_id' => $lead->id,
                            'channel' => $channel,
                            'granted' => true,
                            'consent_text' => null,
                            'consent_version' => null,
                            'source' => 'backfill',
                            'ip_address' => $lead->ip_address,
                            'user_agent' => $lead->user_agent,
                            'recorded_by_user_id' => null,
                            'consented_at' => $lead->consent_given_at ?? $lead->created_at ?? $now,
                            'created_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('lead_consents')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_consents');
    }
};
