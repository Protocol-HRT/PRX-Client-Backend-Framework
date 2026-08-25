<?php

namespace App\Rules;

use App\Cms\Support\PaletteUsage;
use App\Settings\ThemeSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses a palette edit that drops a colour some section still stores.
 *
 * A CLASS, NOT A CLOSURE, deliberately. Filament's `->rule()` hands a bare
 * Closure straight to Laravel as a closure rule, so the
 * `fn () => fn ($attribute, $value, $fail) => …` wrapper this started as was
 * invoked with the three validation arguments, ignored them, and returned an
 * inner closure Laravel discarded — a rule that always passed while looking
 * exactly like one that worked. A ValidationRule object has one unambiguous
 * entry point and cannot fail that way.
 *
 * The action guards this again at save time; see UpdateThemeSettingsAction.
 * This half exists so the error lands on the field the operator touched
 * instead of arriving as a page-level notification.
 */
class PaletteColorNotInUse implements ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Removal is "gone from the submitted list", which makes a RENAME a
        // removal — sections store the colour's name, not an id, so renaming
        // breaks them exactly as deleting does.
        $removed = array_diff(
            array_column(app(ThemeSettings::class)->palette ?? [], 'name'),
            array_column(is_array($value) ? $value : [], 'name'),
        );

        if ($removed === []) {
            return;
        }

        $usages = PaletteUsage::find(array_values($removed));

        if ($usages !== []) {
            $fail(PaletteUsage::message($usages));
        }
    }
}
