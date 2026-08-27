<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds a default operator role matrix on top of Shield's generated
 * permissions ("Verb:Model" names). super_admin is NOT defined here — it is
 * created/synced by shield:super-admin (see AdminUserSeeder).
 *
 * Role names and grants are a starting point; installs tune them in the
 * admin (Roles resource). Permission names are filtered against what Shield
 * actually generated, so this never creates dangling permissions — run it
 * AFTER shield:generate (DatabaseSeeder orders this correctly).
 *
 * Idempotent: syncPermissions replaces each role's grants on re-run.
 */
class BaseRolesSeeder extends Seeder
{
    private const EDIT_VERBS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny', 'Reorder', 'Replicate'];

    private const READ_VERBS = ['ViewAny', 'View'];

    private const CMS_MODELS = ['Page', 'Menu', 'GlobalSection', 'FlexibleSectionType', 'RegionItem', 'Media'];

    private const BLOG_MODELS = ['BlogPost', 'BlogCategory'];

    private const CONTENT_MODELS = ['FaqItem', 'FaqCategory', 'Profile', 'Compound'];

    private const CATALOG_MODELS = ['Product', 'Package', 'Plan', 'Category', 'Tag'];

    private const CRM_MODELS = ['Lead', 'EmailSubscriber', 'Patient', 'Encounter', 'Order'];

    public function run(): void
    {
        $existing = Permission::pluck('name');

        if ($existing->isEmpty()) {
            $this->command?->warn('No permissions found — run shield:generate first (php artisan db:seed runs AdminUserSeeder which does this).');

            return;
        }

        $roles = [
            // Full operational access except managing roles themselves.
            'admin' => $existing->reject(
                fn (string $name): bool => str_ends_with($name, ':Role') && ! in_array(explode(':', $name)[0], self::READ_VERBS, true)
            )->all(),

            'content_editor' => [
                ...$this->grant(self::EDIT_VERBS, [...self::CMS_MODELS, ...self::BLOG_MODELS, ...self::CONTENT_MODELS]),
            ],

            'catalog_manager' => [
                ...$this->grant(self::EDIT_VERBS, self::CATALOG_MODELS),
                ...$this->grant(['ViewAny', 'View', 'Create', 'Update'], ['Media']),
            ],

            'support' => [
                ...$this->grant(self::READ_VERBS, self::CRM_MODELS),
                ...$this->grant(['Update'], ['Lead', 'Order']),
                'View:OverviewStatsWidget',
                'View:LeadsChartWidget',
                'View:RecentLeadsWidget',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($existing->intersect($permissions)->all());
            $this->command?->info(sprintf('Role %s: %d permissions', $name, $role->permissions()->count()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $verbs
     * @param  list<string>  $models
     * @return list<string>
     */
    private function grant(array $verbs, array $models): array
    {
        $names = [];

        foreach ($models as $model) {
            foreach ($verbs as $verb) {
                $names[] = "{$verb}:{$model}";
            }
        }

        return $names;
    }
}
