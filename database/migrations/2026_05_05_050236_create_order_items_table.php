<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items on an order. We store product names + quantities for
 * customer-support / fulfillment / returns visibility. With AWS BAA + RDS
 * encryption-at-rest this is acceptable PHI exposure for ops staff;
 * sensitive name field is also encrypted at the model level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('prescribe_rx_product_id', 64)->nullable()->index();
            $table->string('prescribe_rx_product_number', 64)->nullable()->index();

            // Product name as PRX prescribed it. Encrypted at the model level.
            $table->text('name')->nullable();

            // SKU detail — public catalog info, helpful for warehouse picks.
            $table->string('sku', 64)->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('line_total', 10, 2)->nullable();

            // For plan / subscription items.
            $table->string('billing_period', 32)->nullable();

            // Opaque PRX line metadata (dose product id, etc.).
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
