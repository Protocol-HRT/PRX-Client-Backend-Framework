<?php

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelSettings\Settings;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every declared settings property has a row behind it.
 *
 * ─── The bug this exists to prevent, which shipped and blocked an operator ──
 *
 * `IntegrationSettings::$prescribe_rx_encounter_type_id` was added to the class,
 * to the Filament form and to the checkout action — with no settings migration.
 * The failure mode is the nasty one: the mapper backfills a missing property
 * from the class default when LOADING, so the page opens, renders the field, and
 * looks entirely healthy. Only on SAVE does it throw
 *
 *   Tried saving settings '…', and the following properties were missing: …
 *
 * which reads like a form validation problem and is actually a missing database
 * row — and it fails the WHOLE page, not just that field, so an operator cannot
 * save anything on it until somebody notices.
 *
 * ─── Why a test rather than care ───────────────────────────────────────
 *
 * Adding a property is a one-line edit in a file that has nothing to do with
 * migrations, and the app keeps working locally until the first save. Every
 * settings class in this codebase is one forgotten migration away from the same
 * outage, so the check belongs across all of them at once rather than in
 * whichever module last got bitten.
 */
class SettingsPropertiesAreMigratedTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_settings_property_has_a_stored_row(): void
    {
        $missing = [];

        foreach ($this->settingsClasses() as $class) {
            $group = $class::group();
            $stored = DB::table('settings')->where('group', $group)->pluck('name')->all();

            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                if (! in_array($property->getName(), $stored, true)) {
                    $missing[] = "{$group}.{$property->getName()}";
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            'These settings properties are declared on their class but have no row in the '
            .'settings table, so the page they belong to will load fine and then fail every '
            .'save: %s. Add a settings migration for each.',
            implode(', ', $missing),
        ));
    }

    /** @return list<class-string<Settings>> */
    private function settingsClasses(): array
    {
        $classes = [];

        foreach (Finder::create()->files()->in(app_path('Settings'))->name('*.php') as $file) {
            $class = 'App\\Settings\\'.$file->getFilenameWithoutExtension();

            if (class_exists($class) && is_subclass_of($class, Settings::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
