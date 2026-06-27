<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateBrandSettingsAction;
use App\Data\Settings\BrandSettingsData;
use App\Settings\BrandSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageBrand extends BaseSettingsPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSparkles;

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
                TextInput::make('name')
                    ->label('Brand name')
                    ->helperText('Shown in the title bar, mail signatures, and any place that asks for the company name.')
                    ->required()
                    ->maxLength(255),
                TextInput::make('tagline')
                    ->label('Tagline')
                    ->helperText('One-line positioning statement, used as a fallback meta description and on the layout.')
                    ->required()
                    ->maxLength(255),
                TextInput::make('logo_path')
                    ->label('Logo path')
                    ->helperText('Public path to the logo asset, e.g. /images/logo.svg')
                    ->required()
                    ->maxLength(2048),
                TextInput::make('favicon_path')
                    ->label('Favicon path')
                    ->helperText('Public path to the favicon, e.g. /images/logo.svg')
                    ->required()
                    ->maxLength(2048),
                TextInput::make('hero_image_path')
                    ->label('Hero image path')
                    ->helperText('Optional fallback hero image for OG previews and the home hero block.')
                    ->maxLength(2048),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = BrandSettingsData::validateAndCreate($this->form->getState());
            app(UpdateBrandSettingsAction::class)->execute($data);
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
