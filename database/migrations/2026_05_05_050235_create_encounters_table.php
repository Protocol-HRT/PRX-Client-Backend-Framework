<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of prescribe-rx encounter state.
 *
 * No clinical answers / lab values / medication strings are stored here.
 * We track lifecycle (status, key timestamps), totals, and PRX-side IDs so
 * admin operators can see "where is this customer in the funnel" without
 * needing access to the prescribe-rx admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

            // prescribe-rx identifiers (uuid format on PRX side).
            $table->string('prescribe_rx_encounter_id', 64)->unique();
            $table->string('prescribe_rx_patient_id', 64)->nullable()->index();
            $table->string('prescribe_rx_encounter_type_id', 64)->nullable();

            $table->string('status', 32)->default('pending')->index();

            // Lifecycle timestamps. Filled as PRX webhooks roll in.
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->decimal('total_amount', 10, 2)->nullable();

            // Sandbox flag from PRX (so admin can filter test encounters).
            $table->boolean('is_sandbox')->default(false)->index();

            // Opaque PRX metadata pass-through (encounter type slug, etc.).
            // No clinical fields permitted — enforced at webhook handler.
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
