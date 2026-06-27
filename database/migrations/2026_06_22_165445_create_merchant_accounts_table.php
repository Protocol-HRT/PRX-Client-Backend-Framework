<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('gateway_provider', 32)->index();
            $table->string('environment', 16)->default('sandbox');

            // NMI credentials (encrypted at the model layer)
            $table->text('nmi_security_key')->nullable();

            // Authorize.Net credentials (encrypted at the model layer)
            $table->text('authnet_api_login_id')->nullable();
            $table->text('authnet_transaction_key')->nullable();
            $table->string('authnet_public_client_key')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->integer('transaction_weight')->default(1);
            $table->decimal('monthly_volume_limit', 12, 2)->nullable();
            $table->decimal('monthly_volume_used', 12, 2)->default(0);
            $table->boolean('auto_disable_at_limit')->default(false);
            $table->timestamp('auto_disabled_at')->nullable();
            $table->date('reactivate_on')->nullable();
            $table->boolean('allows_recurring_payments')->default(false);
            $table->json('notification_thresholds')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_accounts');
    }
};
