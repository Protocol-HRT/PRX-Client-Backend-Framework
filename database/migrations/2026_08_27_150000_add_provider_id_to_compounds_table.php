<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The upstream provider's id for a compound, so monographs can be synced over
 * the API once prescribe-rx exposes an endpoint for them.
 *
 * Matches the catalog convention exactly — `provider_product_id`,
 * `provider_package_id`, `provider_ingredient_id` are all nullable indexed
 * uuids, and `last_synced_at` is the pairing `SyncCatalogCommand` already uses.
 * A future compound sync should look like the catalog one, which means it needs
 * the same two columns to key on.
 *
 * **Overlap with `source_ref`, stated rather than hidden.** For today's file
 * import the two hold the same value: the dump row's `id` IS the provider's id.
 * They are kept apart because they answer different questions —
 * `(source_system, source_ref)` is "which import produced this row", and is the
 * idempotency key for ingesting a FILE from any source; `provider_compound_id`
 * is "what prescribe-rx calls this compound", and is what an API sync reads.
 * If a second content source ever appears, the first stays correct and the
 * second would be null. Collapsing them into one column is a small change if
 * that never happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compounds', function (Blueprint $table): void {
            $table->uuid('provider_compound_id')->nullable()->after('source_ref')->index();
            $table->timestamp('last_synced_at')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('compounds', function (Blueprint $table): void {
            $table->dropIndex(['provider_compound_id']);
            $table->dropColumn(['provider_compound_id', 'last_synced_at']);
        });
    }
};
