<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ContactSettings extends Settings
{
    public ?string $support_email;

    public ?string $sales_email;

    public ?string $phone;

    public ?string $address_line_1;

    public ?string $address_line_2;

    public ?string $city;

    public ?string $state;

    public ?string $postal_code;

    public ?string $country;

    public ?string $business_hours;

    public ?string $instagram_url;

    public ?string $twitter_url;

    public ?string $facebook_url;

    public ?string $linkedin_url;

    public ?string $tiktok_url;

    public ?string $youtube_url;

    public static function group(): string
    {
        return 'contact';
    }
}
