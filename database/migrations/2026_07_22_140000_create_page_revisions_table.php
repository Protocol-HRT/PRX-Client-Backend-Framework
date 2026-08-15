<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            // {page: {...attrs}, sections: [{type, position, enabled, anchor_id,
            //  data, global_section_id, global_data}]} — global_data is a captured
            // copy so restores survive a later deletion of the referenced global.
            $table->json('snapshot');
            // sha256 of the canonical snapshot — consecutive identical states
            // are not stored twice.
            $table->string('content_hash', 64)->index();
            $table->string('cause', 32);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
