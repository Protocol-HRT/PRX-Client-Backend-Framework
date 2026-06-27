<?php

namespace Tests\Feature\Settings;

use App\Actions\Settings\UpdateBrandSettingsAction;
use App\Actions\Settings\UpdateSeoSettingsAction;
use App\Data\Settings\BrandSettingsData;
use App\Data\Settings\SeoSettingsData;
use App\Settings\BrandSettings;
use App\Settings\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_action_persists_brand_settings_through_dto(): void
    {
        $action = $this->app->make(UpdateBrandSettingsAction::class);

        $action->execute(BrandSettingsData::validateAndCreate([
            'name' => 'Acme Wellness',
            'tagline' => 'Custom tagline',
            'logo_path' => '/images/logo.svg',
            'favicon_path' => '/images/logo.svg',
            'hero_image_path' => '/images/lifestyle/him-hero.webp',
        ]));

        // Re-resolve to confirm persistence (Settings is a singleton; force a fresh repository read)
        $brand = $this->app->make(BrandSettings::class);
        $this->assertSame('Acme Wellness', $brand->name);
        $this->assertSame('Custom tagline', $brand->tagline);
    }

    public function test_invalid_payload_is_rejected_by_dto(): void
    {
        $this->expectException(ValidationException::class);

        BrandSettingsData::validateAndCreate([
            'name' => '',
            'tagline' => 'x',
            'logo_path' => 'x',
            'favicon_path' => 'x',
        ]);
    }

    public function test_config_endpoint_reflects_brand_and_seo_from_settings(): void
    {
        $this->app->make(UpdateBrandSettingsAction::class)
            ->execute(BrandSettingsData::validateAndCreate([
                'name' => 'Helios Clinic',
                'tagline' => 'Test tagline',
                'logo_path' => '/images/logo.svg',
                'favicon_path' => '/images/logo.svg',
                'hero_image_path' => '/images/lifestyle/him-hero.webp',
            ]));

        $this->app->make(UpdateSeoSettingsAction::class)
            ->execute(SeoSettingsData::validateAndCreate([
                'default_meta_title' => 'Helios Clinic — Hormone Therapy',
                'default_meta_description' => 'A clinic-wide DB-driven description.',
                'og_image_path' => '/images/lifestyle/him-hero.webp',
                'allow_indexing' => false,
            ]));

        Cache::forget('api.v1.config');

        $this->getJson('/api/v1/config')
            ->assertStatus(200)
            ->assertJsonPath('data.brand.name', 'Helios Clinic')
            ->assertJsonPath('data.seo.default_title', 'Helios Clinic — Hormone Therapy')
            ->assertJsonPath('data.seo.default_description', 'A clinic-wide DB-driven description.')
            ->assertJsonPath('data.seo.allow_indexing', false);
    }
}
