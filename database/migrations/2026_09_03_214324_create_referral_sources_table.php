<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who gets credited for a referral: an affiliate, a sales group, a partner.
 *
 * DELIBERATELY PROVIDER-AGNOSTIC. Attribution is computed and owned entirely on
 * this side — prescribe-rx's intake embed pins one sales organization per embed
 * code and exposes no per-session override, and their tracking-link system has no
 * API at all, so riding on their org tree was never actually available. Keeping
 * it local is also the only shape that ships in a generic backend: a deployment
 * that never uses prescribe-rx still needs to pay its affiliates.
 *
 * `parent_id` is an ARBITRARY-DEPTH tree — a national group over regions over
 * individual partners, as deep as the commercial structure goes. One nullable
 * self-reference is the whole schema; the recursion lives in
 * `staudenmeir/laravel-adjacency-list`, the same package prescribe-rx uses, so
 * both systems describe a sales organisation the same way.
 *
 * (This comment originally said "one level". That was wrong and the operator
 * caught it: a real sales org has managers under managers, and flattening it
 * makes "my whole downline's numbers" unanswerable.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // affiliate | sales_group | partner | internal.
            $table->string('type', 32)->default('affiliate')->index();
            $table->string('name');
            $table->string('slug')->unique();

            // A sales group owns affiliates. nullOnDelete, never cascade: losing
            // a parent must orphan its children, never delete them, because a
            // child holds commission history of its own.
            $table->foreignId('parent_id')->nullable()
                ->constrained('referral_sources')->nullOnDelete();

            // Prefix for codes minted under this source (e.g. "LT" -> "LT-4F2A").
            // Unique so two sources can never mint colliding codes.
            $table->string('code_prefix', 12)->unique()->nullable();

            $table->string('company_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();

            // Commission terms live here as data, never as code. Nullable
            // because a source may exist before its terms are agreed.
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('commission_notes')->nullable();

            $table->boolean('portal_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_sources');
    }
};
