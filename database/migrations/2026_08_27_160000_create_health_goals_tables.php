<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health goals, and the two edges that hang off them.
 *
 * The goal is what a visitor picks in the quiz. Everything the quiz can
 * recommend is derived from there, and it is derived through the CATALOG
 * rather than through the knowledge base:
 *
 *     goal ──> ingredient ──> product ──> package/stack
 *              (weighted)     (exists)    (exists)
 *
 * Keying recommendations on the ingredient is what makes them true: an
 * ingredient is what a product actually CONTAINS, with a concentration, via
 * the `ingredient_product` pivot that is already there. A compound is an
 * editorial document that may correspond to nothing this install sells.
 * Packages are never mapped directly — a stack surfaces because it contains a
 * product containing a matching ingredient, so it cannot drift out of step
 * with its own contents.
 *
 * `health_goal_product` exists alongside it for the direct override: an
 * operator who wants a product on a goal regardless of its ingredient list.
 *
 * `compound_health_goal` is the SECOND edge and it does a different job —
 * education, not recommendation. It answers "which goals does this peptide
 * align with" on a knowledge-base page. It has to be separate because only 7
 * of 102 compounds map to a catalog ingredient: deriving the KB's goals from
 * the ingredient edge would leave 95 monographs showing none at all.
 *
 * Shape lifted from prx-demo's proven `health_goal_categories` — name, slug,
 * description, icon, colour, ordering, self-referencing parent — minus its
 * clinical `tracked_*` columns, which belong to a patient chart and not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_goals', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // What the quiz shows. `prompt` is the outcome-framed line a
            // visitor picks ("Sleep that actually restores"); `name` is the
            // short admin/label form ("Sleep"). They are different registers
            // and collapsing them makes one of the two read badly.
            $table->string('prompt')->nullable();
            $table->text('description')->nullable();

            $table->string('icon', 64)->nullable();
            $table->string('color', 32)->nullable();
            $table->string('image_path', 2048)->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('health_goals')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();

            // Whether the goal is offered in the public quiz. Separate from
            // is_active on purpose: a goal can stay live for the compounds and
            // products already mapped to it while being withdrawn from intake.
            $table->boolean('show_in_quiz')->default(true)->index();

            $table->unsignedInteger('position')->default(0)->index();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── The recommendation edge ──────────────────────────────────────
        Schema::create('health_goal_ingredient', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('health_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();

            // 0-100, prx-demo's proven range. Ranks which ingredients surface
            // for a goal when more match than a plan can show.
            $table->unsignedTinyInteger('relevance_weight')->default(50);

            // From the indications table prx-demo built and deleted. Separates
            // "strong evidence, mild effect" from the reverse — a weight alone
            // cannot express that, and a clinician reviewing the mapping needs
            // to see both.
            $table->string('evidence_level', 32)->nullable();
            $table->boolean('is_first_line')->default(false);

            // "RELEVANT TO — Fat loss and lean muscle", per goal per ingredient.
            $table->string('relevance_note')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['health_goal_id', 'ingredient_id']);
            $table->index(['health_goal_id', 'relevance_weight']);
        });

        // ── The direct override ──────────────────────────────────────────
        Schema::create('health_goal_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('health_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('relevance_weight')->default(50);
            $table->boolean('is_first_line')->default(false);
            $table->string('relevance_note')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['health_goal_id', 'product_id']);
        });

        // ── The education edge ───────────────────────────────────────────
        Schema::create('compound_health_goal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compound_id')->constrained()->cascadeOnDelete();
            $table->foreignId('health_goal_id')->constrained()->cascadeOnDelete();

            // What the monograph says about this goal — "shown to support
            // recovery from tendon injury". Editorial, and separate from the
            // commercial `relevance_note` on the ingredient edge.
            $table->string('relevance_note')->nullable();
            $table->string('evidence_level', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['compound_id', 'health_goal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compound_health_goal');
        Schema::dropIfExists('health_goal_product');
        Schema::dropIfExists('health_goal_ingredient');
        Schema::dropIfExists('health_goals');
    }
};
