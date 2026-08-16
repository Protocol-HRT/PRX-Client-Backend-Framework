<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `mode` splits DB-defined section types into `active` (live in the
     * registry — admin-created custom types, and code types whose seeded
     * definition has been promoted) and `shadow` (seeded mirror of a code
     * blueprint awaiting golden-parity promotion; the code definition keeps
     * winning until then).
     */
    public function up(): void
    {
        Schema::table('flexible_section_types', function (Blueprint $table) {
            $table->string('mode', 16)->default('active')->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('flexible_section_types', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
