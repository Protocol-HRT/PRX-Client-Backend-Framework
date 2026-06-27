<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateSeoSettingsAction;
use App\Data\Settings\SeoSettingsData;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageSeo extends BaseSettingsPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'SEO & Analytics';

    protected static ?string $title = 'SEO & analytics';

    protected static ?string $slug = 'settings/seo';

    public function mount(): void
    {
        $this->form->fill(app(SeoSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Meta defaults')
                    ->description('Used as fallbacks when a page does not declare its own meta.')
                    ->components([
                        TextInput::make('default_meta_title')->label('Default page title')->required()->maxLength(255),
                        Textarea::make('default_meta_description')->label('Default meta description')->required()->rows(3)->maxLength(500),
                        TextInput::make('og_image_path')->label('Default OG image path')->maxLength(2048),
                    ]),
                Section::make('Analytics')
                    ->columns(2)
                    ->components([
                        TextInput::make('google_analytics_id')->label('GA4 measurement ID')->placeholder('G-XXXXXXX')->maxLength(64),
                        TextInput::make('google_tag_manager_id')->label('GTM container ID')->placeholder('GTM-XXXXXXX')->maxLength(64),
                        TextInput::make('facebook_pixel_id')->label('Facebook Pixel ID')->maxLength(64),
                    ]),
                Section::make('Indexing')
                    ->components([
                        Toggle::make('allow_indexing')
                            ->label('Allow search-engine indexing')
                            ->helperText('Disable in staging or pre-launch environments. When off, the public layout emits a noindex meta tag.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = SeoSettingsData::validateAndCreate($this->form->getState());
            app(UpdateSeoSettingsAction::class)->execute($data);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not save SEO settings')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('SEO settings saved')->success()->send();
    }
}
