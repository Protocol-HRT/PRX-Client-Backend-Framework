<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Optional per-page title banner: {enabled, background_image (media id),
            // title_override, subtitle, intro_text, show_breadcrumbs}
            $table->json('title_banner')->nullable()->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('title_banner');
        });
    }
};
