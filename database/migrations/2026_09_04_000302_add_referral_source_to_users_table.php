<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attach a person to a referral organisation — a "rep".
 *
 * Mirrors prescribe-rx's `users.sales_organization_id`: a rep is a USER, not a
 * separate contact table, because the whole point of a rep is that they log in
 * and see their org's numbers. A parallel person-table would need its own auth,
 * its own roles and its own invitation flow, all of which `users` already has.
 *
 * `restrictOnDelete`, and that is a SECURITY decision rather than a data one.
 * `nullOnDelete` looked kinder — nobody loses an account — but this column is
 * what `User::canAccessPanel()` reads to decide someone is a partner rather than
 * staff. Nulling it on an org deletion silently PROMOTES every affiliate in that
 * org to a staff-eligible account. Hard-deleting an organisation therefore fails
 * until its people are reassigned or removed, which forces the question to be
 * asked out loud. Soft deletes are unaffected and remain the normal path.
 *
 * Staff keep `referral_source_id = null`. That null is meaningful: it is what
 * distinguishes an internal user from a partner, and the panel gate reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referral_source_id')->nullable()->after('is_active')
                ->constrained('referral_sources')->restrictOnDelete();

            $table->index('referral_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_source_id');
        });
    }
};
