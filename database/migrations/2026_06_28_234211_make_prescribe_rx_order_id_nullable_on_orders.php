<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_prescribe_rx_order_id_unique');
            $table->string('prescribe_rx_order_id', 64)->nullable()->change();
            $table->unique('prescribe_rx_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_prescribe_rx_order_id_unique');
            $table->string('prescribe_rx_order_id', 64)->nullable(false)->change();
            $table->unique('prescribe_rx_order_id');
        });
    }
};
