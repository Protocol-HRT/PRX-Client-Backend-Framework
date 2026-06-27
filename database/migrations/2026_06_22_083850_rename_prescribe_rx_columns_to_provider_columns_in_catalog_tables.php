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
        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('prescribe_rx_product_id', 'provider_product_id');
            $table->renameColumn('prescribe_rx_product_number', 'provider_product_sku');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->renameColumn('prescribe_rx_package_id', 'provider_package_id');
            $table->renameColumn('prescribe_rx_package_number', 'provider_package_sku');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->renameColumn('prescribe_rx_plan_id', 'provider_plan_id');
            $table->renameColumn('prescribe_rx_plan_number', 'provider_plan_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('provider_product_id', 'prescribe_rx_product_id');
            $table->renameColumn('provider_product_sku', 'prescribe_rx_product_number');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->renameColumn('provider_package_id', 'prescribe_rx_package_id');
            $table->renameColumn('provider_package_sku', 'prescribe_rx_package_number');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->renameColumn('provider_plan_id', 'prescribe_rx_plan_id');
            $table->renameColumn('provider_plan_sku', 'prescribe_rx_plan_number');
        });
    }
};
