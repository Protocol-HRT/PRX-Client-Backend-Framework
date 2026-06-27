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
        Schema::table('merchant_accounts', function (Blueprint $table): void {
            // Stripe credentials
            $table->text('stripe_secret_key')->nullable()->after('surcharge_passthrough');
            $table->string('stripe_publishable_key')->nullable()->after('stripe_secret_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_publishable_key');

            // Square credentials (matching PRX column names)
            $table->string('square_application_id', 128)->nullable()->after('stripe_webhook_secret');
            $table->text('square_access_token')->nullable()->after('square_application_id');
            $table->string('square_location_id', 64)->nullable()->after('square_access_token');
            $table->text('square_webhook_signature_key')->nullable()->after('square_location_id');

            // Optional gateway endpoint URL override (useful for NMI sandbox proxy, on-prem, etc.)
            $table->string('gateway_endpoint_url', 512)->nullable()->after('square_webhook_signature_key');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'stripe_secret_key',
                'stripe_publishable_key',
                'stripe_webhook_secret',
                'square_application_id',
                'square_access_token',
                'square_location_id',
                'square_webhook_signature_key',
                'gateway_endpoint_url',
            ]);
        });
    }
};
