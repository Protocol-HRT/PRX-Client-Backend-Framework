<?php

namespace App\Data\Patient;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class PatientRegistrationData extends Data
{
    public function __construct(
        #[Required, Email, Max(255)]
        public string $email,
        #[Required, Min(8), Max(255)]
        public string $password,
        #[Required, Max(100)]
        public string $first_name,
        #[Required, Max(100)]
        public string $last_name,
        #[Max(32)]
        public ?string $phone = null,
        public ?string $date_of_birth = null,
    ) {}
}
