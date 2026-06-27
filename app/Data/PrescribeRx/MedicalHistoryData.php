<?php

namespace App\Data\PrescribeRx;

use Spatie\LaravelData\Data;

class MedicalHistoryData extends Data
{
    public function __construct(
        /** @var array<int, string> */
        public array $allergies = [],
        /** @var array<int, string> */
        public array $medications = [],
        /** @var array<int, string> */
        public array $conditions = [],
    ) {}
}
