<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_relations', function (Blueprint $table): void {
            $table->id();
            $table->morphs('source');
            $table->morphs('related');
            $table->string('relation_type', 32)->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'related_type', 'related_id', 'relation_type'],
                'catalog_relations_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_relations');
    }
};
