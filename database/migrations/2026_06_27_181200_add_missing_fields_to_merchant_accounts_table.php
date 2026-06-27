<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_accounts', function (Blueprint $table): void {
            // NMI: public key for Collect.js client-side tokenization (plain — safe to expose)
            $table->string('nmi_public_key')->nullable()->after('nmi_security_key');

            // Authorize.Net: webhook signature key for HMAC verification of inbound webhooks
            $table->text('authnet_signature_key')->nullable()->after('authnet_public_client_key');
            // Authorize.Net: Customer Information Manager (CIM) vault support
            $table->boolean('cim_enabled')->default(false)->after('authnet_signature_key');

            // Capability flags — mirror PRX's routing/eligibility logic
            $table->boolean('allows_rx_processing')->default(true)->after('allows_recurring_payments');
            $table->boolean('allows_card_present')->default(false)->after('allows_rx_processing');
            $table->boolean('allows_card_not_present')->default(true)->after('allows_card_present');
            $table->boolean('supports_public_checkout')->default(true)->after('allows_card_not_present');

            // Surcharge — set once on account, all orders routed here inherit
            $table->decimal('surcharge_rate', 6, 4)->nullable()->default(null);
            $table->decimal('surcharge_flat_per_txn', 8, 2)->nullable()->default(null);
            $table->boolean('surcharge_passthrough')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('merchant_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'nmi_public_key',
                'authnet_signature_key',
                'cim_enabled',
                'allows_rx_processing',
                'allows_card_present',
                'allows_card_not_present',
                'supports_public_checkout',
                'surcharge_rate',
                'surcharge_flat_per_txn',
                'surcharge_passthrough',
            ]);
        });
    }
};
