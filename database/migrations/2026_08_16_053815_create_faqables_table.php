<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqables', function (Blueprint $table) {
            $table->foreignId('faq_item_id')->constrained()->cascadeOnDelete();
            $table->morphs('faqable');
            $table->unsignedInteger('position')->default(0);
            $table->primary(['faq_item_id', 'faqable_id', 'faqable_type'], 'faqables_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqables');
    }
};
