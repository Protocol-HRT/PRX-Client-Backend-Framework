<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans attach to a Package OR a Product (product term plans — 3/6/9/12-month
 * pricing with the existing recurring/rebill machinery). Exactly-one-parent
 * is enforced at the model layer (Plan::booted saving guard) — SQLite used in
 * tests has no cross-dialect CHECK ALTER support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->nullable()
                ->after('package_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
