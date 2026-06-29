<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class CommunicationSettingsData extends Data
{
    public function __construct(
        #[Max(64)]
        public ?string $twilio_account_sid = null,
        #[Max(64)]
        public ?string $twilio_auth_token = null,
        #[Max(20)]
        public ?string $twilio_from_number = null,
        public bool $sms_enabled = false,
        #[Max(500)]
        public ?string $sms_opt_in_message = null,
        public bool $voice_enabled = false,
        public bool $video_enabled = false,
    ) {}
}
