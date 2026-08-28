<?php

use App\Enums\Privacy\DataClassification;
use App\Enums\Quiz\QuizQuestionKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How sensitive one question's answer is.
 *
 * NULL MEANS "INHERIT THE KIND'S DEFAULT", and that is a third state worth
 * having rather than an unset boolean in disguise. `QuizQuestionKind` already
 * knows that a health-goals question is clinical and a contact question is not,
 * so most questions never need an answer here — and the difference between "an
 * operator classified this" and "the system assumed" is exactly what the
 * mapping warning should be able to say out loud.
 *
 * Defaults lean protective: an operator-authored select in a health quiz counts
 * as health data until somebody decides otherwise. Downgrading is the
 * deliberate act, because the failure directions are not symmetrical — an
 * over-classified field costs one extra attestation, an under-classified one
 * ships health data to a destination that must not have it.
 *
 * @see DataClassification
 * @see QuizQuestionKind::defaultDataClassification()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->string('data_class', 16)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('data_class');
        });
    }
};
