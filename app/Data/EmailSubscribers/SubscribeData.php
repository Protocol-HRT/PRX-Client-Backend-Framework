<?php

namespace App\Data\EmailSubscribers;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SubscribeData extends Data
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        #[Required, Email, Max(255)]
        public string $email,
        #[Required, Max(64)]
        public string $source,
        #[Max(255)]
        public ?string $first_name = null,
        #[Max(255)]
        public ?string $last_name = null,
        #[Max(64)]
        public ?string $phone = null,
        public bool $email_consent = true,
        public bool $sms_consent = false,
        public ?string $utm_source = null,
        public ?string $utm_medium = null,
        public ?string $utm_campaign = null,
        public ?string $utm_term = null,
        public ?string $utm_content = null,
        #[Max(2048)]
        public ?string $referrer = null,
        #[Max(2048)]
        public ?string $landing_url = null,
        #[Max(45)]
        public ?string $ip_address = null,
        #[Max(512)]
        public ?string $user_agent = null,
        public ?array $meta = null,
    ) {}
}
