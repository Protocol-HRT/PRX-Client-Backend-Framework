<?php

namespace Database\Seeders;

use App\Enums\Cms\SectionTypeMode;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\SectionRegistry;
use Illuminate\Database\Seeder;

/**
 * Seeds code section blueprints as DB-defined shadow definitions — the
 * migration path to data-driven section types. Each row mirrors its
 * blueprint's fields, defaults, and runtime behavior (via resolver ops) in
 * the flexible schema vocabulary. Rows stay in SHADOW mode (code keeps
 * serving) until the golden-parity test for the slug is green and the row
 * is promoted to active.
 *
 * Idempotent and non-destructive: firstOrCreate by slug, so admin edits to
 * a promoted row survive re-seeding.
 */
class SectionTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            FlexibleSectionType::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'schema' => $definition['schema'],
                    'enabled' => true,
                    'mode' => SectionTypeMode::Shadow,
                ],
            );
        }

        app(SectionRegistry::class)->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        $themeOptions = [
            ['value' => 'light', 'label' => 'Light'],
            ['value' => 'dark', 'label' => 'Dark'],
            ['value' => 'cream', 'label' => 'Cream'],
        ];

        return [
            [
                'slug' => 'text-block',
                'name' => 'Text block',
                'icon' => 'heroicon-o-document-text',
                'description' => 'Generic prose section: optional eyebrow + heading + rich text body. Useful for "About", "Our Approach", legal copy, etc.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'body', 'kind' => 'richtext'],
                        ['key' => 'alignment', 'kind' => 'select', 'default' => 'left', 'options' => [
                            ['value' => 'left', 'label' => 'Left'],
                            ['value' => 'center', 'label' => 'Center'],
                        ]],
                        ['key' => 'theme', 'kind' => 'select', 'default' => 'light', 'options' => $themeOptions],
                    ],
                ],
            ],
            [
                'slug' => 'cta-banner',
                'name' => 'CTA banner',
                'icon' => 'heroicon-o-megaphone',
                'description' => 'Single full-width call-to-action strip with eyebrow, headline, sub, and one or two buttons.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'sub', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'primary_cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'primary_cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'secondary_cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'secondary_cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'background_image', 'kind' => 'image', 'label' => 'Background image'],
                        ['key' => 'theme', 'kind' => 'select', 'default' => 'dark', 'options' => $themeOptions],
                    ],
                ],
            ],
            [
                'slug' => 'video-embed',
                'name' => 'Video embed',
                'icon' => 'heroicon-o-play-circle',
                'description' => 'YouTube or Vimeo embed with optional heading + caption + poster image.',
                'schema' => [
                    'fields' => [
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'caption', 'kind' => 'text', 'max' => 500],
                        ['key' => 'video_url', 'kind' => 'text', 'max' => 2048, 'required' => true, 'help' => 'YouTube or Vimeo URL.'],
                        ['key' => 'poster_image', 'kind' => 'image', 'label' => 'Poster image'],
                        ['key' => 'theme', 'kind' => 'select', 'default' => 'light', 'options' => $themeOptions],
                    ],
                ],
            ],
            [
                'slug' => 'features-grid',
                'name' => 'Features grid',
                'icon' => 'heroicon-o-squares-2x2',
                'description' => 'Multi-column grid of icon + title + description cards. Use for service overviews, value props, etc.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'columns', 'kind' => 'select', 'default' => '3', 'options' => [
                            ['value' => '2', 'label' => '2 columns'],
                            ['value' => '3', 'label' => '3 columns'],
                            ['value' => '4', 'label' => '4 columns'],
                        ]],
                        ['key' => 'features', 'kind' => 'repeater', 'fields' => [
                            ['key' => 'icon', 'kind' => 'text', 'max' => 64, 'help' => 'Heroicon name (e.g. heroicon-o-bolt) or emoji.'],
                            ['key' => 'title', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'body', 'kind' => 'textarea', 'required' => true],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'highlight-banner',
                'name' => 'Highlight banner',
                'icon' => 'heroicon-o-sparkles',
                'description' => 'Slim band of short icon + text highlights (trust markers, key benefits). Column count controls item width per row.',
                'schema' => [
                    'fields' => [
                        ['key' => 'items', 'kind' => 'repeater', 'label' => 'Highlights', 'fields' => [
                            ['key' => 'icon', 'kind' => 'image', 'label' => 'Icon'],
                            ['key' => 'text', 'kind' => 'textarea', 'max' => 255, 'required' => true, 'help' => 'Line breaks are kept — two short lines render as in the design.'],
                        ]],
                        ['key' => 'icon_placement', 'kind' => 'select', 'default' => 'left', 'options' => [
                            ['value' => 'left', 'label' => 'Icon left of text'],
                            ['value' => 'top', 'label' => 'Icon above text'],
                        ]],
                        ['key' => 'per_row', 'kind' => 'select', 'label' => 'Items per row', 'default' => '4', 'help' => 'Desktop column count — item width follows. Collapses on small screens.', 'options' => [
                            ['value' => '2', 'label' => '2'],
                            ['value' => '3', 'label' => '3'],
                            ['value' => '4', 'label' => '4'],
                            ['value' => '5', 'label' => '5'],
                            ['value' => '6', 'label' => '6'],
                        ]],
                        ['key' => 'bordered', 'kind' => 'boolean', 'label' => 'Item borders', 'default' => false, 'help' => 'Outline each highlight as a card.'],
                        ['key' => 'theme', 'kind' => 'select', 'default' => 'cream', 'options' => $themeOptions],
                    ],
                ],
            ],
            [
                'slug' => 'product-slider',
                'name' => 'Product slider',
                'icon' => 'heroicon-o-rectangle-group',
                'description' => 'Horizontal product carousel. Pick products by hand or let a rule (featured, newest, category) choose them.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'subhead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'variant', 'kind' => 'select', 'default' => 'progressbar', 'required' => true, 'help' => 'Layout hint — the frontend maps each variant to a theme layout.', 'options' => [
                            ['value' => 'progressbar', 'label' => 'Progress-bar slider (no header row)'],
                            ['value' => 'arrows', 'label' => 'Titled slider with arrows + dots'],
                        ]],
                        ['key' => 'cta_label', 'kind' => 'text', 'label' => 'Section CTA label', 'max' => 120, 'help' => 'Optional "view all"-style link rendered with the header.'],
                        ['key' => 'cta_url', 'kind' => 'text', 'label' => 'Section CTA URL', 'max' => 500],
                        ['key' => 'card_cta_label', 'kind' => 'text', 'label' => 'Card CTA label', 'max' => 120, 'help' => 'Optional link text rendered on every product card (e.g. "Request Personalized Care").'],
                        ['key' => 'mode', 'kind' => 'select', 'default' => 'manual', 'required' => true, 'options' => [
                            ['value' => 'manual', 'label' => 'Pick products by hand'],
                            ['value' => 'featured', 'label' => 'Featured products'],
                            ['value' => 'newest', 'label' => 'Newest products'],
                            ['value' => 'category', 'label' => 'All products in a category'],
                        ]],
                        ['key' => 'product_ids', 'kind' => 'products', 'label' => 'Products', 'raw' => true, 'default' => [], 'help' => 'Shown in the order selected. Unpublished products are dropped automatically.', 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'equals', 'value' => 'manual'],
                        ]],
                        ['key' => 'category_id', 'kind' => 'category', 'label' => 'Category', 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'equals', 'value' => 'category'],
                        ]],
                        ['key' => 'limit', 'kind' => 'number', 'default' => 8, 'min' => 1, 'max' => 24, 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'not_equals', 'value' => 'manual'],
                        ]],
                        ['key' => 'autoplay', 'kind' => 'boolean', 'default' => false, 'help' => 'Layout hint for the frontend carousel.'],
                    ],
                    'resolvers' => [
                        ['op' => 'products_by_mode', 'output' => 'products'],
                    ],
                ],
            ],
        ];
    }
}
