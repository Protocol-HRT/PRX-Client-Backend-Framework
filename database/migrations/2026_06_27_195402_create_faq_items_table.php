<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faq_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question');
            $table->longText('answer');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['faq_category_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
    }
};
