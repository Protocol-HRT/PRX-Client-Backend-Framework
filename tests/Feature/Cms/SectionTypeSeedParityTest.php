<?php

namespace Tests\Feature\Cms;

use App\Actions\Cms\SetFlexibleSectionTypeModeAction;
use App\Cms\FlexibleDefinition;
use App\Enums\Cms\SectionTypeMode;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\FlexibleSchemaValidator;
use App\Services\Cms\SectionDataTransformer;
use App\Services\Cms\SectionRegistry;
use App\Services\Cms\SectionResolverOps;
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

    public function test_final_cta_seed_matches_blueprint(): void
    {
        $this->assertSeedParity('final-cta', [
            'eyebrow' => 'One last thing',
            'heading' => 'Start today',
            'emphasis' => 'on your terms',
            'lead' => 'Physician review within 24 hours.',
            'primary_cta_label' => 'Get started',
            'primary_cta_url' => '/consultation',
            'secondary_cta_label' => 'Browse protocols',
            'secondary_cta_url' => '/products',
        ]);
    }

    public function test_stats_marquee_seed_matches_blueprint(): void
    {
        $this->assertSeedParity('stats-marquee', [
            'items' => [
                ['value' => '12,400+', 'label' => 'Patients treated'],
                ['value' => '97%', 'label' => 'Satisfaction'],
            ],
        ]);
    }

    public function test_results_stats_seed_matches_blueprint(): void
    {
        $this->assertSeedParity('results-stats', [
            'eyebrow' => 'Results',
            'heading' => 'By the numbers',
            'emphasis' => 'that matter',
            'stats' => [
                ['value' => '12,400+', 'label' => 'Patients', 'sublabel' => 'and counting', 'icon' => 'patients'],
                ['value' => '50', 'label' => 'States', 'sublabel' => null, 'icon' => 'states'],
            ],
            'footer_note' => 'Data audited quarterly.',
        ]);
    }

    public function test_story_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/founder.jpg');

        $this->assertSeedParity('story', [
            'eyebrow' => 'Our story',
            'heading' => 'Founded by physicians',
            'emphasis' => 'for patients',
            'lead' => 'Two doctors, one mission.',
            'physicians' => [
                ['name' => 'Dr. Jane Doe', 'title' => 'Co-founder', 'badge' => 'Author: The Protocol', 'image' => $media->id, 'image_alt' => 'Dr. Doe', 'body' => 'Twenty years of practice.'],
            ],
            'pull_quote' => 'Medicine should meet you where you are.',
            'pull_quote_attribution' => 'Dr. Jane Doe',
        ]);
    }

    public function test_how_it_works_seed_matches_blueprint(): void
    {
        $this->assertSeedParity('how-it-works', [
            'eyebrow' => 'Process',
            'heading' => 'How it works',
            'emphasis' => 'in three steps',
            'lead' => 'From intake to doorstep.',
            'cta_label' => 'Begin intake',
            'cta_url' => '/consultation',
            'steps' => [
                ['number' => '01', 'title' => 'Intake', 'meta' => 'Takes about 5 minutes', 'body' => 'Tell us about your goals.'],
                ['number' => '02', 'title' => 'Review', 'meta' => null, 'body' => 'A physician reviews your case.'],
            ],
        ]);
    }

    public function test_testimonials_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/headshot.jpg');

        $this->assertSeedParity('testimonials', [
            'eyebrow' => 'Reviews',
            'heading' => 'What patients say',
            'emphasis' => 'in their words',
            'rating_stats' => [
                ['value' => '4.9/5', 'label' => 'Average rating'],
            ],
            'quotes' => [
                ['protocol' => 'HORMONE OPTIMIZATION', 'stars' => 5, 'name' => 'Alex R.', 'title' => 'Member since 2025', 'image' => $media->id, 'initials' => 'AR', 'quote' => 'Life-changing care.'],
                ['protocol' => null, 'stars' => 4, 'name' => 'Sam K.', 'title' => null, 'image' => null, 'initials' => 'SK', 'quote' => 'Fast and thorough.'],
            ],
        ]);
    }

    public function test_timeline_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/emblem.png');

        $this->assertSeedParity('timeline', [
            'heading' => 'Your first 90 days',
            'mark_image' => $media->id,
            'lead' => 'What to expect, week by week.',
            'steps' => [
                [
                    'title' => 'Baseline labs',
                    'meta' => 'Week 1',
                    'body' => 'Comprehensive panel drawn locally.',
                    'bullets' => [['text' => '50+ biomarkers'], ['text' => 'At-home option']],
                ],
                [
                    'title' => 'Protocol start',
                    'meta' => 'Week 2',
                    'body' => 'Medication ships to your door.',
                    'bullets' => [],
                ],
            ],
        ]);
    }

    public function test_image_text_split_seed_matches_blueprint(): void
    {
        $image = $this->media('sections/science.jpg');
        $icon = $this->media('sections/mark.png');

        $this->assertSeedParity('image-text-split', [
            'eyebrow' => 'The science',
            'heading' => 'Evidence first',
            'lead' => 'Every protocol is peer-reviewed.',
            'body' => '<p>Long-form prose about the clinical approach.</p>',
            'image' => $image->id,
            'image_alt' => 'Lab work',
            'cta_label' => 'Read the research',
            'cta_url' => '/science',
            'image_right' => true,
            'theme' => 'light',
            'float_cards' => [
                ['position' => 'bottom-left', 'icon' => $icon->id, 'value' => '15+', 'text' => 'Years of practice', 'rating_value' => null, 'rating_text' => null],
                ['position' => 'top-right', 'icon' => null, 'value' => null, 'text' => 'Rated excellent', 'rating_value' => 4.9, 'rating_text' => '4.9/5 · 200+ reviews'],
            ],
        ]);
    }

    public function test_physicians_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/portrait.jpg');

        $this->assertSeedParity('physicians', [
            'theme' => 'dark',
            'eyebrow' => 'Your care team',
            'heading' => 'Meet the physicians',
            'heading_emphasis' => 'behind the protocols',
            'lead' => 'Licensed in all 50 states.',
            'physicians' => [
                [
                    'name' => 'Dr. Jane Smith, MD',
                    'title' => 'Medical Director',
                    'specialty' => 'Board-Certified · 20+ Years Clinical Experience',
                    'image' => $media->id,
                    'image_alt' => 'Dr. Smith',
                    'bio' => 'Dedicated to preventive medicine.',
                    'badges' => ['Board-Certified', 'ABIM Fellow'],
                ],
            ],
            'trust_badges' => [
                ['icon' => '✓', 'label' => 'HIPAA compliant'],
            ],
        ]);
    }

    public function test_benefits_him_seed_matches_blueprint(): void
    {
        $this->assertBenefitsPitchParity('benefits-him');
    }

    public function test_benefits_her_seed_matches_blueprint(): void
    {
        $this->assertBenefitsPitchParity('benefits-her');
    }

    public function test_transformed_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/ambassador.jpg');

        $this->assertSeedParity('transformed', [
            'eyebrow' => 'Transformed',
            'heading' => 'Featured members',
            'emphasis' => 'real results',
            'lead' => 'Ambassadors who live the protocols.',
            'quotes' => [
                ['name' => 'Jordan P.', 'title' => 'Ambassador', 'protocol' => 'Performance & Hormone Optimization', 'image' => $media->id, 'image_alt' => 'Jordan', 'quote' => 'I feel ten years younger.'],
            ],
        ]);
    }

    public function test_pricing_tiers_seed_matches_blueprint(): void
    {
        $this->assertSeedParity('pricing-tiers', [
            'eyebrow' => 'Membership',
            'main_tiers' => [
                [
                    'pill' => 'Most popular',
                    'pill_emoji' => '⭐',
                    'accent' => 'gold',
                    'title' => 'TRT Complete',
                    'subtitle' => 'Everything included',
                    'price' => '$199',
                    'price_suffix' => '/month',
                    'price_note_micro' => 'Billed monthly',
                    'lto_banner' => 'Founding-member pricing ends soon.',
                    'callout_heading' => 'Why members choose this',
                    'callout_body' => 'Labs, visits, and medication in one plan.',
                    'features' => ['Quarterly labs', 'Unlimited messaging'],
                    'cta_label' => 'Join now',
                    'cta_url' => '/checkout',
                    'cta_micro' => 'Cancel anytime',
                    'secondary_label' => 'Compare plans',
                    'secondary_url' => '/pricing',
                    'route' => 'Intake → labs → protocol.',
                    'guarantees' => ['30-day guarantee'],
                ],
            ],
            'peptide_card' => [
                'enabled' => '1',
                'eyebrow_main' => 'Peptide therapy',
                'eyebrow_secondary' => 'Waitlist open',
                'title' => 'Peptide protocols',
                'subtitle' => 'Targeted recovery and longevity support.',
                'mini_tiers' => [
                    ['label' => '1 peptide', 'price' => '$89', 'note' => 'per month'],
                    ['label' => '2 peptides', 'price' => '$159', 'note' => 'per month'],
                ],
                'features' => ['Physician-monitored'],
                'waitlist_heading' => 'Join the waitlist',
                'waitlist_body' => 'We onboard new peptide patients monthly.',
                'waitlist_placeholder' => 'you@example.com',
                'waitlist_cta_label' => 'Notify me',
                'success_heading' => 'You are on the list',
                'success_body' => 'We will email you when a spot opens.',
                'fallback_cta_label' => 'Explore other protocols',
                'fallback_cta_url' => '/products',
                'fallback_note' => 'No spam, ever.',
            ],
        ]);
    }

    public function test_faq_seed_matches_blueprint(): void
    {
        $media = $this->media('sections/faq-intro.jpg');

        $this->assertSeedParity('faq', [
            'eyebrow' => 'FAQ',
            'heading' => 'Common questions',
            'emphasis' => 'answered',
            'description' => 'Everything about the process.',
            'cta_label' => 'Contact us',
            'cta_url' => '/contact',
            'image' => $media->id,
            'image_alt' => 'Support team',
            'faqs' => [
                ['q' => 'Is this legal?', 'a' => 'Yes — physician-prescribed in all 50 states.'],
                ['q' => 'How fast is shipping?', 'a' => 'Two to four business days.'],
            ],
        ]);
    }

    public function test_hero_seed_matches_blueprint(): void
    {
        $slide = $this->media('sections/hero-slide.jpg');
        $highlight = $this->media('sections/highlight.png');
        $background = $this->media('sections/hero-bg.jpg');

        $this->assertSeedParity('hero', [
            'layout' => 'slider',
            'slides' => [
                [
                    'image' => $slide->id,
                    'image_alt' => 'Sunrise run',
                    'heading' => 'Own your health',
                    'heading_emphasis' => 'for good',
                    'description' => '<p>Physician-led protocols, delivered.</p>',
                    'cta_label' => 'Start now',
                    'cta_url' => '/consultation',
                    'text_theme' => 'dark',
                ],
            ],
            'highlight_title' => 'Member favorite',
            'highlight_subtitle' => 'NAD+ protocol',
            'highlight_quote' => 'The energy difference is real.',
            'highlight_image' => $highlight->id,
            'eyebrow' => 'Telemedicine',
            'headline' => 'Modern care',
            'headline_emphasis' => 'without the wait',
            'subhead' => 'Board-certified physicians, online.',
            'primary_cta_label' => 'Get started',
            'primary_cta_url' => '/consultation',
            'secondary_cta_label' => 'Learn more',
            'secondary_cta_url' => '/about',
            'trust_microcopy' => 'HIPAA-compliant · Licensed in 50 states',
            'background_image' => $background->id,
            'background_video_url' => null,
        ]);
    }

    public function test_product_grid_seed_matches_blueprint_in_manual_mode(): void
    {
        $published = Product::factory()->count(2)->create();
        $draft = Product::factory()->draft()->create();

        $envelope = $this->assertSeedParity('product-grid', [
            'eyebrow' => 'Shop',
            'heading' => 'All protocols',
            'subhead' => null,
            'columns' => '3',
            'mode' => 'manual',
            'product_ids' => [...$published->pluck('id')->all(), $draft->id],
            'category_id' => null,
            'limit' => 12,
        ]);

        $this->assertCount(2, $envelope['data']['products']);
    }

    public function test_product_grid_seed_matches_blueprint_in_category_mode(): void
    {
        $category = Category::factory()->create();
        $inCategory = Product::factory()->count(2)->create();
        $inCategory->each(fn (Product $product) => $product->categories()->attach($category->id));
        Product::factory()->create();

        $envelope = $this->assertSeedParity('product-grid', [
            'eyebrow' => null,
            'heading' => 'Category picks',
            'subhead' => null,
            'columns' => '4',
            'mode' => 'category',
            'product_ids' => [],
            'category_id' => $category->id,
            'limit' => 12,
        ]);

        $this->assertCount(2, $envelope['data']['products']);
    }

    public function test_package_slider_seed_matches_blueprint(): void
    {
        $published = Package::factory()->count(2)->create();
        $draft = Package::factory()->draft()->create();

        $envelope = $this->assertSeedParity('package-slider', [
            'eyebrow' => 'Bundles',
            'heading' => 'Protocol stacks',
            'subhead' => 'Save with a bundle.',
            'mode' => 'manual',
            'package_ids' => [...$published->pluck('id')->all(), $draft->id],
            'category_id' => null,
            'limit' => 8,
            'autoplay' => false,
        ]);

        $this->assertCount(2, $envelope['data']['packages']);
    }

    public function test_package_slider_seed_matches_blueprint_in_featured_mode(): void
    {
        Package::factory()->featured()->count(2)->create();
        Package::factory()->create();

        $envelope = $this->assertSeedParity('package-slider', [
            'eyebrow' => null,
            'heading' => 'Featured stacks',
            'subhead' => null,
            'mode' => 'featured',
            'package_ids' => [],
            'category_id' => null,
            'limit' => 8,
            'autoplay' => true,
        ]);

        $this->assertCount(2, $envelope['data']['packages']);
    }

    public function test_package_pricing_comparison_seed_matches_blueprint(): void
    {
        $packages = Package::factory()->count(3)->create();

        $envelope = $this->assertSeedParity('package-pricing-comparison', [
            'eyebrow' => 'Compare',
            'heading' => 'Which stack fits?',
            'subhead' => null,
            'package_ids' => $packages->pluck('id')->all(),
            'highlight_package_id' => $packages[1]->id,
        ]);

        $this->assertCount(3, $envelope['data']['packages']);
        $this->assertSame($packages[1]->id, $envelope['data']['highlight_package_id']);
    }

    public function test_product_callout_seed_matches_blueprint_for_product(): void
    {
        $product = Product::factory()->create();
        $media = $this->media('sections/callout.jpg');

        $envelope = $this->assertSeedParity('product-callout', [
            'item_type' => 'product',
            'product_id' => $product->id,
            'package_id' => null,
            'eyebrow' => 'Featured',
            'headline' => 'The flagship protocol',
            'body' => 'Custom copy overriding the catalog text.',
            'cta_label' => 'Learn more',
            'cta_url' => '/products/flagship',
            'image' => $media->id,
            'image_alt' => 'Product shot',
            'image_right' => true,
        ]);

        $this->assertSame($product->name, $envelope['data']['product']['name']);
        $this->assertNull($envelope['data']['package']);
    }

    public function test_product_callout_seed_matches_blueprint_for_package_with_stale_product_id(): void
    {
        $product = Product::factory()->create();
        $package = Package::factory()->create();

        // The non-selected branch must null out even when its id is set —
        // this is the conditional the resolver `when` gate replicates.
        $envelope = $this->assertSeedParity('product-callout', [
            'item_type' => 'package',
            'product_id' => $product->id,
            'package_id' => $package->id,
            'eyebrow' => null,
            'headline' => null,
            'body' => null,
            'cta_label' => null,
            'cta_url' => null,
            'image' => null,
            'image_alt' => null,
            'image_right' => false,
        ]);

        $this->assertNull($envelope['data']['product']);
        $this->assertSame($package->name, $envelope['data']['package']['name']);
    }

    public function test_category_grid_seed_matches_blueprint_in_all_mode(): void
    {
        Category::factory()->count(2)->create();
        Category::factory()->hidden()->create();

        $envelope = $this->assertSeedParity('category-grid', [
            'eyebrow' => 'Browse',
            'heading' => 'By category',
            'subhead' => null,
            'mode' => 'all',
            'category_ids' => [],
            'limit' => 12,
        ]);

        $this->assertCount(2, $envelope['data']['categories']);
    }

    public function test_category_grid_seed_matches_blueprint_in_manual_mode(): void
    {
        $categories = Category::factory()->count(3)->create();
        $picked = [$categories[2]->id, $categories[0]->id];

        $envelope = $this->assertSeedParity('category-grid', [
            'eyebrow' => null,
            'heading' => 'Hand-picked',
            'subhead' => null,
            'mode' => 'manual',
            'category_ids' => $picked,
            'limit' => 12,
        ]);

        $this->assertSame($picked, $envelope['data']['category_ids']);
        $this->assertSame($categories[2]->name, $envelope['data']['categories'][0]['name']);
    }

    public function test_benefits_diagram_seed_matches_blueprint(): void
    {
        $center = $this->media('sections/diagram.png');
        $pointIcon = $this->media('sections/point-icon.png');
        $product = Product::factory()->create();

        $envelope = $this->assertSeedParity('benefits-diagram', [
            'heading' => 'One protocol, many benefits',
            'image' => $center->id,
            'image_alt' => 'Product vial',
            'marker_style' => 'icon',
            'points' => [
                ['text' => 'Deeper sleep', 'side' => 'left', 'icon' => $pointIcon->id],
                ['text' => 'Sharper focus', 'side' => 'right', 'icon' => null],
            ],
            'rating_value' => 4.8,
            'rating_text' => '4.8/5 from 300+ members',
            'cta_label' => 'Add to cart',
            'cta_mode' => 'add_to_cart',
            'cta_url' => null,
            'cta_item_type' => 'product',
            'cta_product_id' => $product->id,
            'cta_package_id' => null,
            'cta_subtext' => 'Secure checkout',
        ]);

        $this->assertSame($product->name, $envelope['data']['cta_product']['name']);
        $this->assertNull($envelope['data']['cta_package']);
    }

    public function test_image_callout_banner_seed_matches_blueprint(): void
    {
        $background = $this->media('sections/banner-bg.jpg');
        $icon = $this->media('sections/callout-icon.png');
        $package = Package::factory()->create();

        $envelope = $this->assertSeedParity('image-callout-banner', [
            'background_image' => $background->id,
            'background_alt' => 'Coastline at dawn',
            'background_treatment' => 'tint',
            'tint_color' => '#8a7a5c',
            'callouts' => [
                [
                    'position' => '0',
                    'variant' => 'card',
                    'align' => 'center',
                    'color' => null,
                    'icon' => $icon->id,
                    'icon_width' => 48,
                    'title' => 'Concierge care',
                    'content' => '<p>Message your care team anytime.</p>',
                    'cta_label' => 'Learn more',
                    'cta_mode' => 'link',
                    'cta_url' => '/care',
                    'cta_item_type' => 'product',
                    'cta_product_id' => null,
                    'cta_package_id' => null,
                ],
                [
                    'position' => '1',
                    'variant' => 'feature',
                    'align' => 'bottom',
                    'color' => null,
                    'icon' => null,
                    'icon_width' => null,
                    'title' => 'The complete stack',
                    'content' => '<p>Everything in one bundle.</p>',
                    'cta_label' => 'Add the stack',
                    'cta_mode' => 'add_to_cart',
                    'cta_url' => null,
                    'cta_item_type' => 'package',
                    'cta_product_id' => null,
                    'cta_package_id' => $package->id,
                ],
            ],
        ]);

        $this->assertNull($envelope['data']['callouts'][0]['cta_product']);
        $this->assertSame($package->name, $envelope['data']['callouts'][1]['cta_package']['name']);
        $this->assertSame('1', $envelope['data']['callouts'][1]['position']);
    }

    public function test_every_seeded_schema_passes_validation(): void
    {
        foreach (FlexibleSectionType::query()->get() as $row) {
            FlexibleSchemaValidator::validate($row->fields());
            SectionResolverOps::validate(array_values($row->schema['resolvers'] ?? []));
        }

        $this->assertTrue(true);
    }

    /**
     * Benefits-Him / Benefits-Her share a field surface — one fixture,
     * asserted per slug.
     */
    private function assertBenefitsPitchParity(string $slug): void
    {
        $media = $this->media('sections/lifestyle.jpg');

        $this->assertSeedParity($slug, [
            'eyebrow' => 'Protocols',
            'heading' => 'Optimized for you',
            'emphasis' => 'at every age',
            'lead' => 'Four pillars of performance.',
            'cta_label' => 'Explore protocols',
            'cta_url' => '/products',
            'image' => $media->id,
            'image_alt' => 'Lifestyle',
            'benefits' => [
                ['category' => 'HORMONES', 'pill' => 'Popular', 'title' => 'Hormone optimization', 'body' => 'Restore healthy levels.'],
                ['category' => 'RECOVERY', 'pill' => null, 'title' => 'Recovery support', 'body' => 'Sleep and repair.'],
            ],
        ]);
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
