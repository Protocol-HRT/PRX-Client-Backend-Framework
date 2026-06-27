<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('hero_image_path', 2048)->nullable();
            $table->json('gallery')->nullable();
            $table->string('status', 16)->default('draft')->index();

            $table->decimal('retail_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('price_suffix', 32)->nullable();

            $table->uuid('prescribe_rx_package_id')->nullable()->index();
            $table->string('prescribe_rx_package_number')->nullable()->index();

            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('requires_lab')->default(false);

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image_path', 2048)->nullable();

            $table->unsignedInteger('position')->default(0)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
