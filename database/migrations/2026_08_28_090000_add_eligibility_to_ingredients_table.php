<?php

use App\Enums\Catalog\SexEligibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sex and age eligibility, stored on the INGREDIENT.
 *
 * This is the gate the quiz applies before ranking. `relevance_weight` on
 * `health_goal_ingredient` orders options that are all acceptable; these
 * columns decide which are acceptable at all, and they are applied first.
 * Recommending testosterone to a woman is not a badly ranked answer, it is a
 * wrong one, and no weight can express that.
 *
 * WHY THE INGREDIENT AND NOT THE PRODUCT — the same argument the health-goals
 * migration makes for recommendations, and the data backs it here too. An
 * ingredient is what a product actually CONTAINS, and one ingredient backs
 * several SKUs (Testosterone Cypionate, Sildenafil and Tadalafil each already
 * do). Stated on the substance, the rule is written once and every product
 * holding it inherits it, including products that do not exist yet. Stated on
 * the product, it is restated per SKU and drifts the first time a new
 * testosterone item ships with the flag forgotten — a failure that is silent
 * and points the wrong way.
 *
 * Measured before choosing: 10 of 11 products have ingredients attached, so
 * derivation covers the catalogue. The eleventh is `testosterone-cypionate`,
 * whose pivot row is simply missing — a data gap, not a case against. The
 * resolver treats a product with NO ingredients as ineligible rather than
 * unrestricted, so that gap cannot become a bypass.
 *
 * NO PRODUCT-LEVEL OVERRIDE COLUMN SHIPS HERE, deliberately. There is no
 * demand for one yet (`health_goal_product` has 0 rows), and a second place to
 * state one clinical fact fails silently when the two disagree. If a
 * combination product ever needs looser eligibility than its strictest
 * ingredient, that is one migration and an explicit nullable column, where
 * null keeps meaning "derive" — not a default that quietly re-opens the gate.
 *
 * AGE IS TWO NULLABLE BOUNDS, NOT A BAND ENUM. The quiz collects an exact age
 * from a slider, so an integer compares directly; a band would need mapping in
 * both directions and could not express "18-35". Null on either side means
 * unbounded, which is why both default to null rather than 18/100 — a bound
 * nobody set must not become a bound that filters. The human-readable form
 * ("Suitable under 40") is DERIVED for display and for the protocol PDF, never
 * stored, so it cannot contradict the numbers beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->string('sex_eligibility', 16)
                ->default(SexEligibility::Any->value)
                ->after('description');

            $table->unsignedTinyInteger('min_age')->nullable()->after('sex_eligibility');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');

            // Operator-authored rationale, surfaced in the generated protocol
            // and the PDF. Authored rather than generated on purpose: "not
            // recommended over 65 due to cardiovascular risk" is a clinical
            // claim, and a sentence assembled from two integers cannot make
            // it. Null means the numbers speak for themselves.
            $table->text('eligibility_note')->nullable()->after('max_age');

            // Filtering reads sex on every recommendation resolve, and the
            // table is small enough that the index is about intent as much as
            // speed — it marks the column as a query gate, not a display flag.
            $table->index('sex_eligibility');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropIndex(['sex_eligibility']);
            $table->dropColumn(['sex_eligibility', 'min_age', 'max_age', 'eligibility_note']);
        });
    }
};
