<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetailSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_exposes_normalized_detail_sections(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'detail_sections' => [
                ['title' => 'How To Use', 'placement' => 'accordion', 'content' => 'Take as directed.'],
                ['title' => 'Science', 'placement' => 'tab', 'content' => 'Peer-reviewed.'],
                ['title' => '', 'placement' => 'accordion', 'content' => 'orphan row dropped'],
                ['title' => 'Bad placement', 'placement' => 'sidebar', 'content' => 'defaults to accordion'],
            ],
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(3, 'data.detail_sections')
            ->assertJsonPath('data.detail_sections.0.title', 'How To Use')
            ->assertJsonPath('data.detail_sections.0.placement', 'accordion')
            ->assertJsonPath('data.detail_sections.1.placement', 'tab')
            ->assertJsonPath('data.detail_sections.2.placement', 'accordion');
    }

    public function test_detail_sections_excluded_from_index_and_empty_when_unset(): void
    {
        Product::factory()->create(['status' => CatalogStatus::Published, 'detail_sections' => [
            ['title' => 'T', 'placement' => 'accordion', 'content' => 'C'],
        ]]);

        $index = $this->getJson('/api/v1/catalog/products')->assertOk();
        $this->assertArrayNotHasKey('detail_sections', $index->json('data.0'));

        $bare = Product::factory()->create(['status' => CatalogStatus::Published]);
        $this->getJson("/api/v1/catalog/products/{$bare->slug}")
            ->assertOk()
            ->assertJsonPath('data.detail_sections', []);
    }

    public function test_package_show_exposes_detail_sections(): void
    {
        $package = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'detail_sections' => [
                ['title' => 'What Is Included', 'placement' => 'tab', 'content' => 'Everything.'],
            ],
        ]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.detail_sections.0.title', 'What Is Included');
    }
}
