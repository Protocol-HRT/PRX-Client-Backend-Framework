<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'packages', 'plans'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('default_fulfillment_center_id')
                    ->nullable()
                    ->after('position')
                    ->constrained('fulfillment_centers')
                    ->nullOnDelete();
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('fulfillment_center_id')
                ->nullable()
                ->after('encounter_id')
                ->constrained('fulfillment_centers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['fulfillment_center_id']);
            $table->dropColumn('fulfillment_center_id');
        });

        foreach (['plans', 'packages', 'products'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropForeign(['default_fulfillment_center_id']);
                $table->dropColumn('default_fulfillment_center_id');
            });
        }
    }
};
