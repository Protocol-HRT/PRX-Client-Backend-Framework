<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label', 120);
            // MenuLinkType enum value. Entity links store a morph reference and
            // resolve slug/availability at API read time; url/anchor store `url`.
            $table->string('link_type', 32);
            $table->string('linkable_type', 64)->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('target', 16)->nullable();
            $table->string('icon', 80)->nullable();
            $table->string('badge', 40)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'position']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
