<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a completed quiz lands, and how a package says what it is.
 *
 * QUIZ ANSWERS GO ON THE LEAD, not in a table of their own. A completed quiz
 * IS a lead — that is the whole point of the funnel — and the answers have no
 * life independent of the person who gave them. A separate `quiz_responses`
 * table would let an answer set exist with nobody attached to it, which is a
 * row nobody can act on and nobody can delete on request.
 *
 * JSON keyed by question slug rather than a row per answer, because the
 * questions are themselves data: a row-per-answer table would need a foreign
 * key to `quiz_questions`, and deleting a question an operator retired would
 * then either cascade away historical answers or block the deletion. The slug
 * is a stable key that outlives the question row, and reading a whole
 * response is one column rather than a join.
 *
 * What this is NOT: a place for contact details. Name, email, phone and the
 * two consents are real columns on `leads` already and stay there. Copying
 * them into the JSON as well would create a second email address that can
 * disagree with the first, and only one of them is the one we mail.
 *
 * PACKAGE TIER is a nullable free string, deliberately generic. Atlas uses
 * `protocol` (a few peptides designed to run together) and `stack` (the larger
 * set), but prx-backend ships to more than one client and their vocabulary is
 * their own. The alternative considered was a tag — zero migration, but it
 * routes money-adjacent logic (which price range a quiz option shows) through
 * a display string an operator can rename, which is exactly the failure the
 * palette guard exists to prevent elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->json('quiz_answers')->nullable()->after('cart_items');

            // Which quiz produced them. Null for a lead from checkout, which
            // is most of them today — so this is also the flag that separates
            // funnel leads from cart leads without inspecting the JSON.
            $table->foreignId('quiz_id')->nullable()->after('quiz_answers')
                ->constrained()->nullOnDelete();

            $table->timestamp('quiz_completed_at')->nullable()->after('quiz_id');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->string('tier', 32)->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quiz_id');
            $table->dropColumn(['quiz_answers', 'quiz_completed_at']);
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropIndex(['tier']);
            $table->dropColumn('tier');
        });
    }
};
