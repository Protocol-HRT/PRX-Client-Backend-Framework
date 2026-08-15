<?php

namespace Tests\Feature\Cms;

use App\Enums\Cms\Region;
use App\Enums\Cms\RegionItemKind;
use App\Filament\Resources\Cms\Menus\MenuResource;
use App\Models\Cms\Menu;
use App\Models\Cms\RegionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke: the Menus list renders its "Mounted in" state — region labels for
 * mounted menus, a "Not mounted" warning for orphans that nothing renders.
 */
class MenusTableMountedInTest extends TestCase
{
    use RefreshDatabase;

    public function test_menus_list_shows_mounted_regions_and_flags_unmounted_menus(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');

        $mounted = Menu::create(['name' => 'Main Navigation', 'slug' => 'main-nav']);
        Menu::create(['name' => 'Orphan Menu', 'slug' => 'orphan']);

        RegionItem::create([
            'region' => Region::Header,
            'kind' => RegionItemKind::Menu,
            'menu_id' => $mounted->id,
            'enabled' => true,
        ]);

        $this->actingAs($user)
            ->get(MenuResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Header')
            ->assertSee('Not mounted');
    }
}
