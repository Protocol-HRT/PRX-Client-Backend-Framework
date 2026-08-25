<?php

namespace Tests\Feature\Settings;

use App\Actions\Settings\UpdateBillingSettingsAction;
use App\Actions\Settings\UpdateBrandSettingsAction;
use App\Actions\Settings\UpdateCommunicationSettingsAction;
use App\Actions\Settings\UpdateContactSettingsAction;
use App\Actions\Settings\UpdateLlmSettingsAction;
use App\Actions\Settings\UpdateSeoSettingsAction;
use App\Actions\Settings\UpdateThemeSettingsAction;
use App\Data\Settings\BillingSettingsData;
use App\Data\Settings\BrandSettingsData;
use App\Data\Settings\CommunicationSettingsData;
use App\Data\Settings\ContactSettingsData;
use App\Data\Settings\LlmSettingsData;
use App\Data\Settings\SeoSettingsData;
use App\Data\Settings\ThemeSettingsData;
use App\Jobs\Cms\RevalidateFrontendJob;
use App\Services\Cms\ConfigCache;
use App\Services\Cms\FrontendRevalidator;
use App\Settings\BillingSettings;
use App\Settings\BrandSettings;
use App\Settings\CommunicationSettings;
use App\Settings\ContactSettings;
use App\Settings\LlmSettings;
use App\Settings\SeoSettings;
use App\Settings\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Spatie\LaravelData\Data;
use Spatie\LaravelSettings\Settings;
use Tests\TestCase;

class ConfigCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, class-string, class-string}>
     */
    public static function settingsActionProvider(): array
    {
        return [
            'billing' => [UpdateBillingSettingsAction::class, BillingSettingsData::class, BillingSettings::class],
            'brand' => [UpdateBrandSettingsAction::class, BrandSettingsData::class, BrandSettings::class],
            'communication' => [UpdateCommunicationSettingsAction::class, CommunicationSettingsData::class, CommunicationSettings::class],
            'contact' => [UpdateContactSettingsAction::class, ContactSettingsData::class, ContactSettings::class],
            'llm' => [UpdateLlmSettingsAction::class, LlmSettingsData::class, LlmSettings::class],
            'seo' => [UpdateSeoSettingsAction::class, SeoSettingsData::class, SeoSettings::class],
            'theme' => [UpdateThemeSettingsAction::class, ThemeSettingsData::class, ThemeSettings::class],
        ];
    }

    /**
     * @param  class-string  $actionClass
     * @param  class-string<Data>  $dataClass
     * @param  class-string<Settings>  $settingsClass
     */
    #[DataProvider('settingsActionProvider')]
    public function test_saving_settings_clears_the_public_config_cache(string $actionClass, string $dataClass, string $settingsClass): void
    {
        Cache::put('api.v1.config', ['stale' => true], 300);

        // Round-trip the seeded settings values through the action; the save
        // itself is what must evict the public config bundle.
        $payload = $dataClass::from($this->app->make($settingsClass)->toArray());
        $this->app->make($actionClass)->execute($payload);

        $this->assertFalse(Cache::has('api.v1.config'), "{$actionClass} did not clear the api.v1.config cache");
    }

    /**
     * The half that was missing: clearing this app's own cache published
     * nothing, because the decoupled frontend holds its own copy for
     * API_REVALIDATE seconds. Every settings save must purge both.
     *
     * @param  class-string  $actionClass
     * @param  class-string<Data>  $dataClass
     * @param  class-string<Settings>  $settingsClass
     */
    #[DataProvider('settingsActionProvider')]
    public function test_saving_settings_revalidates_the_frontend_config_tag(string $actionClass, string $dataClass, string $settingsClass): void
    {
        // The revalidator no-ops unless a frontend is configured, which is the
        // correct behaviour for an install that has none — so configure one.
        config()->set('cms.frontend.revalidate_url', 'https://frontend.test/api/revalidate');
        config()->set('cms.frontend.revalidate_secret', 'test-secret');

        Queue::fake();

        $payload = $dataClass::from($this->app->make($settingsClass)->toArray());
        $this->app->make($actionClass)->execute($payload);

        // Tags accumulate and normally flush on terminate; a test has no
        // request lifecycle, so flush explicitly.
        $this->app->make(FrontendRevalidator::class)->flush();

        Queue::assertPushed(
            RevalidateFrontendJob::class,
            function (RevalidateFrontendJob $job): bool {
                $tags = new ReflectionProperty($job, 'tags');
                $tags->setAccessible(true);

                return in_array(ConfigCache::TAG, $tags->getValue($job), true);
            },
            "{$actionClass} did not revalidate the frontend's config tag"
        );
    }

    public function test_no_frontend_configured_is_a_no_op_rather_than_a_failure(): void
    {
        config()->set('cms.frontend.revalidate_url', null);
        config()->set('cms.frontend.revalidate_secret', null);

        Queue::fake();

        ConfigCache::invalidate();
        $this->app->make(FrontendRevalidator::class)->flush();

        Queue::assertNothingPushed();
    }
}
