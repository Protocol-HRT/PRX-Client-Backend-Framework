<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the plan email actually went out.
 *
 * A recorded FACT rather than an assumption, and the plan page reads it to
 * decide what to claim: "we've sent a copy to you" only once this is set,
 * because until then it is not true. An install that cannot send mail — which
 * this one could not at all until today — then shows a page that is honest
 * about it instead of one that lies confidently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('plan_sent_at')->nullable()->after('quiz_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('plan_sent_at');
        });
    }
};
