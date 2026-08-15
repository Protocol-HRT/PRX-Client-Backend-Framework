<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ApiDocs;
use App\Filament\Pages\Settings\ManageBilling;
use App\Filament\Pages\Settings\ManageBrand;
use App\Filament\Pages\Settings\ManageCommunication;
use App\Filament\Pages\Settings\ManageContact;
use App\Filament\Pages\Settings\ManageIntegrations;
use App\Filament\Pages\Settings\ManageLlm;
use App\Filament\Pages\Settings\ManageSeo;
use App\Filament\Pages\Settings\ManageTheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test: every custom Filament page renders for a super admin.
 * Catches fatals the rest of the suite can't see — missing component
 * imports, broken form schemas, bad view references.
 */
class SettingsPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function pageProvider(): array
    {
        return [
            'brand' => [ManageBrand::class],
            'theme' => [ManageTheme::class],
            'contact' => [ManageContact::class],
            'seo' => [ManageSeo::class],
            'billing' => [ManageBilling::class],
            'communication' => [ManageCommunication::class],
            'integrations' => [ManageIntegrations::class],
            'llm' => [ManageLlm::class],
            'api-docs' => [ApiDocs::class],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_page_renders_for_super_admin(string $pageClass): void
    {
        Role::findOrCreate('super_admin', 'web');

        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get($pageClass::getUrl())
            ->assertOk();
    }

    public function test_settings_pages_are_denied_without_permission(): void
    {
        Role::findOrCreate('editor', 'web');

        // refresh() loads DB-default columns (is_active) the factory instance lacks
        $user = User::factory()->create()->refresh();
        $user->assignRole('editor');

        $this->actingAs($user)
            ->get(ManageBrand::getUrl())
            ->assertForbidden();
    }
}
