<?php

namespace App\Data\PrescribeRx;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class PatientData extends Data
{
    public function __construct(
        #[Required, Max(120)]
        public string $first_name,
        #[Required, Max(120)]
        public string $last_name,
        #[Required, Email, Max(255)]
        public string $email,
        #[Required, Date]
        public string $date_of_birth,
        #[Max(32)]
        public ?string $phone = null,
        #[In(['male', 'female', 'other', 'unspecified'])]
        public ?string $gender = null,
        public ?AddressData $address = null,
    ) {}
}
