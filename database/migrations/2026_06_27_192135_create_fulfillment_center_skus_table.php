<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_center_skus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fulfillment_center_id')->constrained()->cascadeOnDelete();

            // Polymorphic — can be App\Models\Catalog\Product, Package, or Plan
            $table->string('fulfillmentable_type');
            $table->unsignedBigInteger('fulfillmentable_id');

            // FC-internal identifier for this item
            $table->string('sku');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fulfillment_center_id', 'fulfillmentable_type', 'fulfillmentable_id'], 'fc_skus_unique');
            $table->index(['fulfillmentable_type', 'fulfillmentable_id'], 'fc_skus_morphable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_center_skus');
    }
};
