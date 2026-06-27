<?php

namespace Tests\Feature\Api\V1\Blog;

use App\Enums\PostStatus;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ── Posts index ──────────────────────────────────────────────────────

    public function test_posts_index_returns_only_published_posts(): void
    {
        BlogPost::factory()->create(['status' => PostStatus::Published, 'title' => 'Live Post']);
        BlogPost::factory()->create(['status' => PostStatus::Draft, 'title' => 'Hidden Draft']);
        BlogPost::factory()->create(['status' => PostStatus::Archived, 'title' => 'Hidden Archived']);

        $this->getJson('/api/v1/blog/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Live Post');
    }

    public function test_posts_index_excludes_future_scheduled_posts(): void
    {
        BlogPost::factory()->create([
            'status' => PostStatus::Published,
            'published_at' => now()->addDay(),
            'title' => 'Future Post',
        ]);
        BlogPost::factory()->create([
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'title' => 'Past Post',
        ]);

        $this->getJson('/api/v1/blog/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Past Post');
    }

    public function test_posts_index_filters_by_category_slug(): void
    {
        $cat = BlogCategory::factory()->create(['is_visible' => true]);
        $inCategory = BlogPost::factory()->create(['status' => PostStatus::Published]);
        $notInCategory = BlogPost::factory()->create(['status' => PostStatus::Published]);
        $inCategory->categories()->attach($cat->id);

        $this->getJson("/api/v1/blog/posts?category={$cat->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inCategory->id);
    }

    public function test_posts_index_filters_by_featured(): void
    {
        BlogPost::factory()->create(['status' => PostStatus::Published, 'featured' => true]);
        BlogPost::factory()->create(['status' => PostStatus::Published, 'featured' => false]);

        $this->getJson('/api/v1/blog/posts?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_posts_index_searches_by_title(): void
    {
        BlogPost::factory()->create(['status' => PostStatus::Published, 'title' => 'Hormone Therapy Guide']);
        BlogPost::factory()->create(['status' => PostStatus::Published, 'title' => 'Nutrition Tips']);

        $this->getJson('/api/v1/blog/posts?search=Hormone')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Hormone Therapy Guide');
    }

    public function test_posts_index_is_paginated(): void
    {
        BlogPost::factory()->count(20)->create(['status' => PostStatus::Published]);

        $this->getJson('/api/v1/blog/posts')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_posts_index_per_page_is_capped_at_50(): void
    {
        BlogPost::factory()->count(5)->create(['status' => PostStatus::Published]);

        $this->getJson('/api/v1/blog/posts?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    // ── Posts show ───────────────────────────────────────────────────────

    public function test_posts_show_returns_single_published_post(): void
    {
        $post = BlogPost::factory()->create([
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson("/api/v1/blog/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $post->slug)
            ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'content', 'seo']]);
    }

    public function test_posts_show_returns_404_for_draft(): void
    {
        $post = BlogPost::factory()->create(['status' => PostStatus::Draft]);

        $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertNotFound();
    }

    public function test_posts_show_returns_404_for_archived_post(): void
    {
        $post = BlogPost::factory()->create(['status' => PostStatus::Archived]);

        $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertNotFound();
    }

    public function test_posts_show_includes_seo_fields(): void
    {
        $post = BlogPost::factory()->create([
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'meta_title' => 'Custom SEO Title',
        ]);

        $this->getJson("/api/v1/blog/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.seo.meta_title', 'Custom SEO Title');
    }

    public function test_posts_show_includes_content_on_show_route_only(): void
    {
        $post = BlogPost::factory()->create([
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'content' => 'Full article body.',
        ]);

        // Show route should include content
        $this->getJson("/api/v1/blog/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.content', 'Full article body.');

        // Index route should not include content
        $response = $this->getJson('/api/v1/blog/posts')->assertOk();
        $this->assertArrayNotHasKey('content', $response->json('data.0'));
    }

    // ── Categories index ─────────────────────────────────────────────────

    public function test_categories_index_returns_visible_categories(): void
    {
        BlogCategory::factory()->create(['name' => 'Visible Cat', 'is_visible' => true]);
        BlogCategory::factory()->create(['name' => 'Hidden Cat', 'is_visible' => false]);

        $this->getJson('/api/v1/blog/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible Cat');
    }

    public function test_categories_index_response_has_correct_shape(): void
    {
        BlogCategory::factory()->create(['is_visible' => true]);

        $this->getJson('/api/v1/blog/categories')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'description', 'is_visible']],
            ]);
    }

    // ── Categories show ──────────────────────────────────────────────────

    public function test_categories_show_returns_visible_category(): void
    {
        $cat = BlogCategory::factory()->create(['is_visible' => true]);

        $this->getJson("/api/v1/blog/categories/{$cat->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $cat->slug);
    }

    public function test_categories_show_returns_404_for_hidden_category(): void
    {
        $cat = BlogCategory::factory()->create(['is_visible' => false]);

        $this->getJson("/api/v1/blog/categories/{$cat->slug}")->assertNotFound();
    }

    // ── Tags index ───────────────────────────────────────────────────────

    public function test_tags_index_returns_visible_tags(): void
    {
        BlogTag::factory()->create(['name' => 'Visible Tag', 'is_visible' => true]);
        BlogTag::factory()->create(['name' => 'Hidden Tag', 'is_visible' => false]);

        $this->getJson('/api/v1/blog/tags')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible Tag');
    }
}
