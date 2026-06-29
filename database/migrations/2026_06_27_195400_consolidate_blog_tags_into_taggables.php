<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges the blog-specific tag system (blog_tags + blog_tag_post) into the
 * existing polymorphic tags + taggables tables.
 *
 * Strategy:
 *   1. Insert each blog_tag row into tags (new auto-increment ID, preserving slug/name/color).
 *   2. Insert blog_tag_post pivot rows into taggables using the new tag IDs.
 *   3. Drop blog_tag_post, then blog_tags.
 *
 * BlogPost::class FQCN must match what Laravel writes into taggable_type.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Copy blog_tags rows into tags, capturing old_id → new_id mapping.
        $blogTags = DB::table('blog_tags')->get();
        $idMap = [];

        foreach ($blogTags as $blogTag) {
            // Handle rare slug collision: append _blog suffix to deduplicate.
            $slug = $blogTag->slug;
            if (DB::table('tags')->where('slug', $slug)->exists()) {
                $slug = $slug.'-blog';
            }

            $newId = DB::table('tags')->insertGetId([
                'name' => $blogTag->name,
                'slug' => $slug,
                'color' => $blogTag->color,
                'is_visible' => $blogTag->is_visible,
                'position' => $blogTag->position,
                'created_at' => $blogTag->created_at,
                'updated_at' => $blogTag->updated_at,
                'deleted_at' => $blogTag->deleted_at,
            ]);

            $idMap[$blogTag->id] = $newId;
        }

        // Copy blog_tag_post pivot rows into taggables.
        $pivotRows = DB::table('blog_tag_post')->get();
        foreach ($pivotRows as $pivot) {
            if (! isset($idMap[$pivot->blog_tag_id])) {
                continue;
            }

            DB::table('taggables')->insertOrIgnore([
                'tag_id' => $idMap[$pivot->blog_tag_id],
                'taggable_id' => $pivot->blog_post_id,
                'taggable_type' => 'App\\Models\\Blog\\BlogPost',
            ]);
        }

        Schema::dropIfExists('blog_tag_post');
        Schema::dropIfExists('blog_tags');
    }

    public function down(): void
    {
        Schema::create('blog_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 32)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blog_tag_post', function (Blueprint $table): void {
            $table->foreignId('blog_tag_id')->constrained('blog_tags')->cascadeOnDelete();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->primary(['blog_tag_id', 'blog_post_id']);
        });
    }
};
