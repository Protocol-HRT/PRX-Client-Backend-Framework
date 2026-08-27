<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The knowledge-base monograph: one row per compound, independent of whether
 * this install sells it.
 *
 * Deliberately NOT an extension of `ingredients`. That table is the catalog's
 * lookup — thin, provider-syncable, and it exists to drive a facet on the shop
 * listing. A monograph is editorial: it is long-form, it is reviewed by a
 * named clinician, and most of its rows will never be a product. Merging them
 * would put a sync target and a reviewed document in one row. `ingredient_id`
 * links the two instead, and it is that link — compound to product — that the
 * KB has and a generic health wiki does not.
 *
 * Note what is absent: no `compound_class` taxonomy is built on top of the
 * imported string. In the seed data 94 of 106 rows say only "Peptide" and the
 * remainder mix mechanism, effect and chemistry, so it cannot carry a browse
 * hierarchy. It is kept for provenance and search only; health goals are the
 * taxonomy, and they arrive in a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compounds', function (Blueprint $table): void {
            $table->id();

            // ── Identity ────────────────────────────────────────────────
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->json('brand_names')->nullable();
            $table->json('synonyms')->nullable();

            // ── Classification ──────────────────────────────────────────
            // is_peptide is the gate on the peptide wiki. Roughly two thirds
            // of the seed rows are antibiotics, topicals and vitamins; without
            // this flag the peptide knowledge base publishes amoxicillin.
            $table->string('compound_class')->nullable()->index();
            $table->boolean('is_peptide')->default(false)->index();
            $table->string('regulatory_status', 32)->nullable()->index();
            $table->string('route_of_administration')->nullable();

            // ── Monograph ───────────────────────────────────────────────
            // HTML, not markdown and not plain text: the import converts the
            // source markdown once so the admin editor and the public page
            // agree on the shape, and `.rich-text` on the frontend styles the
            // tags the prose contract already promises.
            $table->longText('description')->nullable();
            $table->longText('overview')->nullable();
            $table->longText('mechanism_of_action')->nullable();
            $table->longText('pharmacology')->nullable();
            $table->longText('clinical_evidence')->nullable();
            $table->longText('dosing_guidelines')->nullable();
            $table->longText('safety_profile')->nullable();
            $table->longText('patient_summary')->nullable();
            $table->json('clinical_references')->nullable();

            // ── Ranking ─────────────────────────────────────────────────
            // Both NULL on every imported row. They exist now because the
            // goal-to-compound index ranks on them, and adding a column to a
            // reviewed table later is more disruptive than carrying two nulls.
            $table->string('evidence_tier', 32)->nullable()->index();
            $table->decimal('evidence_score', 3, 2)->nullable();

            // ── Commerce link ───────────────────────────────────────────
            $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();

            // ── Review gate ─────────────────────────────────────────────
            // A Profile, not a User: the reviewer has to be *displayable* with
            // credentials ("Reviewed by Jane Roe, PharmD"), and `profiles`
            // already carries name/title/credentials/bio for exactly that.
            // Users are admin accounts and have no credentials field.
            $table->foreignId('reviewed_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            // ── Publication ─────────────────────────────────────────────
            // Import never sets this. 106 machine-written monographs arriving
            // unread is precisely what the gate is for.
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();

            // ── SEO ─────────────────────────────────────────────────────
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('hero_image_path', 2048)->nullable();
            $table->string('og_image_path', 2048)->nullable();

            // ── Provenance ──────────────────────────────────────────────
            // source_system + source_ref make re-import a decision rather than
            // a disaster: the seed is model-generated against someone else's
            // sources and may be regenerated, and without a stable key the
            // second import either duplicates every row or overwrites local
            // edits blindly.
            $table->string('source_system', 64)->nullable();
            $table->string('source_ref', 64)->nullable();
            $table->string('content_model', 100)->nullable();
            $table->timestamp('content_generated_at')->nullable();

            $table->unsignedInteger('position')->default(0)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_system', 'source_ref']);
            $table->index(['is_published', 'is_peptide']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compounds');
    }
};
