<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush the config cache between tests so cached values never bleed across.
        cache()->forget('api.v1.config');
    }

    public function test_config_endpoint_is_publicly_accessible(): void
    {
        $this->getJson('/api/v1/config')->assertOk();
    }

    public function test_config_response_has_expected_top_level_keys(): void
    {
        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['brand', 'theme', 'contact', 'seo', 'provider'],
            ]);
    }

    public function test_config_brand_section_has_required_keys(): void
    {
        $this->getJson('/api/v1/config')
            ->assertJsonStructure([
                'data' => [
                    'brand' => ['name', 'tagline', 'logo_url', 'favicon_url'],
                ],
            ]);
    }

    public function test_config_provider_section_exposes_capability_flags(): void
    {
        $this->getJson('/api/v1/config')
            ->assertJsonStructure([
                'data' => [
                    'provider' => [
                        'name',
                        'slug',
                        'supports_embed',
                        'supports_patient_portal_auth',
                    ],
                ],
            ]);
    }

    public function test_config_response_is_cached(): void
    {
        $this->getJson('/api/v1/config')->assertOk();

        $this->assertNotNull(cache()->get('api.v1.config'));
    }

    public function test_config_seo_section_exposes_tracking_fields(): void
    {
        $this->getJson('/api/v1/config')
            ->assertJsonStructure([
                'data' => [
                    'seo' => [
                        'google_analytics_id',
                        'google_tag_manager_id',
                        'facebook_pixel_id',
                        'tiktok_pixel_id',
                        'custom_head_scripts',
                        'custom_body_scripts',
                        'allow_indexing',
                    ],
                ],
            ]);
    }

    public function test_config_theme_section_exposes_frontend_hooks(): void
    {
        $this->getJson('/api/v1/config')
            ->assertJsonStructure([
                'data' => [
                    'theme' => ['custom_css', 'frontend_template'],
                ],
            ])
            ->assertJsonPath('data.theme.frontend_template', 'default');
    }
}
