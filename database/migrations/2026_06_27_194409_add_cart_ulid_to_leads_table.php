<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            // Links a lead to the anonymous cart session that created it.
            // Checked at checkout to prevent cross-session cart/lead pairings.
            $table->string('cart_ulid', 26)->nullable()->after('uuid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['cart_ulid']);
            $table->dropColumn('cart_ulid');
        });
    }
};
