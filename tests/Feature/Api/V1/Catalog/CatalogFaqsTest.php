<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Content\FaqCategory;
use App\Models\Content\FaqItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFaqsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_exposes_attached_published_faqs_in_pivot_order(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        $category = FaqCategory::factory()->create(['name' => 'Dosage']);

        $first = FaqItem::factory()->create(['question' => 'How do I store it?']);
        $second = FaqItem::factory()->create([
            'question' => 'How often do I inject?',
            'faq_category_id' => $category->id,
        ]);
        $hidden = FaqItem::factory()->unpublished()->create(['question' => 'Hidden question?']);

        $product->faqs()->attach([
            $first->id => ['position' => 2],
            $second->id => ['position' => 1],
            $hidden->id => ['position' => 3],
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'How often do I inject?')
            ->assertJsonPath('data.faqs.0.category', 'Dosage')
            ->assertJsonPath('data.faqs.1.question', 'How do I store it?')
            ->assertJsonPath('data.faqs.1.category', null);
    }

    public function test_package_show_exposes_attached_published_faqs(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        $faq = FaqItem::factory()->create(['question' => 'What is in the stack?']);

        $package->faqs()->attach([$faq->id => ['position' => 1]]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'What is in the stack?');
    }

    public function test_unattached_faqs_do_not_appear_on_a_product(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        FaqItem::factory()->create(['question' => 'Global question?']);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(0, 'data.faqs');
    }

    public function test_product_index_does_not_expose_faqs(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        $product->faqs()->attach(FaqItem::factory()->create());

        $response = $this->getJson('/api/v1/catalog/products')->assertOk();

        $this->assertArrayNotHasKey('faqs', $response->json('data.0'));
    }

    public function test_faq_attachment_is_shared_between_records(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        $faq = FaqItem::factory()->create(['question' => 'Shared question?']);

        $product->faqs()->attach([$faq->id => ['position' => 1]]);
        $package->faqs()->attach([$faq->id => ['position' => 4]]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertJsonPath('data.faqs.0.question', 'Shared question?');
        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertJsonPath('data.faqs.0.question', 'Shared question?');

        $this->assertSame(1, $faq->products()->count());
        $this->assertSame(1, $faq->packages()->count());
    }
}
