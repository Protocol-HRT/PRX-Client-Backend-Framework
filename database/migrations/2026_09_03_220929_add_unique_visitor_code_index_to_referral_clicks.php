<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make one-click-per-visitor-per-code structural rather than best-effort.
 *
 * `RecordReferralClickAction` used a check-then-insert, so two concurrent first
 * landings — a double-tapped link, a prefetch racing the real navigation — both
 * passed the existence check and wrote two rows. Unique counts survived that
 * (they are COUNT DISTINCT visitor_id) but raw click totals inflated, and this
 * is a table money is argued from.
 *
 * Replaces the plain composite index added with the table; the unique one serves
 * every query the old one did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_clicks', function (Blueprint $table) {
            $table->dropIndex('referral_clicks_code_visitor_id_index');
            $table->unique(['code', 'visitor_id'], 'referral_clicks_code_visitor_unique');
        });
    }

    public function down(): void
    {
        Schema::table('referral_clicks', function (Blueprint $table) {
            $table->dropUnique('referral_clicks_code_visitor_unique');
            $table->index(['code', 'visitor_id'], 'referral_clicks_code_visitor_id_index');
        });
    }
};
