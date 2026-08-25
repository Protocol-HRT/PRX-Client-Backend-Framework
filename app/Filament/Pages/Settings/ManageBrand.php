<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateBrandSettingsAction;
use App\Data\Settings\BrandSettingsData;
use App\Enums\Settings\OrganizationType;
use App\Settings\BrandSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageBrand extends BaseSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Brand';

    protected static ?string $title = 'Brand settings';

    protected static ?string $slug = 'settings/brand';

    public function mount(): void
    {
        $this->form->fill(app(BrandSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Identity')
                    ->description('Core brand name and positioning shown site-wide.')
                    ->columns(1)
                    ->components([
                        TextInput::make('name')
                            ->label('Brand name')
                            ->hintIcon(Heroicon::InformationCircle, 'Public-facing brand name shown in the browser title, email signatures, and anywhere the company name appears.')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('tagline')
                            ->label('Tagline')
                            ->hintIcon(Heroicon::InformationCircle, 'Short marketing tagline shown in headers and used as a fallback meta description.')
                            ->maxLength(255),
                    ]),

                Section::make('Logo & Mark')
                    ->description('Upload logo files directly. The React frontend reads these URLs from /api/v1/config and injects them into the layout.')
                    ->columns(2)
                    ->components([
                        FileUpload::make('logo_path')
                            ->label('Logo (default / fallback)')
                            ->hintIcon(Heroicon::InformationCircle, 'Universal fallback logo used when no dark/light variant is set. SVG or transparent PNG recommended.')
                            ->image()
                            ->disk('public')
                            ->directory('brand/logos')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/webp', 'image/jpeg'])
                            ->maxSize(2048)
                            ->downloadable()
                            ->columnSpanFull(),

                        FileUpload::make('logo_dark_path')
                            ->label('Logo — dark variant')
                            ->hintIcon(Heroicon::InformationCircle, 'Logo used on dark backgrounds or in dark-mode. White/light colored logo. Optional.')
                            ->image()
                            ->disk('public')
                            ->directory('brand/logos')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->downloadable(),

                        FileUpload::make('logo_light_path')
                            ->label('Logo — light variant')
                            ->hintIcon(Heroicon::InformationCircle, 'Logo used on white/light backgrounds. Dark colored logo. Optional.')
                            ->image()
                            ->disk('public')
                            ->directory('brand/logos')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->downloadable(),

                        FileUpload::make('favicon_path')
                            ->label('Favicon')
                            ->hintIcon(Heroicon::InformationCircle, 'Favicon shown in browser tabs and bookmarks. ICO, 32×32 PNG, or SVG.')
                            ->image()
                            ->disk('public')
                            ->directory('brand/favicons')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'])
                            ->maxSize(512),
                    ]),

                Section::make('Hero Image')
                    ->description('Optional site-wide fallback hero image used in OG previews and any hero block that has no custom image.')
                    ->components([
                        FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->hintIcon(Heroicon::InformationCircle, 'Fallback hero image. 1920×1080 or wider recommended. Used when no page-specific hero is set.')
                            ->image()
                            ->disk('public')
                            ->directory('brand/hero')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/webp', 'image/png'])
                            ->maxSize(8192)
                            ->downloadable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Announcement bar')
                    ->description('Optional bar shown at the top of every page. Inject into the React layout via /api/v1/config → brand.announcement.')
                    ->columns(1)
                    ->components([
                        Toggle::make('announcement_enabled')
                            ->label('Enable announcement bar')
                            ->default(false)
                            ->live(),
                        TextInput::make('announcement_emphasis')
                            ->label('Emphasis text')
                            ->hintIcon(Heroicon::InformationCircle, 'Short bold phrase at the start of the bar, e.g. "Now Available" or "Licensed in All 50 States".')
                            ->maxLength(120)
                            ->visible(fn (Get $get): bool => (bool) $get('announcement_enabled')),
                        Textarea::make('announcement_text')
                            ->label('Announcement body')
                            ->hintIcon(Heroicon::InformationCircle, 'Body text shown after the emphasis. Plain text only, no HTML. Max 500 chars.')
                            ->rows(2)
                            ->maxLength(500)
                            ->visible(fn (Get $get): bool => (bool) $get('announcement_enabled')),
                    ]),

                Section::make('Site & Schema')
                    ->description('Used in JSON-LD structured data for search engine rich results.')
                    ->columns(2)
                    ->components([
                        TextInput::make('site_url')
                            ->label('Canonical site URL')
                            ->hintIcon(Heroicon::InformationCircle, 'Full URL of the frontend site, e.g. https://example.com. Used in Organization and WebSite JSON-LD schemas.')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Select::make('organization_type')
                            ->label('Organization type')
                            ->hintIcon(Heroicon::InformationCircle, 'schema.org @type for the Organization JSON-LD block. Choose the type that best describes this business.')
                            ->options(OrganizationType::class)
                            ->placeholder('Select type…'),
                    ]),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = BrandSettingsData::validateAndCreate($this->form->getState());
            app(UpdateBrandSettingsAction::class)->execute($data);
        } catch (ValidationException $e) {
            // Rethrow ahead of the catch-all: a ValidationException knows which
            // field it belongs to, and converting it into a page-level
            // notification throws that away, leaving the operator hunting for
            // the control at fault. Every settings page had this; found via the
            // palette rule on ManageTheme, which looked inert because its
            // message was being swallowed here.
            throw $e;
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not save brand settings')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Brand settings saved')
            ->success()
            ->send();
    }
}
