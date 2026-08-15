<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flexible_section_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Becomes page_sections.type for instances; must never collide with
            // a code-defined SectionType value (enforced in the actions).
            $table->string('slug', 64)->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            // Field definitions: {"fields": [{key, kind, label, required, ...}]}
            $table->json('schema');
            $table->boolean('enabled')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flexible_section_types');
    }
};
