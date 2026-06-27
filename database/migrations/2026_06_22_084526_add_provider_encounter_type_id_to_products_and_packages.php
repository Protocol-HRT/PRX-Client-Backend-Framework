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
        // Per-product/package override for the intake encounter type.
        // Category-level mapping is the default; this column overrides it for a specific item.
        Schema::table('products', function (Blueprint $table): void {
            $table->string('provider_encounter_type_id')->nullable()->after('provider_product_sku');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->string('provider_encounter_type_id')->nullable()->after('provider_package_sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('provider_encounter_type_id');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('provider_encounter_type_id');
        });
    }
};
