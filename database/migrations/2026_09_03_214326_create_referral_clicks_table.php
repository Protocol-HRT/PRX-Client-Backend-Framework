<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per referral arrival. THE LEDGER — this is the table a commission
 * dispute is settled from, so it is append-only by design.
 *
 * NO `softDeletes()` and NO `prunable()`, on purpose. A click is an event that
 * happened; it is never edited and never reaped. `carts` is pruned at 90 days
 * (Cart::prunable) and that is exactly why attribution does not live on the cart.
 *
 * THE STRING SNAPSHOTS ARE THE POINT. `code` and `source_slug` duplicate what the
 * two foreign keys already say, which looks like denormalisation until you ask
 * what happens when a source is deleted years later: the FKs go null and the row
 * still names who was credited. Reconstructable after the fact means after the
 * rows it pointed at are gone.
 *
 * `visitor_id` is the frontend's first-party cookie value. It is what makes a
 * unique click derivable (COUNT DISTINCT visitor_id) without storing a counter,
 * and it is NOT an identity — it never leaves the cookie and joins to no person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Both nullable + nullOnDelete: the FKs are the convenience, the
            // string snapshots below are the record.
            $table->foreignId('referral_link_id')->nullable()
                ->constrained('referral_links')->nullOnDelete();
            $table->foreignId('referral_source_id')->nullable()
                ->constrained('referral_sources')->nullOnDelete();

            // Survives deletion of everything above. Never null.
            $table->string('code', 64)->index();
            $table->string('source_slug')->nullable();

            // First-party cookie value from the storefront. Unique clicks are
            // COUNT(DISTINCT visitor_id), derived, never stored.
            $table->uuid('visitor_id')->index();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 2048)->nullable();

            // The REAL landing URL, captured at landing by frontend middleware —
            // not re-read at submit time, which is the bug this replaces.
            $table->string('landing_url', 2048)->nullable();

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            $table->timestamp('clicked_at')->index();
            $table->timestamps();

            // Serves both the per-source dashboard and the unique-click count.
            $table->index(['referral_source_id', 'clicked_at']);
            $table->index(['code', 'visitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
    }
};
