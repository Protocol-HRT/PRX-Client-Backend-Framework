<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each node in the tree earned on one conversion.
 *
 * COMPUTED ONCE, AT CONVERSION, AND NEVER RECOMPUTED. The rate that applied is
 * snapshotted onto the row, so renegotiating an affiliate's percentage next
 * quarter cannot silently rewrite what they were owed last quarter. A commission
 * system that recalculates from live rates cannot answer "why was I paid this",
 * which is the only question anyone ever asks of it.
 *
 * ONE ROW PER (conversion, node in the chain). A sale by a leaf partner under two
 * levels of group writes three rows: the partner's own, and an override band for
 * each ancestor. Reading "what do we owe this quarter" is then a sum with a
 * where, not a tree walk — and the tree can be reorganised afterwards without
 * changing a single settled number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The conversion. RESTRICT because deleting a paid-on lead would
            // orphan money that has been, or is about to be, paid out.
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();

            $table->foreignId('referral_source_id')->nullable()
                ->constrained('referral_sources')->nullOnDelete();

            // Snapshots, for the same reason the click ledger carries them: the
            // row must still name the payee after the source record is gone.
            $table->string('source_slug')->nullable();
            $table->string('source_name')->nullable();
            $table->string('referral_code', 64)->nullable();

            // 0 = the source that owns the code and made the sale; 1 = its
            // parent's override; 2 = the grandparent's, and so on.
            $table->unsignedSmallInteger('tier')->default(0);

            $table->decimal('basis_amount', 10, 2);
            // The percentage AS IT WAS. Not a join to the source's current rate.
            $table->decimal('rate', 5, 2);
            $table->decimal('amount', 10, 2);

            // pending → approved → paid, or void. Money leaves on `paid`.
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();

            $table->timestamps();

            // One band per node per conversion — recomputing must update in
            // place rather than double-pay.
            $table->unique(['lead_id', 'referral_source_id'], 'referral_commissions_lead_source_unique');
            $table->index(['referral_source_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
