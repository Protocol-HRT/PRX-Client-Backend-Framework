<?php

namespace App\Data\Catalog;

use App\Enums\Catalog\SexEligibility;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class IngredientData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Max(255)]
        public ?string $slug = null,
        #[Max(64)]
        public ?string $short_name = null,
        #[Max(10000)]
        public ?string $description = null,

        // Eligibility. Defaults to Any rather than being required, so an
        // operator who never opens the tab gets the permissive value — the
        // failure direction is deliberate (see the enum's doc comment).
        public SexEligibility $sex_eligibility = SexEligibility::Any,
        #[Min(0), Max(120)]
        public ?int $min_age = null,
        #[Min(0), Max(120)]
        public ?int $max_age = null,
        #[Max(2000)]
        public ?string $eligibility_note = null,
        public bool $is_active = true,
        public int $position = 0,
        #[Uuid, Max(36)]
        public ?string $provider_ingredient_id = null,
    ) {}
}
