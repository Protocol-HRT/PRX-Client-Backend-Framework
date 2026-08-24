<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Promotes `theme.text_classes` into `theme.palette` — the install's one
 * named colour vocabulary.
 *
 * The two carry the SAME shape (a list of {name, color}), which is why this
 * is a copy and not a transform. `text_classes` only ever generated
 * `.tx-{name}` text utilities; the palette additionally backs the section
 * colour knobs (LayoutFields), so an operator names a colour once and can use
 * it as copy colour, section background or section text.
 *
 * `text_classes` is deliberately LEFT IN PLACE rather than deleted:
 * prx-backend ships to more than one frontend and `/config` still emits it,
 * derived from the palette, so a consumer that has not been updated keeps
 * working unchanged. UpdateThemeSettingsAction keeps the two in sync.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('theme.palette', $this->existingTextClasses());
    }

    public function down(): void
    {
        $this->migrator->delete('theme.palette');
    }

    /**
     * Read the current value directly: the migrator has no "get" and the
     * settings class already declares $palette, so instantiating it here
     * would fail on the very property this migration is adding.
     *
     * @return array<int, array{name: string, color: string}>
     */
    private function existingTextClasses(): array
    {
        $raw = DB::table('settings')
            ->where('group', 'theme')
            ->where('name', 'text_classes')
            ->value('payload');

        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_values($decoded) : [];
    }
};
