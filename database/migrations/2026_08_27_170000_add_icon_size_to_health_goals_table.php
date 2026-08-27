<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-goal icon size.
 *
 * Goal icons are a webfont glyph, not an image, so their size is typography
 * rather than a box — and glyphs in the same family are not optically equal.
 * A heartbeat line and a filled body render at very different visual weights
 * at the same pixel size, so a single sitewide size makes half a goal set look
 * wrong. This lets the operator even them up per goal.
 *
 * Nullable: unset means the frontend's own default, which is what almost every
 * goal should use. It is a correction, not a required decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_goals', function (Blueprint $table): void {
            $table->unsignedSmallInteger('icon_size')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('health_goals', function (Blueprint $table): void {
            $table->dropColumn('icon_size');
        });
    }
};
