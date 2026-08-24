<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_subscribers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email')->unique();

            // Optional name + phone — set if the user later submits a fuller form.
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();

            // Where the subscription came from. Free-form to avoid coupling to enums.
            // Examples: 'footer', 'pricing-peptide-waitlist', 'cart-checkout', 'concierge', 'manual'.
            $table->string('source', 64)->index();

            // Status — subscribed / unsubscribed / bounced. Subscribed by default.
            $table->string('status', 16)->default('subscribed')->index();

            // Consents.
            $table->boolean('email_consent')->default(true);
            $table->boolean('sms_consent')->default(false);
            $table->timestamp('consent_given_at')->nullable();

            // Marketing attribution.
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('landing_url', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();

            // Catch-all for source-specific extras (e.g. waitlist product ids).
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_subscribers');
    }
};
