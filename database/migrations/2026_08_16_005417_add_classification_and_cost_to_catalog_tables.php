<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_class_id')->nullable()->after('status')->constrained('product_classes')->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->after('product_class_id')->constrained('product_types')->nullOnDelete();
            $table->foreignId('product_form_id')->nullable()->after('product_type_id')->constrained('product_forms')->nullOnDelete();
            $table->foreignId('administration_method_id')->nullable()->after('product_form_id')->constrained('administration_methods')->nullOnDelete();
            $table->decimal('volume', 10, 4)->nullable()->after('administration_method_id');
            $table->foreignId('volume_unit_id')->nullable()->after('volume')->constrained('measurement_units')->nullOnDelete();
            $table->string('inventory_status', 24)->nullable()->index()->after('is_in_stock');
            $table->boolean('is_controlled_substance')->default(false)->after('inventory_status');
            $table->boolean('rx_required')->default(false)->after('is_controlled_substance');
            $table->decimal('cost', 10, 2)->nullable()->after('sale_price');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->decimal('cost', 10, 2)->nullable()->after('sale_price');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->decimal('cost', 10, 2)->nullable()->after('sale_price');
            $table->string('billing_mode', 24)->nullable()->index()->after('billing_period');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['cost', 'billing_mode']);
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('cost');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_class_id');
            $table->dropConstrainedForeignId('product_type_id');
            $table->dropConstrainedForeignId('product_form_id');
            $table->dropConstrainedForeignId('administration_method_id');
            $table->dropConstrainedForeignId('volume_unit_id');
            $table->dropColumn(['volume', 'inventory_status', 'is_controlled_substance', 'rx_required', 'cost']);
        });
    }
};
