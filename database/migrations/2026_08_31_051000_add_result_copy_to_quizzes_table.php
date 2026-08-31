<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the quiz says on its results page, in the operator's own words.
 *
 * THESE EXIST BECAUSE THE ZERO-MATCH PATH IS COPY, NOT CODE. The resolver
 * already distinguishes `restricted` (we built this goal out, and it is not for
 * you) from `unmapped` (nobody has built it out yet). Those need completely
 * different sentences, and neither sentence is a frontend concern — a component
 * that hardcoded them would put brand voice in a repo that ships to more than
 * one brand, and would leave the person who actually decides what to say to a
 * visitor who matched nothing unable to change it.
 *
 * NULLABLE WITH NO DEFAULTS, deliberately. This backend ships content-free: a
 * fresh install gets the columns and no words, and each frontend decides what
 * an unauthored value means. Atlas's own copy is applied by a fill script in
 * the frontend repo, which is where this deployment's content lives.
 *
 * Text rather than string: every one of these is a rich-text field, so the
 * stored value carries markup and a 255 cap would truncate mid-tag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            // Inline: the frontend supplies the heading element.
            $table->text('result_heading')->nullable()->after('description');

            // Prose: each gets its own container on the results page.
            $table->text('result_intro')->nullable()->after('result_heading');
            $table->text('result_restricted_body')->nullable()->after('result_intro');
            $table->text('result_unmapped_body')->nullable()->after('result_restricted_body');
            $table->text('result_empty_body')->nullable()->after('result_unmapped_body');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn([
                'result_heading',
                'result_intro',
                'result_restricted_body',
                'result_unmapped_body',
                'result_empty_body',
            ]);
        });
    }
};
