<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->boolean('is_in_stock')->default(true)->after('is_featured');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_in_stock')->default(true)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('is_in_stock');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_in_stock');
        });
    }
};
