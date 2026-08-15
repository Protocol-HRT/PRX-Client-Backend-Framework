<?php

namespace App\Data\Llm;

use Spatie\LaravelData\Data;

class GeneratedSeoData extends Data
{
    public function __construct(
        public string $meta_title,
        public string $meta_description,
    ) {}
}
