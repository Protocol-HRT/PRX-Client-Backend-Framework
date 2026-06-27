<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Soft-disable an admin without deleting (revoke panel access).
            $table->boolean('is_active')->default(true)->index()->after('password');

            // Set by Filament's login hook so we can show "Last login N hours ago".
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            // For invited admins who haven't set a password yet.
            $table->timestamp('invited_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_login_at', 'invited_at']);
        });
    }
};
