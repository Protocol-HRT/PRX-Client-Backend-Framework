<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Content\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_exposes_approved_reviews_and_rating_aggregate(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        Review::factory()->rating(5)->for($product, 'reviewable')->create([
            'author_name' => 'Sarah M.',
            'reviewed_at' => now()->subDay(),
        ]);
        Review::factory()->rating(4)->for($product, 'reviewable')->create([
            'author_name' => 'James K.',
            'reviewed_at' => now()->subWeek(),
        ]);
        Review::factory()->rating(1)->unapproved()->for($product, 'reviewable')->create();

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'data.reviews')
            ->assertJsonPath('data.rating.average', 4.5)
            ->assertJsonPath('data.rating.count', 2)
            ->assertJsonPath('data.reviews.0.author_name', 'Sarah M.')
            ->assertJsonPath('data.reviews.1.author_name', 'James K.');
    }

    public function test_rating_is_null_with_no_approved_reviews(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        Review::factory()->unapproved()->for($product, 'reviewable')->create();

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.rating', null)
            ->assertJsonCount(0, 'data.reviews');
    }

    public function test_package_show_exposes_reviews(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        Review::factory()->rating(5)->for($package, 'reviewable')->create(['author_name' => 'Dana R.']);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.rating.average', 5)
            ->assertJsonPath('data.rating.count', 1)
            ->assertJsonPath('data.reviews.0.author_name', 'Dana R.');
    }

    public function test_product_index_does_not_expose_reviews(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        Review::factory()->for($product, 'reviewable')->create();

        $response = $this->getJson('/api/v1/catalog/products')->assertOk();

        $this->assertArrayNotHasKey('reviews', $response->json('data.0'));
        $this->assertArrayNotHasKey('rating', $response->json('data.0'));
    }

    public function test_rating_summary_helper_matches_api_aggregate(): void
    {
        $product = Product::factory()->create();
        Review::factory()->rating(5)->for($product, 'reviewable')->create();
        Review::factory()->rating(2)->for($product, 'reviewable')->create();
        Review::factory()->rating(1)->unapproved()->for($product, 'reviewable')->create();

        $this->assertSame(['average' => 3.5, 'count' => 2], $product->ratingSummary());
        $this->assertNull(Product::factory()->create()->ratingSummary());
    }
}
