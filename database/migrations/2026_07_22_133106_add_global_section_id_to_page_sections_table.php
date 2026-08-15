<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_sections', function (Blueprint $table) {
            // When set, this row renders the referenced global's type + data and
            // its own `data` is ignored (`type` stays mirrored for filtering).
            // nullOnDelete is the DB-level safety net; the delete action refuses
            // to remove a referenced global before this can ever fire.
            $table->foreignId('global_section_id')
                ->nullable()
                ->after('data')
                ->constrained('global_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('page_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('global_section_id');
        });
    }
};
