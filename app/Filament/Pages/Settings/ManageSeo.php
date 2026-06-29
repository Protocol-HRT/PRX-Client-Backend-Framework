<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateSeoSettingsAction;
use App\Data\Settings\SeoSettingsData;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
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
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

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
                        TextInput::make('default_meta_title')
                            ->label('Default page title')
                            ->required()
                            ->maxLength(255)
                            ->hintIcon(Heroicon::InformationCircle, 'Default page title used when no page-specific title is set. Typically "Brand Name | Tagline".'),
                        Textarea::make('default_meta_description')
                            ->label('Default meta description')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->hintIcon(Heroicon::InformationCircle, 'Default meta description (150–160 chars) for search engines when no page-specific description is set.'),
                        FileUpload::make('og_image_path')
                            ->label('Default OG image')
                            ->hintIcon(Heroicon::InformationCircle, 'Default Open Graph image for social shares. 1200×630px recommended. Used when no page-specific OG image is set.')
                            ->image()
                            ->disk('public')
                            ->directory('seo')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->downloadable(),
                    ]),
                Section::make('Analytics')
                    ->columns(2)
                    ->components([
                        TextInput::make('google_analytics_id')
                            ->label('GA4 measurement ID')
                            ->placeholder('G-XXXXXXX')
                            ->maxLength(64)
                            ->hintIcon(Heroicon::InformationCircle, 'Google Analytics 4 measurement ID, e.g. G-XXXXXXXXXX. Leave blank to disable GA4.'),
                        TextInput::make('google_tag_manager_id')
                            ->label('GTM container ID')
                            ->placeholder('GTM-XXXXXXX')
                            ->maxLength(64)
                            ->hintIcon(Heroicon::InformationCircle, 'Google Tag Manager container ID, e.g. GTM-XXXXXXX. Leave blank to disable GTM.'),
                        TextInput::make('facebook_pixel_id')
                            ->label('Facebook Pixel ID')
                            ->maxLength(64)
                            ->hintIcon(Heroicon::InformationCircle, 'Facebook/Meta Pixel ID for conversion tracking. Leave blank to disable.'),
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
