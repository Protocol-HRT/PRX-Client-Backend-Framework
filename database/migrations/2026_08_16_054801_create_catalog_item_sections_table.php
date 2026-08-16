<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_item_sections', function (Blueprint $table) {
            $table->id();
            $table->morphs('sectionable');
            $table->string('type');
            $table->json('data')->nullable();
            $table->foreignId('global_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('anchor_id', 64)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('detail_layout')->nullable()->after('detail_sections');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->json('detail_layout')->nullable()->after('detail_sections');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_item_sections');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('detail_layout');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('detail_layout');
        });
    }
};
