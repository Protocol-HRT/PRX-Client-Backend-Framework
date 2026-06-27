<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Models\Catalog\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_index_returns_visible_categories(): void
    {
        Category::factory()->create(['is_visible' => true, 'name' => 'Visible']);
        Category::factory()->create(['is_visible' => false, 'name' => 'Hidden']);

        $this->getJson('/api/v1/catalog/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible');
    }

    public function test_categories_flat_list_includes_parent_reference(): void
    {
        $parent = Category::factory()->create(['is_visible' => true]);
        Category::factory()->create(['is_visible' => true, 'parent_id' => $parent->id]);

        $response = $this->getJson('/api/v1/catalog/categories');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_categories_tree_returns_only_top_level_with_children(): void
    {
        $parent = Category::factory()->create(['is_visible' => true]);
        $child = Category::factory()->create(['is_visible' => true, 'parent_id' => $parent->id]);

        $this->getJson('/api/v1/catalog/categories?tree=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parent->id)
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.id', $child->id);
    }

    public function test_category_show_returns_single_visible_category(): void
    {
        $category = Category::factory()->create([
            'is_visible' => true,
            'provider_encounter_type_id' => 'hrt-basic',
        ]);

        $this->getJson("/api/v1/catalog/categories/{$category->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $category->slug)
            ->assertJsonPath('data.provider_encounter_type_id', 'hrt-basic');
    }

    public function test_category_show_returns_404_for_hidden(): void
    {
        $category = Category::factory()->create(['is_visible' => false]);

        $this->getJson("/api/v1/catalog/categories/{$category->slug}")->assertNotFound();
    }

    public function test_category_show_includes_children(): void
    {
        $parent = Category::factory()->create(['is_visible' => true]);
        $child = Category::factory()->create(['is_visible' => true, 'parent_id' => $parent->id]);

        $this->getJson("/api/v1/catalog/categories/{$parent->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.children')
            ->assertJsonPath('data.children.0.id', $child->id);
    }
}
