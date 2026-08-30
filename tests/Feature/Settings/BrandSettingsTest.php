<?php

namespace Tests\Feature\Settings;

use App\Actions\Settings\UpdateBrandSettingsAction;
use App\Actions\Settings\UpdateSeoSettingsAction;
use App\Data\Settings\BrandSettingsData;
use App\Data\Settings\SeoSettingsData;
use App\Enums\Settings\OrganizationType;
use App\Filament\Pages\Settings\ManageBrand;
use App\Models\User;
use App\Settings\BrandSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_brand_page_saves_an_organization_type(): void
    {
        // THROUGH THE FORM, not the action — and that distinction is the whole
        // point of this test. Every other test on this class calls
        // `UpdateBrandSettingsAction` directly with a plain array, which is the
        // one path that never sees what the Filament Select actually puts in
        // state. The field was declared `->options(OrganizationType::class)`,
        // which handed back an ENUM INSTANCE, while the DTO property is a
        // `?string` carrying `#[Max(100)]` — so the rule called `mb_strlen()` on
        // an object and the page died with a TypeError. The whole page, not just
        // that field: an operator could not save their brand name either.
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        Livewire::test(ManageBrand::class)
            ->fillForm([
                'name' => 'Acme Wellness',
                'tagline' => 'Doctor-led protocols',
                'organization_type' => OrganizationType::MedicalClinic->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $brand = $this->app->make(BrandSettings::class);

        // A string, because that is what the property and the DTO both declare.
        $this->assertSame('MedicalClinic', $brand->organization_type);
        $this->assertIsString($brand->organization_type);
    }

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
