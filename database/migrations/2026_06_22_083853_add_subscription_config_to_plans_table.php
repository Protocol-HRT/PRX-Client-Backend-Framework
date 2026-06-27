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
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('term_months')->nullable()->after('billing_period');
            $table->boolean('is_recurring')->default(false)->after('is_featured');
            $table->string('rebill_strategy', 32)->nullable()->after('is_recurring');
            $table->unsignedSmallInteger('trial_days')->nullable()->after('rebill_strategy');
            $table->boolean('is_default')->default(false)->after('trial_days');
            $table->json('provider_product_ids')->nullable()->after('provider_plan_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'term_months',
                'is_recurring',
                'rebill_strategy',
                'trial_days',
                'is_default',
                'provider_product_ids',
            ]);
        });
    }
};
