<?php

namespace App\Data\Checkout;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class CheckoutData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $cart_ulid,

        #[Required, StringType]
        public string $lead_uuid,

        /**
         * Pre-filled intake answers keyed by PRX field slug.
         * Typically empty — the PRX embed collects answers after handoff.
         *
         * @var array<string, mixed>
         */
        public array $intake_answers = [],

        /**
         * Tokenized payment data from the gateway's client-side SDK.
         * Required when checkout_path = 'local'; not used for the PRX path.
         *
         * Shape is gateway-specific:
         *   NMI        → { payment_token: string }
         *   Auth.Net   → { dataDescriptor: string, dataValue: string }
         *   Stripe     → { payment_method_id: string }
         *   Square     → { nonce: string }
         *
         * @var array<string, mixed>|null
         */
        #[ArrayType]
        public ?array $payment_method = null,
    ) {}
}
