<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('concentration', 10, 4)->nullable();
            $table->foreignId('concentration_unit_id')->nullable()->constrained('measurement_units')->nullOnDelete();
            $table->decimal('per_volume', 10, 4)->nullable();
            $table->foreignId('per_volume_unit_id')->nullable()->constrained('measurement_units')->nullOnDelete();
            $table->string('provider_quantity_label')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['ingredient_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_product');
    }
};
