<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('badge_text')->nullable()->after('is_featured');
            $table->json('highlights')->nullable()->after('badge_text');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->string('badge_text')->nullable()->after('is_featured');
            $table->json('highlights')->nullable()->after('badge_text');
            $table->string('banner_image_path', 2048)->nullable()->after('hero_image_path');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->string('badge_text')->nullable()->after('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['badge_text', 'highlights']);
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['badge_text', 'highlights', 'banner_image_path']);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('badge_text');
        });
    }
};
