<?php

use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // orders.patient_id may already exist if the column was added before this migration ran
        if (! Schema::hasColumn('orders', 'patient_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('patient_id')->nullable()->after('encounter_id')
                    ->constrained('patients')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('leads', 'patient_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('patient_id')->nullable()->after('id')
                    ->constrained('patients')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'patient_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeignIdFor(Patient::class);
            });
        }

        if (Schema::hasColumn('leads', 'patient_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeignIdFor(Patient::class);
            });
        }
    }
};
