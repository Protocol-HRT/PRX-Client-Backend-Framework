<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_centers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('system_type', 32);
            $table->string('environment', 16)->default('production');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_default_rx')->default(false);
            $table->boolean('is_default_non_rx')->default(false);

            // Prescribe-Rx ref — only relevant when system_type = prescribe_rx
            $table->string('prescribe_rx_fc_id', 64)->nullable();

            // Address
            $table->string('street_1')->nullable();
            $table->string('street_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 8)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country_code', 2)->default('US');

            // Contact
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('main_contact')->nullable();

            // Generic API credentials (shared across most provider types)
            $table->string('api_endpoint', 512)->nullable();
            $table->text('api_key')->nullable();     // encrypted
            $table->text('api_secret')->nullable();   // encrypted
            $table->text('api_token')->nullable();    // encrypted

            // ShipStation-specific (dedicated column so it can be used in queries)
            $table->string('shipstation_warehouse_id', 64)->nullable();

            // Catch-all encrypted JSON for provider-specific extras
            $table->text('settings')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('system_type');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_centers');
    }
};
