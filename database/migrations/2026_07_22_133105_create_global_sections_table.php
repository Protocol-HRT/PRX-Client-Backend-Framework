<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Stable identifier: CSS hook on the frontend, reference in admin.
            $table->string('slug', 64)->unique();
            // Code blueprint slug OR flexible type slug.
            $table->string('type', 64)->index();
            $table->json('data')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_sections');
    }
};
