<?php

namespace App\Data\Catalog;

use App\Enums\CatalogStatus;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class PackageData extends Data
{
    /**
     * `detail_layout` — the per-record storefront presentation knobs:
     * template, accordion placement, Pair With per-view counts, and which
     * bottom rails render.
     *
     * NOT OPTIONAL, AND NOT DECORATIVE. This property was missing, so the five
     * Layout selects on the form wrote into a DTO that dropped them and an
     * update action that never referenced them. The form saved successfully
     * and persisted nothing; every value in the column came from the fill
     * scripts, and an operator could set a knob for months without it ever
     * taking effect. Pinned by DetailLayoutPersistsTest.
     *
     * Kept as a plain array rather than a typed object because the frontend
     * owns this vocabulary and normalizes it (`presentation.js`); the backend
     * stores the operator's choice and does not interpret it. It IS pruned of
     * empty values on write — see App\Support\DetailLayout for why that is
     * load-bearing rather than tidiness.
     *
     * @param  array<int, string>  $gallery
     * @param  array<int, array{item: string}>  $highlights
     * @param  array<string, mixed>  $detail_layout
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
        #[Max(2048)]
        public ?string $banner_image_path = null,
        #[Present]
        public array $gallery = [],
        #[WithCast(EnumCast::class)]
        public CatalogStatus $status = CatalogStatus::Draft,

        // What KIND of package this is — Atlas uses `protocol` and `stack`.
        // A free string, not an enum: prx-backend ships to more than one
        // client and their vocabulary is their own. Null means unclassified,
        // which is the correct starting state.
        #[Max(32)]
        public ?string $tier = null,
        public ?float $retail_price = null,
        public ?float $sale_price = null,
        #[Min(0)]
        public ?float $cost = null,
        #[Max(32)]
        public ?string $price_suffix = null,
        #[Max(36)]
        public ?string $provider_package_id = null,
        #[Max(255)]
        public ?string $provider_package_sku = null,
        #[Max(255)]
        public ?string $provider_encounter_type_id = null,
        #[Max(32)]
        public ?string $badge_text = null,
        #[Present]
        public array $highlights = [],
        #[Present]
        public array $detail_sections = [],
        public array $detail_layout = [],
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
        #[Present]
        public array $category_ids = [],
        #[Present]
        public array $tag_ids = [],
    ) {}
}
