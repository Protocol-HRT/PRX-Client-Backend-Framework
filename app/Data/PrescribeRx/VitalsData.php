<?php

namespace App\Data\PrescribeRx;

use Spatie\LaravelData\Data;

class VitalsData extends Data
{
    public function __construct(
        public ?int $height_inches = null,
        public ?float $weight_lbs = null,
        public ?int $blood_pressure_systolic = null,
        public ?int $blood_pressure_diastolic = null,
        public ?int $heart_rate_bpm = null,
    ) {}
}
