<?php

namespace Tests\Feature\Cms;

use App\Actions\Cms\SetFlexibleSectionTypeModeAction;
use App\Cms\FlexibleDefinition;
use App\Enums\Cms\SectionTypeMode;
use App\Models\Catalog\Product;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\SectionDataTransformer;
use App\Services\Cms\SectionRegistry;
use App\Services\Cms\SectionTypeInspector;
use Awcodes\Curator\Models\Media;
use Database\Seeders\SectionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Golden-parity harness for the section-type migration: each seeded shadow
 * definition must produce a byte-identical API payload to the code
 * blueprint it mirrors before it may be promoted to active. Structural
 * parity (defaults, fieldKinds, field inventory) is asserted alongside the
 * envelope produced for a representative content fixture.
 */
class SectionTypeSeedParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(SectionTypeSeeder::class);
        app(SectionRegistry::class)->flush();
    }

    public function test_text_block_seed_matches_blueprint(): void
    {
        $this->assertSeedParity('text-block', [
            'eyebrow' => 'Our approach',
            'heading' => 'About us',
            'body' => '<p>Personalized care, delivered.</p>',
            'alignment' => 'center',
            'theme' => 'dark',
        ]);
    }

    public function test_cta_banner_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/cta-bg.jpg');

        $this->assertSeedParity('cta-banner', [
            'eyebrow' => 'Ready?',
            'heading' => 'Start your consultation',
            'sub' => 'Board-certified physicians, online.',
            'background_image' => $media->id,
            'theme' => 'dark',
            'primary_cta_label' => 'Get started',
            'primary_cta_url' => '/consultation',
            'secondary_cta_label' => null,
            'secondary_cta_url' => null,
        ]);
    }

    public function test_video_embed_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/poster.jpg');

        $this->assertSeedParity('video-embed', [
            'heading' => 'How it works',
            'caption' => 'Three minutes, start to finish.',
            'video_url' => 'https://www.youtube.com/watch?v=abc123',
            'poster_image' => $media->id,
            'theme' => 'light',
        ]);
    }

    public function test_features_grid_seed_matches_blueprint(): void
    {
        $this->assertSeedParity('features-grid', [
            'eyebrow' => 'Why us',
            'heading' => 'Built around you',
            'lead' => 'Everything in one place.',
            'columns' => '3',
            'features' => [
                ['icon' => 'heroicon-o-bolt', 'title' => 'Fast', 'body' => 'Same-day review.'],
                ['icon' => 'heroicon-o-lock-closed', 'title' => 'Private', 'body' => 'Encrypted end to end.'],
            ],
        ]);
    }

    public function test_highlight_banner_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/badge.png');

        $this->assertSeedParity('highlight-banner', [
            'items' => [
                ['icon' => $media->id, 'text' => 'Free shipping'],
                ['icon' => $media->id, 'text' => 'US pharmacies'],
            ],
            'icon_placement' => 'top',
            'per_row' => '4',
            'bordered' => true,
            'theme' => 'cream',
        ]);
    }

    public function test_product_slider_seed_matches_blueprint_in_manual_mode(): void
    {
        $published = Product::factory()->count(2)->create();
        $draft = Product::factory()->draft()->create();

        $envelope = $this->assertSeedParity('product-slider', [
            'eyebrow' => 'Shop',
            'heading' => 'Popular protocols',
            'subhead' => null,
            'variant' => 'progressbar',
            'cta_label' => 'View all',
            'cta_url' => '/products',
            'card_cta_label' => null,
            'mode' => 'manual',
            'product_ids' => [...$published->pluck('id')->all(), $draft->id],
            'category_id' => null,
            'limit' => 8,
            'autoplay' => false,
        ]);

        // The resolver inlines published products only; the raw ids stay.
        $this->assertCount(2, $envelope['data']['products']);
        $this->assertCount(3, $envelope['data']['product_ids']);
    }

    public function test_product_slider_seed_matches_blueprint_in_featured_mode(): void
    {
        Product::factory()->featured()->count(2)->create();
        Product::factory()->create();

        $envelope = $this->assertSeedParity('product-slider', [
            'eyebrow' => null,
            'heading' => 'Featured',
            'subhead' => null,
            'variant' => 'arrows',
            'cta_label' => null,
            'cta_url' => null,
            'card_cta_label' => null,
            'mode' => 'featured',
            'product_ids' => [],
            'category_id' => null,
            'limit' => 8,
            'autoplay' => false,
        ]);

        $this->assertCount(2, $envelope['data']['products']);
    }

    /**
     * Assert structural + payload parity for one seeded slug and return the
     * promoted (flexible) envelope for extra per-type assertions.
     *
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function assertSeedParity(string $slug, array $fixture): array
    {
        $registry = app(SectionRegistry::class);
        $transformer = app(SectionDataTransformer::class);
        $inspector = app(SectionTypeInspector::class);

        $row = FlexibleSectionType::query()->where('slug', $slug)->firstOrFail();
        $this->assertTrue($row->isShadow(), "Seed for {$slug} should start in shadow mode.");

        $codeDefinition = $registry->resolve($slug);
        $this->assertFalse($codeDefinition->isFlexible(), "Shadow seed for {$slug} must not shadow the code definition.");

        $seededDefinition = new FlexibleDefinition($row);

        $codeDefaults = $codeDefinition->defaults();
        $seedDefaults = $seededDefinition->defaults();
        ksort($codeDefaults);
        ksort($seedDefaults);
        $this->assertSame($codeDefaults, $seedDefaults, "Defaults diverge for {$slug}.");

        $codeKinds = $codeDefinition->fieldKinds();
        $seedKinds = $seededDefinition->fieldKinds();
        ksort($codeKinds);
        ksort($seedKinds);
        $this->assertSame($codeKinds, $seedKinds, "fieldKinds diverge for {$slug}.");

        $codeFieldNames = array_column($inspector->fields($codeDefinition), 'name');
        $seedFieldNames = array_column($inspector->fields($seededDefinition), 'name');
        sort($codeFieldNames);
        sort($seedFieldNames);
        $this->assertSame($codeFieldNames, $seedFieldNames, "Field inventory diverges for {$slug}.");

        $codeEnvelope = $transformer->envelopeFor($slug, $fixture);
        $this->assertSame('code', $codeEnvelope['origin']);

        app(SetFlexibleSectionTypeModeAction::class)->execute($row, SectionTypeMode::Active);

        $seededEnvelope = $transformer->envelopeFor($slug, $fixture);
        $this->assertSame('flexible', $seededEnvelope['origin']);
        $this->assertSame($codeEnvelope['data'], $seededEnvelope['data'], "API payload diverges for {$slug}.");

        app(SetFlexibleSectionTypeModeAction::class)->execute($row->fresh(), SectionTypeMode::Shadow);

        return $seededEnvelope;
    }

    private function media(string $path): Media
    {
        return Media::query()->create([
            'disk' => 'public',
            'directory' => 'sections',
            'visibility' => 'public',
            'name' => str($path)->afterLast('/')->beforeLast('.')->toString(),
            'path' => $path,
            'width' => 800,
            'height' => 600,
            'size' => 1024,
            'type' => 'image/jpeg',
            'ext' => str($path)->afterLast('.')->toString(),
            'alt' => 'Fixture image',
        ]);
    }
}
