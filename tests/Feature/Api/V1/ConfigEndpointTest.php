<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Settings\UpdateThemeSettingsAction;
use App\Data\Settings\ThemeSettingsData;
use App\Settings\ThemeSettings;
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

    public function test_config_checkout_section_exposes_path_and_upsell_knobs(): void
    {
        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.checkout.path', 'prx')
            ->assertJsonPath('data.checkout.upsells.enabled', true)
            ->assertJsonPath('data.checkout.upsells.limit', 4);
    }

    public function test_config_theme_section_exposes_frontend_hooks(): void
    {
        $this->getJson('/api/v1/config')
            ->assertJsonStructure([
                'data' => [
                    'theme' => ['custom_css', 'frontend_template', 'product_zoom_enabled'],
                ],
            ])
            ->assertJsonPath('data.theme.frontend_template', 'default')
            // Pinned as a VALUE, not just a key: the frontend gates a library
            // download on this, so a key that silently stopped being emitted
            // would read as `false` there and look like a working default.
            ->assertJsonPath('data.theme.product_zoom_enabled', false);
    }

    /**
     * The palette is the vocabulary section colour knobs resolve against, so
     * a frontend that cannot read it renders every `background_color: sand`
     * as nothing. `text_classes` ships the same rows for frontends built
     * before the palette existed and must stay in lockstep.
     */
    public function test_config_theme_section_exposes_the_colour_palette(): void
    {
        $palette = [
            ['name' => 'sand', 'color' => '#e8ded1'],
            ['name' => 'ink', 'color' => '#151415'],
        ];

        app(UpdateThemeSettingsAction::class)->execute(
            ThemeSettingsData::from([
                ...app(ThemeSettings::class)->toArray(),
                'palette' => $palette,
            ])
        );

        $this->getJson('/api/v1/config')
            ->assertJsonPath('data.theme.palette', $palette)
            ->assertJsonPath('data.theme.text_classes', $palette);
    }
}
