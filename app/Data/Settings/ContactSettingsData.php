<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;

class ContactSettingsData extends Data
{
    public function __construct(
        #[Email, Max(255)]
        public ?string $support_email = null,
        #[Email, Max(255)]
        public ?string $sales_email = null,
        #[Max(64)]
        public ?string $phone = null,
        #[Max(255)]
        public ?string $address_line_1 = null,
        #[Max(255)]
        public ?string $address_line_2 = null,
        #[Max(128)]
        public ?string $city = null,
        #[Max(128)]
        public ?string $state = null,
        #[Max(32)]
        public ?string $postal_code = null,
        #[Max(2)]
        public ?string $country = null,
        #[Max(255)]
        public ?string $business_hours = null,
        #[Url, Max(2048)]
        public ?string $instagram_url = null,
        #[Url, Max(2048)]
        public ?string $twitter_url = null,
        #[Url, Max(2048)]
        public ?string $facebook_url = null,
        #[Url, Max(2048)]
        public ?string $linkedin_url = null,
        #[Url, Max(2048)]
        public ?string $tiktok_url = null,
        #[Url, Max(2048)]
        public ?string $youtube_url = null,
    ) {}
}
