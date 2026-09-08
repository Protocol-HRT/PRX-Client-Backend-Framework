<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trackable code belonging to one referral source.
 *
 * TWO THINGS THIS TABLE DELIBERATELY DOES NOT HAVE, both learned from reading
 * prescribe-rx's own equivalent (`prx-demo/database/migrations/*referral_tracking_links*`):
 *
 *  1. NO `clicks` / `conversions` COUNTER COLUMNS. Commissions are money and must
 *     be reconstructable after the fact; an incremented integer cannot be audited
 *     or disputed. Counts are DERIVED from `referral_clicks` rows. Their version
 *     carries counters and it is the reason theirs cannot answer "which clicks".
 *
 *  2. NO `cascadeOnDelete` ON THE SOURCE. Theirs cascades, and because their
 *     `leads.tracking_link_id` is nullOnDelete, deleting one referral source
 *     silently nulls the attribution on every lead it ever produced. Here the FK
 *     RESTRICTS: a source with links cannot be deleted at all, which turns a
 *     silent data loss into a visible error at the only moment anyone can fix it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('referral_source_id')
                ->constrained('referral_sources')
                ->restrictOnDelete();

            // The public code, as it appears in ?ref=. Case-insensitively unique
            // in practice — resolution lowercases before lookup, and the column
            // is stored lowercased by the action that mints it.
            $table->string('code', 64)->unique();

            $table->string('campaign_name')->nullable();

            // Where this link lands. A path on the storefront, never an absolute
            // URL — this backend does not own the frontend's URLs.
            $table->string('destination_path', 512)->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['referral_source_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_links');
    }
};
