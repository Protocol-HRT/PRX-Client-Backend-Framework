<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('hero_image_path', 2048)->nullable();
            $table->json('gallery')->nullable();
            $table->string('status', 16)->default('draft')->index();

            // Display pricing — PRX is source of truth at transaction time.
            $table->decimal('retail_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('price_suffix', 32)->nullable();

            // prescribe-rx mapping — accept either the UUID or the human-friendly number.
            $table->uuid('prescribe_rx_product_id')->nullable()->index();
            $table->string('prescribe_rx_product_number')->nullable()->index();

            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('requires_lab')->default(false);

            // SEO overrides — fall through to global SeoSettings when blank.
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
        Schema::dropIfExists('products');
    }
};
