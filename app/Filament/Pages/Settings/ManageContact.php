<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateContactSettingsAction;
use App\Data\Settings\ContactSettingsData;
use App\Settings\ContactSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageContact extends BaseSettingsPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Contact';

    protected static ?string $title = 'Contact info';

    protected static ?string $slug = 'settings/contact';

    public function mount(): void
    {
        $this->form->fill(app(ContactSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reach us')
                    ->columns(2)
                    ->components([
                        TextInput::make('support_email')->label('Support email')->email()->maxLength(255),
                        TextInput::make('sales_email')->label('Sales email')->email()->maxLength(255),
                        TextInput::make('phone')->label('Phone')->tel()->maxLength(64),
                        Textarea::make('business_hours')->label('Business hours')->rows(2)->maxLength(255),
                    ]),
                Section::make('Mailing address')
                    ->columns(2)
                    ->components([
                        TextInput::make('address_line_1')->label('Address line 1')->maxLength(255),
                        TextInput::make('address_line_2')->label('Address line 2')->maxLength(255),
                        TextInput::make('city')->maxLength(128),
                        TextInput::make('state')->maxLength(128),
                        TextInput::make('postal_code')->label('Postal / ZIP code')->maxLength(32),
                        TextInput::make('country')->label('Country (ISO 2-letter)')->maxLength(2)->default('US'),
                    ]),
                Section::make('Social')
                    ->columns(2)
                    ->components([
                        TextInput::make('instagram_url')->label('Instagram URL')->url()->maxLength(2048),
                        TextInput::make('twitter_url')->label('X / Twitter URL')->url()->maxLength(2048),
                        TextInput::make('facebook_url')->label('Facebook URL')->url()->maxLength(2048),
                        TextInput::make('linkedin_url')->label('LinkedIn URL')->url()->maxLength(2048),
                        TextInput::make('tiktok_url')->label('TikTok URL')->url()->maxLength(2048),
                        TextInput::make('youtube_url')->label('YouTube URL')->url()->maxLength(2048),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = ContactSettingsData::validateAndCreate($this->form->getState());
            app(UpdateContactSettingsAction::class)->execute($data);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not save contact settings')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Contact info saved')->success()->send();
    }
}
