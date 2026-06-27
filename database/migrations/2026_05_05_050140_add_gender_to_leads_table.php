<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Optional. Used to prefill the prescribe-rx embed personal-info
            // step. Free-form to match PRX's flexibility (accepts
            // male/female/other or 1/2/3 or self-described strings).
            $table->string('gender', 32)->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
