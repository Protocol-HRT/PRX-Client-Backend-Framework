<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('new')->index();

            // Personal info — collected locally before PRX embed handoff.
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();

            // Address. Used to prefill the PRX embed.
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 8)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country', 2)->default('US');

            // Consents (local-only — PRX collects its own consents inside the embed).
            $table->boolean('sms_consent')->default(false);
            $table->boolean('email_consent')->default(false);
            $table->timestamp('consent_given_at')->nullable();

            // Cart snapshot — what they had selected at lead-capture time.
            $table->json('cart_items')->nullable();
            $table->decimal('cart_subtotal', 10, 2)->nullable();
            $table->string('checkout_path', 32)->nullable(); // local|prx — locked at capture time.

            // Marketing attribution.
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('landing_url', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_address', 45)->nullable();

            // prescribe-rx handoff state.
            $table->string('prescribe_rx_encounter_id')->nullable()->index();
            $table->string('prescribe_rx_patient_id')->nullable()->index();
            $table->timestamp('handed_off_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('prescribe_rx_response')->nullable(); // last response payload for debugging.

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
