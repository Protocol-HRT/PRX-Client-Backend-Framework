<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carries the source pipeline's retrieval counts onto the monograph.
 *
 * These were left behind in the first import as prescribe-rx internals. They
 * are not: the content is summarised from the operator's own clinical
 * literature corpus by a retrieval pipeline, and these are the size of the
 * evidence base behind each page. "Summarised from 43 clinical sources"
 * is the provenance line this module needs, and without these columns there
 * is no number to put in it.
 *
 * `source_preclusion_count` is deliberately NOT imported: it reads exactly
 * 100 on all 106 source rows, which is a retrieval cap rather than a count,
 * and publishing a constant as though it were evidence would be a false
 * precision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compounds', function (Blueprint $table): void {
            $table->unsignedSmallInteger('source_document_count')->nullable()->after('content_generated_at');
            $table->unsignedSmallInteger('source_dosing_count')->nullable()->after('source_document_count');
        });
    }

    public function down(): void
    {
        Schema::table('compounds', function (Blueprint $table): void {
            $table->dropColumn(['source_document_count', 'source_dosing_count']);
        });
    }
};
