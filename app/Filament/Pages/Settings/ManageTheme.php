<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateThemeSettingsAction;
use App\Data\Settings\ThemeSettingsData;
use App\Settings\ThemeSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageTheme extends BaseSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Theme';

    protected static ?string $title = 'Theme settings';

    protected static ?string $slug = 'settings/theme';

    public function mount(): void
    {
        $this->form->fill(app(ThemeSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Colors')
                    ->description('CSS variables flow through `data-theme` on the public site. Hex values only.')
                    ->columns(2)
                    ->components([
                        ColorPicker::make('primary_color')
                            ->label('Primary')
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Brand primary color as a CSS hex value. Used for main buttons, links, and key UI elements site-wide.'),
                        ColorPicker::make('background_color')
                            ->label('Background')
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Page background color. Typically white or a very light neutral.'),
                        ColorPicker::make('text_color')
                            ->label('Body text')
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Default body text color. Should have sufficient contrast against the background.'),
                        ColorPicker::make('accent_color')
                            ->label('Accent (gold)')
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Accent color for highlights, badges, and secondary call-to-action elements.'),
                        ColorPicker::make('accent_secondary_color')
                            ->label('Accent (secondary)')
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Secondary accent color for additional UI highlights and decorative elements.'),
                    ]),
                Section::make('Typography')
                    ->description('Font family names. The CSS @font-face files are bundled separately under public/fonts/.')
                    ->columns(2)
                    ->components([
                        TextInput::make('font_display')
                            ->label('Display font')
                            ->required()
                            ->maxLength(100)
                            ->hintIcon(Heroicon::InformationCircle, 'Font family name used for headings and display text, e.g. "Playfair Display".'),
                        TextInput::make('font_body')
                            ->label('Body font')
                            ->required()
                            ->maxLength(100)
                            ->hintIcon(Heroicon::InformationCircle, 'Font family name used for body/paragraph text, e.g. "Inter" or "Source Sans Pro".'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = ThemeSettingsData::validateAndCreate($this->form->getState());
            app(UpdateThemeSettingsAction::class)->execute($data);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not save theme settings')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Theme settings saved')->success()->send();
    }
}
