<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipments for an order. One order may split across multiple fulfillment
 * centers, each producing its own carrier + tracking number. Each shipment
 * carries 1+ order_items via the order_shipment_items join table.
 *
 * Webhooks tracked: shipment.created (status=pending), shipment.shipped
 * (carrier + tracking_number), shipment.delivered (delivered_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // PRX-side shipment identifier (if PRX surfaces one — fall back
            // to using carrier+tracking_number pair as a natural key).
            $table->string('prescribe_rx_shipment_id', 64)->nullable()->unique();

            $table->string('status', 32)->default('pending')->index();

            $table->string('carrier', 32)->nullable();
            $table->string('tracking_number', 128)->nullable()->index();
            $table->string('tracking_url', 2048)->nullable();

            // Free-form FC code/name (e.g. "FC-AUSTIN-1") so ops can route
            // returns or see split-ship patterns.
            $table->string('fulfillment_center', 64)->nullable()->index();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('exception_at')->nullable();
            $table->text('exception_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
        });

        // Many-to-many between shipments and items. An item can ship in
        // pieces across FCs (split-ship), and a shipment can carry many
        // items, so we explicitly track a quantity on the join row.
        Schema::create('order_shipment_items', function (Blueprint $table) {
            $table->foreignId('order_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->primary(['order_shipment_id', 'order_item_id'], 'order_shipment_items_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipment_items');
        Schema::dropIfExists('order_shipments');
    }
};
