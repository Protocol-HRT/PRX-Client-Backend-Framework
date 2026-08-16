<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_name', 64)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->uuid('provider_product_class_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_class_id')->nullable()->constrained('product_classes')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_name', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->uuid('provider_product_type_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_name', 64)->nullable();
            $table->longText('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->uuid('provider_ingredient_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('administration_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('abbreviation', 16)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->unsignedSmallInteger('provider_value')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_forms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_volume')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->unsignedSmallInteger('provider_value')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('measurement_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64);
            $table->string('abbreviation', 24);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->unsignedSmallInteger('provider_value')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_units');
        Schema::dropIfExists('product_forms');
        Schema::dropIfExists('administration_methods');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('product_classes');
    }
};
