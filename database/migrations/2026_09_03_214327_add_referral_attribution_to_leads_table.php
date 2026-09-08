<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribution on the lead — the conversion half of the ledger.
 *
 * `referral_code` is a STRING SNAPSHOT and is the load-bearing column, for the
 * same reason it is on `referral_clicks`: the FKs are conveniences that go null,
 * the code is the record. A commission is argued from the code and the timestamp,
 * not from a join that may no longer resolve.
 *
 * The existing `utm_*` / `referrer` / `landing_url` columns stay and finally get
 * populated correctly — they were captured at quiz-SUBMIT time (so a visitor who
 * navigated lost them, and `landing_url` recorded the quiz page), and the checkout
 * lead path never sent them at all. Both now carry what the landing captured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('referral_source_id')->nullable()->after('landing_url')
                ->constrained('referral_sources')->nullOnDelete();
            $table->foreignId('referral_link_id')->nullable()->after('referral_source_id')
                ->constrained('referral_links')->nullOnDelete();
            $table->foreignId('referral_click_id')->nullable()->after('referral_link_id')
                ->constrained('referral_clicks')->nullOnDelete();

            // Survives all three going null.
            $table->string('referral_code', 64)->nullable()->after('referral_click_id')->index();

            // When the referral was bound to this lead — the commission window.
            $table->timestamp('attributed_at')->nullable()->after('referral_code');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_source_id');
            $table->dropConstrainedForeignId('referral_link_id');
            $table->dropConstrainedForeignId('referral_click_id');
            $table->dropColumn(['referral_code', 'attributed_at']);
        });
    }
};
