<?php

namespace App\Data\Catalog;

use App\Enums\CatalogStatus;
use App\Enums\InventoryStatus;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    /**
     * @param  array<int, string>  $gallery
     * @param  array<int, array{item: string}>  $highlights
     * @param  array<int, int>  $category_ids
     * @param  array<int, int>  $tag_ids
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Max(255)]
        public ?string $slug = null,
        #[Max(255)]
        public ?string $subtitle = null,
        #[Max(2000)]
        public ?string $short_description = null,
        public ?string $description = null,
        #[Max(2048)]
        public ?string $hero_image_path = null,
        public array $gallery = [],
        #[WithCast(EnumCast::class)]
        public CatalogStatus $status = CatalogStatus::Draft,
        public ?int $product_class_id = null,
        public ?int $product_type_id = null,
        public ?int $product_form_id = null,
        public ?int $administration_method_id = null,
        #[Min(0)]
        public ?float $volume = null,
        public ?int $volume_unit_id = null,
        #[WithCast(EnumCast::class)]
        public ?InventoryStatus $inventory_status = null,
        public bool $is_controlled_substance = false,
        public bool $rx_required = false,
        public ?float $retail_price = null,
        public ?float $sale_price = null,
        #[Min(0)]
        public ?float $cost = null,
        #[Max(32)]
        public ?string $price_suffix = null,
        #[Max(36)]
        public ?string $provider_product_id = null,
        #[Max(255)]
        public ?string $provider_product_sku = null,
        #[Max(255)]
        public ?string $provider_encounter_type_id = null,
        #[Max(32)]
        public ?string $badge_text = null,
        public array $highlights = [],
        public array $detail_sections = [],
        public bool $is_featured = false,
        public bool $is_in_stock = true,
        public bool $requires_lab = false,
        #[Max(255)]
        public ?string $meta_title = null,
        #[Max(500)]
        public ?string $meta_description = null,
        #[Max(2048)]
        public ?string $og_image_path = null,
        public int $position = 0,
        public array $category_ids = [],
        public array $tag_ids = [],
    ) {}
}
