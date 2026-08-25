<?php

namespace Database\Seeders;

use App\Cms\Support\LayoutDefaults;
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
            // Layout defaults come from the same table the code blueprint
            // reads, so a shadow row and its blueprint cannot drift apart
            // (SectionTypeSeedParityTest compares the envelopes they produce).
            $schema = $definition['schema'];
            $schema['layout_defaults'] = LayoutDefaults::for($definition['slug']);

            $row = FlexibleSectionType::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'schema' => $schema,
                    'enabled' => true,
                    'mode' => SectionTypeMode::Shadow,
                ],
            );

            // A row still in shadow mode is seeder-owned, so it may be brought
            // forward when the table gains a type. A promoted row is the
            // operator's and is left exactly as they saved it.
            if ($row->wasRecentlyCreated || $row->mode !== SectionTypeMode::Shadow) {
                continue;
            }

            $existing = $row->schema;

            if (($existing['layout_defaults'] ?? null) !== $schema['layout_defaults']) {
                $existing['layout_defaults'] = $schema['layout_defaults'];
                $row->update(['schema' => $existing]);
            }
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
            [
                'slug' => 'final-cta',
                'name' => 'Final call-to-action',
                'icon' => 'heroicon-o-arrow-right-circle',
                'description' => null,
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'emphasis', 'kind' => 'text', 'max' => 255, 'help' => 'Italic run on the second line.'],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'primary_cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'primary_cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'secondary_cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'secondary_cta_url', 'kind' => 'text', 'max' => 2048],
                    ],
                ],
            ],
            [
                'slug' => 'stats-marquee',
                'name' => 'Stats marquee',
                'icon' => 'heroicon-o-arrow-trending-up',
                'description' => 'Auto-scrolling strip of value/label pairs.',
                'schema' => [
                    'fields' => [
                        ['key' => 'items', 'kind' => 'repeater', 'label' => 'Stats', 'min' => 1, 'fields' => [
                            ['key' => 'value', 'kind' => 'text', 'max' => 60, 'required' => true],
                            ['key' => 'label', 'kind' => 'text', 'max' => 120, 'required' => true],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'results-stats',
                'name' => 'Results — by the numbers',
                'icon' => 'heroicon-o-presentation-chart-line',
                'description' => 'Dark-theme "By the Numbers" section: centered heading + 4-up grid of highlighted stat blocks (each with icon, gold value, white label, and sublabel).',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Sage eyebrow', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'emphasis', 'kind' => 'text', 'label' => 'Heading accent (italic gold)', 'max' => 255, 'help' => 'Italic gold run rendered after the heading.'],
                        ['key' => 'stats', 'kind' => 'repeater', 'label' => 'Stat blocks (4 typical)', 'fields' => [
                            ['key' => 'value', 'kind' => 'text', 'max' => 60, 'required' => true, 'help' => 'Pre-formatted value (e.g. "12,400+", "97%", "50").'],
                            ['key' => 'label', 'kind' => 'text', 'max' => 120, 'required' => true],
                            ['key' => 'sublabel', 'kind' => 'text', 'max' => 255],
                            ['key' => 'icon', 'kind' => 'select', 'default' => 'patients', 'help' => 'Built-in icon set rendered as inline SVG.', 'options' => [
                                ['value' => 'patients', 'label' => 'Patients (people group)'],
                                ['value' => 'protocols', 'label' => 'Protocols (document)'],
                                ['value' => 'states', 'label' => 'States (building)'],
                                ['value' => 'satisfaction', 'label' => 'Satisfaction (star)'],
                                ['value' => 'pulse', 'label' => 'Pulse (heartbeat)'],
                                ['value' => 'shield', 'label' => 'Shield (protection)'],
                            ]],
                        ]],
                        ['key' => 'footer_note', 'kind' => 'textarea', 'max' => 500, 'help' => 'Tiny mono note rendered below the stat grid.'],
                    ],
                ],
            ],
            [
                'slug' => 'story',
                'name' => 'Founders / story',
                'icon' => 'heroicon-o-book-open',
                'description' => 'Light-theme founders story: left-aligned headline + lead, 2-card grid of physician bios (round portrait + name + sage title + gold badge + bio), closing manifesto block on mint background.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'emphasis', 'kind' => 'text', 'label' => 'Heading accent (italic sage)', 'max' => 255, 'help' => 'Optional sage italic run rendered after the heading.'],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'physicians', 'kind' => 'repeater', 'label' => 'Physicians', 'fields' => [
                            ['key' => 'name', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'title', 'kind' => 'text', 'label' => 'Title / role', 'max' => 255],
                            ['key' => 'badge', 'kind' => 'text', 'max' => 255, 'help' => 'Gold pill below the title (e.g. "Author: Some Book").'],
                            ['key' => 'image', 'kind' => 'image', 'label' => 'Portrait image'],
                            ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                            ['key' => 'body', 'kind' => 'textarea', 'label' => 'Bio', 'required' => true],
                        ]],
                        ['key' => 'pull_quote', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'pull_quote_attribution', 'kind' => 'text', 'label' => 'Attribution', 'max' => 255],
                    ],
                ],
            ],
            [
                'slug' => 'how-it-works',
                'name' => 'How it works (process)',
                'icon' => 'heroicon-o-list-bullet',
                'description' => 'Light-theme process explanation. 2-col header (heading left, lead + dark CTA right), then a 3-col step grid with circular step-numbers and connector lines.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow (sage)', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'emphasis', 'kind' => 'text', 'label' => 'Heading accent (sage)', 'max' => 255, 'help' => 'Sage run rendered after the heading.'],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'steps', 'kind' => 'repeater', 'label' => 'Steps', 'fields' => [
                            ['key' => 'number', 'kind' => 'text', 'max' => 8, 'required' => true, 'help' => 'e.g. 01, 02, 03 — rendered in the circular badge.'],
                            ['key' => 'title', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'meta', 'kind' => 'text', 'label' => 'Sub label (sage mono)', 'max' => 255, 'help' => 'Tiny line above the title (e.g. "Takes about 5 minutes").'],
                            ['key' => 'body', 'kind' => 'textarea', 'required' => true],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'testimonials',
                'name' => 'Testimonials',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'description' => 'Dark-theme testimonials grid. 2-col header (heading left, optional rating-stats row right), then a 2-col grid of dark cards: 5 gold stars + protocol tag + italic quote + avatar/name/verified-checkmark footer.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Editorial tag', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'emphasis', 'kind' => 'text', 'label' => 'Heading accent (italic gold)', 'max' => 255],
                        ['key' => 'rating_stats', 'kind' => 'repeater', 'label' => 'Rating stats (right of header — leave empty to hide)', 'max' => 4, 'fields' => [
                            ['key' => 'value', 'kind' => 'text', 'max' => 60, 'required' => true],
                            ['key' => 'label', 'kind' => 'text', 'max' => 120, 'required' => true],
                        ]],
                        ['key' => 'quotes', 'kind' => 'repeater', 'label' => 'Testimonials', 'fields' => [
                            ['key' => 'protocol', 'kind' => 'text', 'label' => 'Protocol tag (mono uppercase)', 'max' => 120, 'help' => 'e.g. "HORMONE OPTIMIZATION", "PHYSICIAN ENDORSEMENT".'],
                            ['key' => 'stars', 'kind' => 'number', 'min' => 0, 'max' => 5, 'default' => 5, 'help' => '0–5 gold stars rendered above the quote.'],
                            ['key' => 'name', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'title', 'kind' => 'text', 'label' => 'Title / role', 'max' => 255],
                            ['key' => 'image', 'kind' => 'image', 'label' => 'Headshot'],
                            ['key' => 'initials', 'kind' => 'text', 'max' => 4, 'help' => 'Fallback when no image is set.'],
                            ['key' => 'quote', 'kind' => 'textarea', 'required' => true],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'timeline',
                'name' => 'Timeline (vertical steps)',
                'icon' => 'heroicon-o-arrow-long-down',
                'description' => 'Centered heading + lead, then a vertical center rail with dot markers and steps alternating right/left of the rail. Each step: title, small sub label, body, optional bullet list. Optional emblem image at the top of the rail.',
                'schema' => [
                    'fields' => [
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'mark_image', 'kind' => 'image', 'label' => 'Rail emblem', 'help' => 'Small image rendered at the top of the vertical rail (e.g. a logo mark). Optional.'],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'steps', 'kind' => 'repeater', 'label' => 'Steps', 'fields' => [
                            ['key' => 'title', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'meta', 'kind' => 'text', 'label' => 'Sub label', 'max' => 255, 'help' => 'Muted line under the title (e.g. a duration).'],
                            ['key' => 'body', 'kind' => 'textarea', 'max' => 2000],
                            ['key' => 'bullets', 'kind' => 'repeater', 'label' => 'Bullet list', 'fields' => [
                                ['key' => 'text', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'image-text-split',
                'name' => 'Image + text split',
                'icon' => 'heroicon-o-photo',
                'description' => '50/50 image and prose layout. Toggle the layout to flip image position. Optional floating frosted cards over the image (stat value or icon + text + star rating).',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500, 'help' => 'Short paragraph rendered under the heading, beside the body column.'],
                        ['key' => 'body', 'kind' => 'richtext'],
                        ['key' => 'image', 'kind' => 'image', 'label' => 'Image'],
                        ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                        ['key' => 'cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'image_right', 'kind' => 'boolean', 'label' => 'Image on the right', 'default' => false, 'help' => 'Default is image on the left.'],
                        ['key' => 'theme', 'kind' => 'select', 'default' => 'light', 'options' => $themeOptions],
                        ['key' => 'float_cards', 'kind' => 'repeater', 'label' => 'Floating cards (over the image)', 'max' => 2, 'fields' => [
                            ['key' => 'position', 'kind' => 'select', 'default' => 'bottom-left', 'required' => true, 'options' => [
                                ['value' => 'bottom-left', 'label' => 'Bottom left'],
                                ['value' => 'top-right', 'label' => 'Top right'],
                            ]],
                            ['key' => 'icon', 'kind' => 'image', 'label' => 'Icon / logo'],
                            ['key' => 'value', 'kind' => 'text', 'label' => 'Stat value', 'max' => 20, 'help' => 'Large figure rendered above the text (e.g. "15+"). Leave empty for icon/text-only cards.'],
                            ['key' => 'text', 'kind' => 'text', 'max' => 160],
                            ['key' => 'rating_value', 'kind' => 'number', 'label' => 'Star rating (0–5)', 'min' => 0, 'max' => 5, 'help' => 'Renders a stars row when set.'],
                            ['key' => 'rating_text', 'kind' => 'text', 'max' => 120, 'help' => 'Line next to the stars (e.g. "4.9/5 · 200+ reviews").'],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'physicians',
                'name' => 'Physicians spotlight',
                'icon' => 'heroicon-o-user-group',
                'description' => 'Physician spotlight card(s): round portrait, role eyebrow, name heading, specialty line, bio paragraph, and a row of credential badge pills. Optional section header and trust-badge strip.',
                'schema' => [
                    'fields' => [
                        ['key' => 'theme', 'kind' => 'select', 'default' => 'light', 'help' => 'Dark renders the spotlight card on a near-black panel with light text and translucent badge pills.', 'options' => [
                            ['value' => 'light', 'label' => 'Light'],
                            ['value' => 'dark', 'label' => 'Dark'],
                        ]],
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'heading_emphasis', 'kind' => 'text', 'label' => 'Heading accent (italic)', 'max' => 255, 'help' => 'Optional accent run rendered after the heading.'],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'physicians', 'kind' => 'repeater', 'label' => 'Physicians', 'fields' => [
                            ['key' => 'name', 'kind' => 'text', 'max' => 255, 'required' => true, 'help' => 'Rendered as given — include credentials if wanted (e.g. "Dr. Jane Smith, MD").'],
                            ['key' => 'title', 'kind' => 'text', 'label' => 'Role eyebrow', 'max' => 255, 'help' => 'Small line above the name (e.g. "Medical Director").'],
                            ['key' => 'specialty', 'kind' => 'text', 'label' => 'Specialty line', 'max' => 255, 'help' => 'Line under the name (e.g. "Board-Certified · 20+ Years Clinical Experience").'],
                            ['key' => 'image', 'kind' => 'image', 'label' => 'Portrait image'],
                            ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                            ['key' => 'bio', 'kind' => 'textarea', 'label' => 'Bio paragraph', 'max' => 1000],
                            ['key' => 'badges', 'kind' => 'repeater', 'label' => 'Credential badge pills', 'simple' => true, 'fields' => [
                                ['key' => 'badge', 'kind' => 'text', 'max' => 120, 'required' => true],
                            ]],
                        ]],
                        ['key' => 'trust_badges', 'kind' => 'repeater', 'label' => 'Trust badges (footer strip, optional)', 'fields' => [
                            ['key' => 'icon', 'kind' => 'text', 'max' => 8, 'help' => 'Emoji or single character.'],
                            ['key' => 'label', 'kind' => 'text', 'max' => 120, 'required' => true],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'benefits-him',
                'name' => 'Benefits — for him',
                'icon' => 'heroicon-o-bolt',
                'description' => 'Dark-theme 3-col grid: pitch column (eyebrow + headline + lead + lifestyle photo + CTA) on the LEFT, 2×2 protocol-card grid on the right. Mirror to Benefits-Her.',
                'schema' => $this->benefitsPitchSchema(),
            ],
            [
                'slug' => 'benefits-her',
                'name' => 'Benefits — for her',
                'icon' => 'heroicon-o-sparkles',
                'description' => 'Dark-theme 3-col grid: 2×2 protocol-card grid on the LEFT, pitch column (eyebrow + headline + lead + lifestyle photo + CTA) on the right. Mirror to Benefits-Him.',
                'schema' => $this->benefitsPitchSchema(),
            ],
            [
                'slug' => 'transformed',
                'name' => 'Ambassadors / featured proof',
                'icon' => 'heroicon-o-trophy',
                'description' => 'Dark-theme ambassador-card grid. 2-col header (heading left, lead right). Each card has a 120×120 circular portrait at the top, 5 gold stars, italic quote, and a divider separating name+title (left) from a small protocol pill (right).',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Editorial tag', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'emphasis', 'kind' => 'text', 'label' => 'Heading accent (italic gold)', 'max' => 255, 'help' => 'Gold italic run rendered after the heading.'],
                        ['key' => 'lead', 'kind' => 'textarea', 'max' => 500, 'help' => 'Right column of the header — lead paragraph that introduces the ambassadors.'],
                        ['key' => 'quotes', 'kind' => 'repeater', 'label' => 'Ambassador cards', 'fields' => [
                            ['key' => 'name', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'title', 'kind' => 'text', 'label' => 'Title / role', 'max' => 255],
                            ['key' => 'protocol', 'kind' => 'text', 'label' => 'Protocol pill (mono uppercase)', 'max' => 120, 'help' => 'Small gold pill on the right of the footer (e.g. "Performance & Hormone Optimization").'],
                            ['key' => 'image', 'kind' => 'image', 'label' => 'Portrait image'],
                            ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                            ['key' => 'quote', 'kind' => 'textarea', 'required' => true],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'pricing-tiers',
                'name' => 'Pricing tiers',
                'icon' => 'heroicon-o-banknotes',
                'description' => 'Two-card primary pricing grid (Blueprint + TRT-style featured) with an optional full-width peptide / waitlist card below.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Section eyebrow tag', 'max' => 120],
                        ['key' => 'main_tiers', 'kind' => 'repeater', 'label' => 'Main pricing cards (max 2 — Blueprint + Featured)', 'max' => 2, 'fields' => [
                            ['key' => 'pill', 'kind' => 'text', 'label' => 'Top pill text', 'max' => 120],
                            ['key' => 'pill_emoji', 'kind' => 'text', 'label' => 'Pill emoji (optional)', 'max' => 8],
                            ['key' => 'accent', 'kind' => 'select', 'required' => true, 'options' => [
                                ['value' => 'sage', 'label' => 'Sage (Blueprint card)'],
                                ['value' => 'gold', 'label' => 'Gold (Featured / TRT)'],
                            ]],
                            ['key' => 'title', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'subtitle', 'kind' => 'text', 'max' => 255],
                            ['key' => 'price', 'kind' => 'text', 'max' => 60],
                            ['key' => 'price_suffix', 'kind' => 'text', 'label' => 'Price suffix', 'max' => 120],
                            ['key' => 'price_note_micro', 'kind' => 'text', 'label' => 'Tiny note next to price', 'max' => 255],
                            ['key' => 'lto_banner', 'kind' => 'textarea', 'label' => 'Limited-time-offer banner', 'max' => 500],
                            ['key' => 'callout_heading', 'kind' => 'text', 'label' => 'Callout heading', 'max' => 255],
                            ['key' => 'callout_body', 'kind' => 'textarea', 'label' => 'Callout body', 'max' => 500],
                            ['key' => 'features', 'kind' => 'repeater', 'simple' => true, 'fields' => [
                                ['key' => 'feature', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ]],
                            ['key' => 'cta_label', 'kind' => 'text', 'max' => 120],
                            ['key' => 'cta_url', 'kind' => 'text', 'max' => 2048],
                            ['key' => 'cta_micro', 'kind' => 'text', 'label' => 'Tiny note under CTA', 'max' => 255],
                            ['key' => 'secondary_label', 'kind' => 'text', 'label' => 'Secondary link label', 'max' => 120],
                            ['key' => 'secondary_url', 'kind' => 'text', 'label' => 'Secondary link URL', 'max' => 2048],
                            ['key' => 'route', 'kind' => 'textarea', 'label' => 'Route summary', 'max' => 500],
                            ['key' => 'guarantees', 'kind' => 'repeater', 'label' => 'Footer guarantees', 'simple' => true, 'fields' => [
                                ['key' => 'guarantee', 'kind' => 'text', 'max' => 255],
                            ]],
                        ]],
                        ['key' => 'peptide_card', 'kind' => 'group', 'label' => 'Peptide / waitlist card', 'default' => ['enabled' => false], 'fields' => [
                            ['key' => 'enabled', 'kind' => 'select', 'label' => 'Show this card', 'default' => '1', 'options' => [
                                ['value' => '1', 'label' => 'Show'],
                                ['value' => '0', 'label' => 'Hide'],
                            ]],
                            ['key' => 'eyebrow_main', 'kind' => 'text', 'label' => 'Primary eyebrow', 'max' => 255],
                            ['key' => 'eyebrow_secondary', 'kind' => 'text', 'label' => 'Secondary eyebrow (e.g. waitlist tag)', 'max' => 255],
                            ['key' => 'title', 'kind' => 'text', 'max' => 255],
                            ['key' => 'subtitle', 'kind' => 'text', 'max' => 500],
                            ['key' => 'mini_tiers', 'kind' => 'repeater', 'label' => 'Mini tier cards (e.g. 1/2/3 peptides)', 'fields' => [
                                ['key' => 'label', 'kind' => 'text', 'max' => 60, 'required' => true],
                                ['key' => 'price', 'kind' => 'text', 'max' => 120],
                                ['key' => 'note', 'kind' => 'text', 'max' => 120],
                            ]],
                            ['key' => 'features', 'kind' => 'repeater', 'label' => 'Features', 'simple' => true, 'fields' => [
                                ['key' => 'feature', 'kind' => 'text', 'max' => 255],
                            ]],
                            ['key' => 'waitlist_heading', 'kind' => 'text', 'max' => 120],
                            ['key' => 'waitlist_body', 'kind' => 'textarea', 'max' => 500],
                            ['key' => 'waitlist_placeholder', 'kind' => 'text', 'max' => 60],
                            ['key' => 'waitlist_cta_label', 'kind' => 'text', 'max' => 60],
                            ['key' => 'success_heading', 'kind' => 'text', 'max' => 120],
                            ['key' => 'success_body', 'kind' => 'textarea', 'max' => 500],
                            ['key' => 'fallback_cta_label', 'kind' => 'text', 'max' => 120],
                            ['key' => 'fallback_cta_url', 'kind' => 'text', 'max' => 2048],
                            ['key' => 'fallback_note', 'kind' => 'text', 'max' => 255],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'faq',
                'name' => 'FAQ',
                'icon' => 'heroicon-o-question-mark-circle',
                'description' => 'Light-theme FAQ accordion. Centered max-w-3xl column. Sage section-label eyebrow → display heading with sage emphasis on the last word → bordered disclosure rows. Alpine-driven (one open at a time).',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Sage eyebrow', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                        ['key' => 'emphasis', 'kind' => 'text', 'label' => 'Heading accent (sage)', 'max' => 255, 'help' => 'Final-clause word(s) rendered in sage green.'],
                        ['key' => 'description', 'kind' => 'textarea', 'label' => 'Intro description', 'max' => 500, 'help' => 'Short paragraph rendered under the heading in the intro column.'],
                        ['key' => 'cta_label', 'kind' => 'text', 'label' => 'CTA label', 'max' => 60],
                        ['key' => 'cta_url', 'kind' => 'text', 'label' => 'CTA URL', 'max' => 2048],
                        ['key' => 'image', 'kind' => 'image', 'label' => 'Intro image'],
                        ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                        ['key' => 'faqs', 'kind' => 'repeater', 'label' => 'Questions', 'fields' => [
                            ['key' => 'q', 'kind' => 'text', 'label' => 'Question', 'max' => 255, 'required' => true],
                            ['key' => 'a', 'kind' => 'textarea', 'label' => 'Answer', 'required' => true],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'hero',
                'name' => 'Hero',
                'icon' => 'heroicon-o-star',
                'description' => 'Full-width hero slideshow: one or more slides (background image + heading + description + CTA each), with an optional floating highlight card. With no slides, the static headline fields render over the background image or video. The banner layout instead renders the static fields as a centered rounded banner (eyebrow, headline, subtext, CTA).',
                'schema' => [
                    'fields' => [
                        ['key' => 'layout', 'kind' => 'select', 'default' => 'slider', 'help' => 'Centered banner ignores the slides and renders the static hero fields centered over the background image in a rounded frame.', 'options' => [
                            ['value' => 'slider', 'label' => 'Slideshow (slides below)'],
                            ['value' => 'banner', 'label' => 'Centered banner (static fields below)'],
                        ]],
                        ['key' => 'slides', 'kind' => 'repeater', 'label' => 'Slides', 'fields' => [
                            ['key' => 'image', 'kind' => 'image', 'label' => 'Background image'],
                            ['key' => 'image_alt', 'kind' => 'text', 'label' => 'Image alt text', 'max' => 255],
                            ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                            ['key' => 'heading_emphasis', 'kind' => 'text', 'label' => 'Heading accent (italic)', 'max' => 120, 'help' => 'Optional accent run rendered after the heading.'],
                            ['key' => 'description', 'kind' => 'richtext', 'help' => 'Rendered as HTML on the public site — bold/italic/line breaks are honored.'],
                            ['key' => 'cta_label', 'kind' => 'text', 'label' => 'CTA label', 'max' => 60],
                            ['key' => 'cta_url', 'kind' => 'text', 'label' => 'CTA URL', 'max' => 2048],
                            ['key' => 'text_theme', 'kind' => 'select', 'label' => 'Text tone', 'default' => 'dark', 'help' => "Pick the tone that stays readable over this slide's image.", 'options' => [
                                ['value' => 'dark', 'label' => 'Dark text (light image)'],
                                ['value' => 'light', 'label' => 'Light text (dark image)'],
                            ]],
                        ]],
                        ['key' => 'highlight_position', 'kind' => 'select', 'label' => 'Highlight card position', 'default' => 'middle-right', 'help' => 'Where the highlight cards sit on the slideshow. Hidden on phones, where the cards stack under the slide instead.', 'options' => [
                            ['value' => 'top-left', 'label' => 'Top left'],
                            ['value' => 'top-center', 'label' => 'Top centre'],
                            ['value' => 'top-right', 'label' => 'Top right'],
                            ['value' => 'middle-left', 'label' => 'Middle left'],
                            ['value' => 'middle-center', 'label' => 'Middle centre'],
                            ['value' => 'middle-right', 'label' => 'Middle right'],
                            ['value' => 'bottom-left', 'label' => 'Bottom left'],
                            ['value' => 'bottom-center', 'label' => 'Bottom centre'],
                            ['value' => 'bottom-right', 'label' => 'Bottom right'],
                        ]],
                        ['key' => 'highlight_title', 'kind' => 'text', 'label' => 'Title', 'max' => 120],
                        ['key' => 'highlight_subtitle', 'kind' => 'text', 'label' => 'Subtitle', 'max' => 120],
                        ['key' => 'highlight_quote', 'kind' => 'textarea', 'label' => 'Quote', 'max' => 255],
                        ['key' => 'highlight_image', 'kind' => 'image', 'label' => 'Image'],
                        ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow tag', 'max' => 120, 'help' => 'Small uppercase pill above the headline.'],
                        ['key' => 'headline', 'kind' => 'text', 'max' => 255],
                        ['key' => 'headline_emphasis', 'kind' => 'text', 'label' => 'Headline accent (italic gold)', 'max' => 120, 'help' => 'Optional gold italic run rendered after the headline.'],
                        ['key' => 'subhead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'primary_cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'primary_cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'secondary_cta_label', 'kind' => 'text', 'max' => 60],
                        ['key' => 'secondary_cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'trust_microcopy', 'kind' => 'text', 'label' => 'Trust micro-copy under buttons', 'max' => 255],
                        ['key' => 'background_image', 'kind' => 'image', 'label' => 'Background image'],
                        ['key' => 'background_video_url', 'kind' => 'text', 'label' => 'Background video embed URL', 'max' => 2048, 'help' => 'Optional Vimeo/YouTube embed URL with autoplay/loop/mute parameters.'],
                    ],
                ],
            ],
            [
                'slug' => 'product-grid',
                'name' => 'Product grid',
                'icon' => 'heroicon-o-squares-2x2',
                'description' => 'Grid of product cards. Pick products by hand or let a rule (featured, newest, category) choose them.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'subhead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'columns', 'kind' => 'select', 'default' => '3', 'help' => 'Layout hint for the frontend grid.', 'options' => [
                            ['value' => '2', 'label' => '2 columns'],
                            ['value' => '3', 'label' => '3 columns'],
                            ['value' => '4', 'label' => '4 columns'],
                        ]],
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
                        ['key' => 'limit', 'kind' => 'number', 'default' => 12, 'min' => 1, 'max' => 24, 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'not_equals', 'value' => 'manual'],
                        ]],
                    ],
                    'resolvers' => [
                        ['op' => 'products_by_mode', 'output' => 'products'],
                    ],
                ],
            ],
            [
                'slug' => 'package-slider',
                'name' => 'Package slider',
                'icon' => 'heroicon-o-queue-list',
                'description' => 'Horizontal carousel of package cards with live plan pricing. Pick packages by hand or let a rule (featured, newest, category) choose them.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'subhead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'mode', 'kind' => 'select', 'default' => 'manual', 'required' => true, 'options' => [
                            ['value' => 'manual', 'label' => 'Pick packages by hand'],
                            ['value' => 'featured', 'label' => 'Featured packages'],
                            ['value' => 'newest', 'label' => 'Newest packages'],
                            ['value' => 'category', 'label' => 'All packages in a category'],
                        ]],
                        ['key' => 'package_ids', 'kind' => 'packages', 'label' => 'Packages', 'raw' => true, 'default' => [], 'help' => 'Shown in the order selected. Unpublished packages are dropped automatically.', 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'equals', 'value' => 'manual'],
                        ]],
                        ['key' => 'category_id', 'kind' => 'category', 'label' => 'Category', 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'equals', 'value' => 'category'],
                        ]],
                        ['key' => 'limit', 'kind' => 'number', 'default' => 8, 'min' => 1, 'max' => 24, 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'not_equals', 'value' => 'manual'],
                        ]],
                        ['key' => 'autoplay', 'kind' => 'boolean', 'default' => false, 'help' => 'Layout hint for the frontend carousel.'],
                        // Card copy. Null defaults, deliberately: these are the
                        // operator's words, and a content-free blueprint must
                        // not ship any. The frontend omits what it is not given.
                        ['key' => 'price_intro_label', 'kind' => 'text', 'default' => null, 'help' => 'Precedes the introductory price, e.g. "First month". Omitted when empty.'],
                        ['key' => 'price_recurring_label', 'kind' => 'text', 'default' => null, 'help' => 'Precedes the recurring price, e.g. "Recurring". Omitted when empty.'],
                        ['key' => 'cta_label', 'kind' => 'text', 'default' => null, 'help' => 'Use {package} for the package name. The button is hidden when empty.'],
                        ['key' => 'cta_url', 'kind' => 'text', 'default' => null, 'help' => 'Where every card button goes, e.g. /checkout.'],
                        ['key' => 'range_aria_label', 'kind' => 'text', 'default' => null, 'help' => 'Accessible name for the carousel scrubber.'],
                    ],
                    'resolvers' => [
                        ['op' => 'packages_by_mode', 'output' => 'packages'],
                    ],
                ],
            ],
            [
                'slug' => 'package-pricing-comparison',
                'name' => 'Package pricing comparison',
                'icon' => 'heroicon-o-scale',
                'description' => 'Side-by-side comparison columns for 2–3 packages using live plan pricing from the catalog.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'subhead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'package_ids', 'kind' => 'packages', 'label' => 'Packages to compare', 'raw' => true, 'default' => [], 'max' => 3, 'help' => '2–3 packages, shown in the order selected with live plan pricing.'],
                        ['key' => 'highlight_package_id', 'kind' => 'package', 'label' => 'Highlighted package', 'raw' => true, 'help' => 'Optional. The frontend emphasizes this column (e.g. "Most popular").'],
                    ],
                    'resolvers' => [
                        ['op' => 'inline_packages', 'input' => 'package_ids', 'output' => 'packages'],
                    ],
                ],
            ],
            [
                'slug' => 'product-callout',
                'name' => 'Product callout',
                'icon' => 'heroicon-o-megaphone',
                'description' => 'Single featured product or package promo with custom headline and copy overriding the catalog text.',
                'schema' => [
                    'fields' => [
                        ['key' => 'item_type', 'kind' => 'select', 'default' => 'product', 'required' => true, 'options' => [
                            ['value' => 'product', 'label' => 'Product'],
                            ['value' => 'package', 'label' => 'Package'],
                        ]],
                        ['key' => 'product_id', 'kind' => 'product', 'label' => 'Product', 'raw' => true, 'visible_when' => [
                            ['field' => 'item_type', 'operator' => 'equals', 'value' => 'product'],
                        ]],
                        ['key' => 'package_id', 'kind' => 'package', 'label' => 'Package', 'raw' => true, 'visible_when' => [
                            ['field' => 'item_type', 'operator' => 'equals', 'value' => 'package'],
                        ]],
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'headline', 'kind' => 'text', 'max' => 255],
                        ['key' => 'body', 'kind' => 'textarea', 'max' => 1000],
                        ['key' => 'cta_label', 'kind' => 'text', 'max' => 80],
                        ['key' => 'cta_url', 'kind' => 'text', 'max' => 2048],
                        ['key' => 'image', 'kind' => 'image', 'label' => 'Image', 'help' => 'Optional custom image. Falls back to the catalog hero image when left blank.'],
                        ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                        ['key' => 'image_right', 'kind' => 'boolean', 'label' => 'Image on the right', 'default' => false, 'help' => 'Default is image on the left.'],
                    ],
                    'resolvers' => [
                        ['op' => 'inline_product', 'input' => 'product_id', 'output' => 'product', 'when' => [
                            ['field' => 'item_type', 'operator' => 'equals', 'value' => 'product'],
                        ]],
                        ['op' => 'inline_package', 'input' => 'package_id', 'output' => 'package', 'when' => [
                            ['field' => 'item_type', 'operator' => 'equals', 'value' => 'package'],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'category-grid',
                'name' => 'Category grid',
                'icon' => 'heroicon-o-squares-2x2',
                'description' => 'Grid of catalog category cards linking to their listings. Pick categories by hand or show all visible ones.',
                'schema' => [
                    'fields' => [
                        ['key' => 'eyebrow', 'kind' => 'text', 'max' => 120],
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'subhead', 'kind' => 'textarea', 'max' => 500],
                        ['key' => 'mode', 'kind' => 'select', 'default' => 'all', 'required' => true, 'options' => [
                            ['value' => 'all', 'label' => 'All visible categories'],
                            ['value' => 'manual', 'label' => 'Pick categories by hand'],
                        ]],
                        ['key' => 'category_ids', 'kind' => 'categories', 'label' => 'Categories', 'default' => [], 'help' => 'Shown in the order selected. Hidden categories are dropped automatically.', 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'equals', 'value' => 'manual'],
                        ]],
                        ['key' => 'limit', 'kind' => 'number', 'default' => 12, 'min' => 1, 'max' => 24, 'visible_when' => [
                            ['field' => 'mode', 'operator' => 'not_equals', 'value' => 'manual'],
                        ]],
                    ],
                    'resolvers' => [
                        ['op' => 'categories', 'output' => 'categories'],
                    ],
                ],
            ],
            [
                'slug' => 'benefits-diagram',
                'name' => 'Benefits diagram',
                'icon' => 'heroicon-o-viewfinder-circle',
                'description' => 'Centered image with benefit points around it, connected by markers and dashed lines. Optional rating row and CTA (link or add-to-cart) underneath.',
                'schema' => [
                    'fields' => [
                        ['key' => 'heading', 'kind' => 'text', 'max' => 255],
                        ['key' => 'image', 'kind' => 'image', 'label' => 'Center image'],
                        ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                        ['key' => 'marker_style', 'kind' => 'select', 'default' => 'dot', 'help' => 'How each point is marked next to its connector line.', 'options' => [
                            ['value' => 'dot', 'label' => 'Dot'],
                            ['value' => 'number', 'label' => 'Number'],
                            ['value' => 'icon', 'label' => 'Per-point icon'],
                        ]],
                        ['key' => 'points', 'kind' => 'repeater', 'label' => 'Benefit points', 'fields' => [
                            ['key' => 'text', 'kind' => 'textarea', 'max' => 255, 'required' => true],
                            ['key' => 'side', 'kind' => 'select', 'default' => 'left', 'options' => [
                                ['value' => 'left', 'label' => 'Left of image'],
                                ['value' => 'right', 'label' => 'Right of image'],
                            ]],
                            ['key' => 'icon', 'kind' => 'image', 'label' => 'Marker icon', 'help' => 'Only used when marker style is "Per-point icon".'],
                        ]],
                        ['key' => 'rating_value', 'kind' => 'number', 'min' => 0, 'max' => 5, 'help' => 'Star fill, 0–5.'],
                        ['key' => 'rating_text', 'kind' => 'text', 'max' => 255, 'help' => 'Line rendered next to the stars.'],
                        ['key' => 'cta', 'kind' => 'cta', 'label' => 'Call to action'],
                        ['key' => 'cta_subtext', 'kind' => 'text', 'label' => 'CTA subtext', 'max' => 160, 'help' => 'Small reassurance line under the button — renders with a lock icon.'],
                    ],
                ],
            ],
            [
                'slug' => 'image-callout-banner',
                'name' => 'Image callout banner',
                'icon' => 'heroicon-o-photo',
                'description' => 'Full-width background image with up to two floating callouts (icon/logo, title, text, optional CTA), positioned left and/or right. Each callout renders as a frosted card or as plain feature copy; the photo can be monochrome-tinted.',
                'schema' => [
                    'fields' => [
                        ['key' => 'background_image', 'kind' => 'image', 'label' => 'Background image'],
                        ['key' => 'background_alt', 'kind' => 'text', 'label' => 'Background alt text', 'max' => 255],
                        ['key' => 'background_treatment', 'kind' => 'select', 'default' => 'none', 'help' => 'Monochrome tint recolors the photo to a single hue (mix-blend color).', 'options' => [
                            ['value' => 'none', 'label' => 'None'],
                            ['value' => 'tint', 'label' => 'Monochrome tint'],
                        ]],
                        ['key' => 'tint_color', 'kind' => 'color', 'label' => 'Tint color', 'help' => 'Leave empty for the warm neutral default.', 'visible_when' => [
                            ['field' => 'background_treatment', 'operator' => 'equals', 'value' => 'tint'],
                        ]],
                        ['key' => 'callouts', 'kind' => 'repeater', 'label' => 'Callout cards', 'max' => 2, 'fields' => [
                            ['key' => 'position', 'kind' => 'select', 'default' => '0', 'required' => true, 'help' => 'Which slot over the image the card occupies.', 'options' => [
                                ['value' => '0', 'label' => 'Position 1 (left)'],
                                ['value' => '1', 'label' => 'Position 2 (right)'],
                            ]],
                            ['key' => 'variant', 'kind' => 'select', 'default' => 'card', 'help' => 'Compact media card: small left-aligned frosted card (image over text). Feature copy: large serif title with left-aligned text directly over the image, no card.', 'options' => [
                                ['value' => 'card', 'label' => 'Frosted card'],
                                ['value' => 'media-card', 'label' => 'Compact media card'],
                                ['value' => 'feature', 'label' => 'Feature copy (no card)'],
                            ]],
                            ['key' => 'align', 'kind' => 'select', 'default' => 'center', 'help' => 'Vertical placement inside the banner on desktop.', 'options' => [
                                ['value' => 'center', 'label' => 'Vertically centered'],
                                ['value' => 'top', 'label' => 'Top'],
                                ['value' => 'bottom', 'label' => 'Bottom'],
                            ]],
                            ['key' => 'color', 'kind' => 'color', 'label' => 'Card color', 'help' => 'Card background. Leave empty for the frosted light default.'],
                            ['key' => 'icon', 'kind' => 'image', 'label' => 'Icon / logo'],
                            ['key' => 'icon_width', 'kind' => 'number', 'label' => 'Icon width (px)', 'min' => 16, 'max' => 600, 'help' => 'Rendered width of the icon/logo. Leave empty for the small default.'],
                            ['key' => 'title', 'kind' => 'text', 'max' => 255],
                            ['key' => 'content', 'kind' => 'richtext', 'help' => 'Rendered as HTML on the public site — bold/italic/line breaks are honored.'],
                            ['key' => 'cta', 'kind' => 'cta'],
                        ]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Benefits-Him and Benefits-Her share one field surface — only the
     * rendered column order differs, which is the frontend component's
     * concern (keyed by slug).
     *
     * @return array<string, mixed>
     */
    private function benefitsPitchSchema(): array
    {
        return [
            'fields' => [
                ['key' => 'eyebrow', 'kind' => 'text', 'label' => 'Editorial tag', 'max' => 120],
                ['key' => 'heading', 'kind' => 'text', 'max' => 255, 'required' => true],
                ['key' => 'emphasis', 'kind' => 'text', 'label' => 'Heading accent (italic gold)', 'max' => 255, 'help' => 'Optional italic gold run rendered after the heading.'],
                ['key' => 'lead', 'kind' => 'textarea', 'max' => 500],
                ['key' => 'cta_label', 'kind' => 'text', 'max' => 60],
                ['key' => 'cta_url', 'kind' => 'text', 'max' => 2048],
                ['key' => 'image', 'kind' => 'image', 'label' => 'Lifestyle image'],
                ['key' => 'image_alt', 'kind' => 'text', 'max' => 255],
                ['key' => 'benefits', 'kind' => 'repeater', 'label' => 'Protocol cards (4 typical)', 'fields' => [
                    ['key' => 'category', 'kind' => 'text', 'label' => 'Tag (mono uppercase)', 'max' => 120],
                    ['key' => 'pill', 'kind' => 'text', 'label' => 'Badge pill', 'max' => 60],
                    ['key' => 'title', 'kind' => 'text', 'max' => 255, 'required' => true],
                    ['key' => 'body', 'kind' => 'textarea', 'required' => true],
                ]],
            ],
        ];
    }
}
