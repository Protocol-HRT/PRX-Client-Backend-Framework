<?php

namespace App\Data\Leads;

use App\Enums\CheckoutPath;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class LeadData extends Data
{
    /**
     * @param  DataCollection<int, CartItemData>|array<int, CartItemData>  $cart_items
     */
    public function __construct(
        #[Required, Max(255)]
        public string $first_name,
        #[Required, Max(255)]
        public string $last_name,
        #[Required, Email, Max(255)]
        public string $email,
        #[Max(64)]
        public ?string $phone = null,
        public ?string $date_of_birth = null,
        #[Max(32)]
        public ?string $gender = null,
        #[Max(255)]
        public ?string $address_line1 = null,
        #[Max(255)]
        public ?string $address_line2 = null,
        #[Max(255)]
        public ?string $city = null,
        #[Max(8)]
        public ?string $state = null,
        #[Max(16)]
        public ?string $postal_code = null,
        #[Max(2)]
        public string $country = 'US',
        public bool $sms_consent = false,
        public bool $email_consent = false,
        #[DataCollectionOf(CartItemData::class)]
        public array|DataCollection $cart_items = [],
        public ?float $cart_subtotal = null,
        #[WithCast(EnumCast::class)]
        public CheckoutPath $checkout_path = CheckoutPath::PrescribeRx,
        public ?string $utm_source = null,
        public ?string $utm_medium = null,
        public ?string $utm_campaign = null,
        public ?string $utm_term = null,
        public ?string $utm_content = null,
        #[Max(2048)]
        public ?string $referrer = null,
        #[Max(2048)]
        public ?string $landing_url = null,
        #[Max(512)]
        public ?string $user_agent = null,
        #[Max(45)]
        public ?string $ip_address = null,
        public ?string $notes = null,
    ) {}
}
