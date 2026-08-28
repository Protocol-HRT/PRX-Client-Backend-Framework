<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The intake quiz, as data.
 *
 * The wizard shipped with three hardcoded steps. Every question after those is
 * a business decision an operator has to be able to change — the wording, the
 * options, the order, and which questions a given health goal even triggers —
 * so the structure moves into rows and the frontend becomes a walker.
 *
 * NEW TABLES RATHER THAN THE CMS FLEXIBLE-TYPE SYSTEM, and the reason is a
 * direction, not a preference. The CMS schema builder's CONSUMER is the
 * operator: they author a field list and then fill it in, and the filled-in
 * values are the served content. The quiz inverts that — the schema is what
 * gets served, and a VISITOR supplies the values. Putting quiz structure in
 * CMS tables would make every quiz edit ride CMS cache invalidation, force the
 * empty-scaffold classifier to be dodged (the `hasIntrinsicContent()` escape
 * hatch already exists because the quiz did not fit it), and ship
 * steps/branching/exclusive-option semantics to every other prx frontend as
 * CONTENT vocabulary. What is worth taking from that system is its condition
 * format, and this takes exactly that — see `visible_when` below.
 *
 * SHAPED LIKE THE PROVIDER'S CLINICAL INTAKE SCHEMA on purpose
 * (`EncounterTypeSchemaData`: steps -> fields, with `required_slugs`). The
 * marketing quiz and the clinical intake ask questions in the same shape, and
 * a frontend that can walk one should walk the other rather than growing a
 * second walker.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A named quiz. More than one exists so a campaign landing page can
        // run a shorter variant without forking the questions — the `quiz`
        // section already picks which goals it offers, and this is the same
        // idea one level up.
        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();

            // Exactly one quiz answers "which one does /quiz run". A boolean
            // rather than a settings row because the answer belongs to the
            // quiz, and a settings key pointing at a deleted id is a class of
            // breakage this avoids entirely.
            $table->boolean('is_default')->default(false)->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quiz_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();

            $table->string('slug');
            $table->string('name');

            // What the visitor reads at the top of the step. `heading` is the
            // question; `description` is the reassurance under it — "No wrong
            // answer. Most people start from zero." That line does real work
            // in a funnel and must be operator copy, not a string in a JSX
            // file.
            $table->string('heading')->nullable();
            $table->text('description')->nullable();

            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();

            // A whole step can be conditional, not just a question — "roughly
            // where are you today" is asked only for goals where height and
            // weight mean something. Same format as a question's.
            $table->json('visible_when')->nullable();

            $table->timestamps();

            $table->unique(['quiz_id', 'slug']);
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_step_id')->constrained()->cascadeOnDelete();

            // Denormalised from the step, and it earns its keep twice: it is
            // what makes the unique index below a QUIZ-level guarantee, and it
            // lets a condition reference any earlier answer without joining
            // through steps. Kept in step by the model on save.
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();

            // The answer key. Everything downstream — validation, the report's
            // merge tokens, the recommendation resolver — addresses answers by
            // this, so it has to be unique across the QUIZ and not merely the
            // step: moving a question to another step must not change the key
            // its existing answers are filed under.
            $table->string('slug');

            $table->string('kind', 32)->index();
            $table->string('prompt');
            $table->text('help')->nullable();

            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();

            // Conditions in `App\Cms\Support\VisibleWhen`'s format, evaluated
            // against the answers so far:
            //   [{"field": "health_goals", "operator": "contains", "value": "weight-management"}]
            // Reusing that class rather than inventing a second dialect is the
            // point; the operator-facing condition builder is the same one the
            // CMS uses.
            $table->json('visible_when')->nullable();

            // Per-kind settings: slider bounds, measurement units, input mode.
            // JSON because the shape genuinely differs per kind and a column
            // per kind would be mostly-null columns nobody can read.
            $table->json('config')->nullable();

            $table->timestamps();

            $table->unique(['quiz_id', 'slug']);
        });

        Schema::create('quiz_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();

            $table->string('value');
            $table->string('label');
            $table->string('description')->nullable();

            // A Tabler class, same vocabulary as health goals use, so the two
            // pickers behave identically for an operator.
            $table->string('icon', 64)->nullable();

            // "None of these" — selecting it clears every other choice, and
            // choosing another clears it. Without this the visitor can answer
            // "none of these, and also high blood pressure", which is not an
            // answer anyone can act on.
            $table->boolean('is_exclusive')->default(false);

            // Where a price range on this option comes from, if any:
            // `products`, `packages:protocol`, `packages:stack`, or null.
            // The RANGE ITSELF IS NEVER STORED — it is computed from live plan
            // prices when the quiz is served, because an authored price goes
            // stale silently and this one sits next to a buying decision.
            $table->string('price_source', 32)->nullable();

            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['quiz_question_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_question_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quiz_steps');
        Schema::dropIfExists('quizzes');
    }
};
