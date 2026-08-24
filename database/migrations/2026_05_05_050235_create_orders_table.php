<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of prescribe-rx orders. One encounter can produce multiple
 * orders (e.g. recurring subscription refills), so this is hasMany on
 * encounters. Multi-fulfillment-center shipments are tracked on a separate
 * order_shipments table since one order can ship from multiple FCs with
 * independent tracking numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();

            $table->string('prescribe_rx_order_id', 64)->unique();
            $table->string('prescribe_rx_order_number', 64)->nullable()->index();

            $table->string('status', 32)->default('pending')->index();

            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('shipping_amount', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            // Snapshot of where the order is shipping. Encrypted at the model
            // level — could be PHI when paired with order line items.
            $table->text('shipping_address')->nullable();

            // Optional billing address snapshot, if ever distinct.
            $table->text('billing_address')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Opaque PRX metadata; no clinical fields.
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
