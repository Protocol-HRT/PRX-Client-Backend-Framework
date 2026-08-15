<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_items', function (Blueprint $table) {
            $table->id();
            // Region enum value (top_bar, header, pre_footer, footer, sidebars).
            $table->string('region', 32)->index();
            // RegionItemKind: section (owns its own type+data), global_section, menu.
            $table->string('kind', 16);
            $table->string('section_type', 64)->nullable();
            $table->json('data')->nullable();
            $table->foreignId('global_section_id')->nullable()->constrained('global_sections')->nullOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['region', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_items');
    }
};
