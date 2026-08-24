<?php

namespace App\Data\Catalog;

use App\Enums\BillingMode;
use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\RebillStrategy;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class PlanData extends Data
{
    /**
     * @param  array<int, string>  $gallery
     * @param  array<int, string>  $provider_product_ids
     * @param  array<int, int>  $category_ids
     * @param  array<int, int>  $tag_ids
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Max(255)]
        public ?string $slug = null,
        public ?int $package_id = null,
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
        #[WithCast(EnumCast::class)]
        public BillingPeriod $billing_period = BillingPeriod::Monthly,
        #[WithCast(EnumCast::class)]
        public ?BillingMode $billing_mode = null,
        public ?float $retail_price = null,
        public ?float $sale_price = null,
        public ?float $intro_price = null,
        #[Min(0)]
        public ?float $cost = null,
        #[Max(32)]
        public ?string $price_suffix = null,
        #[Max(36)]
        public ?string $provider_plan_id = null,
        #[Max(255)]
        public ?string $provider_plan_sku = null,
        public ?array $provider_product_ids = null,
        #[Max(32)]
        public ?string $badge_text = null,
        public ?int $term_months = null,
        public bool $is_recurring = false,
        #[WithCast(EnumCast::class)]
        public ?RebillStrategy $rebill_strategy = null,
        public ?int $trial_days = null,
        public bool $is_default = false,
        public bool $is_featured = false,
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
