<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('detail_sections')->nullable()->after('highlights');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->json('detail_sections')->nullable()->after('highlights');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('detail_sections');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('detail_sections');
        });
    }
};
