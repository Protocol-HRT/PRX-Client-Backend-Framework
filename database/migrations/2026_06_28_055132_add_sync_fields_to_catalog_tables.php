<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'packages', 'plans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('last_synced_at')->nullable()->after('position');
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'packages', 'plans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('last_synced_at');
            });
        }
    }
};
