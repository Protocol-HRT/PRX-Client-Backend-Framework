<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateCommunicationSettingsAction;
use App\Data\Settings\CommunicationSettingsData;
use App\Settings\CommunicationSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageCommunication extends BaseSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 55;

    protected static ?string $navigationLabel = 'Communication';

    protected static ?string $title = 'Communication settings';

    protected static ?string $slug = 'settings/communication';

    public function mount(): void
    {
        $this->form->fill(app(CommunicationSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Twilio credentials')
                    ->description('Credentials from your Twilio Console (console.twilio.com). Stored encrypted. Used for SMS, Voice, and proxying telehealth video access tokens.')
                    ->columns(2)
                    ->components([
                        TextInput::make('twilio_account_sid')
                            ->label('Account SID')
                            ->hintIcon(Heroicon::InformationCircle, 'Twilio Account SID — starts with "AC". Found on the Twilio Console dashboard. Stored encrypted.')
                            ->placeholder('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                            ->maxLength(64),
                        TextInput::make('twilio_auth_token')
                            ->label('Auth Token')
                            ->hintIcon(Heroicon::InformationCircle, 'Twilio Auth Token. Found below the Account SID on the Console dashboard. Stored encrypted — treat as a password.')
                            ->password()
                            ->revealable()
                            ->maxLength(64),
                        TextInput::make('twilio_from_number')
                            ->label('From number')
                            ->hintIcon(Heroicon::InformationCircle, 'The Twilio phone number or messaging service SID to send from. E.164 format: +15551234567.')
                            ->placeholder('+15551234567')
                            ->maxLength(20)
                            ->columnSpanFull(),
                    ]),

                Section::make('SMS')
                    ->columns(1)
                    ->components([
                        Toggle::make('sms_enabled')
                            ->label('Enable SMS')
                            ->helperText('Enables lead confirmation texts, appointment reminders, and order status alerts.')
                            ->live(),
                        Textarea::make('sms_opt_in_message')
                            ->label('Double opt-in message')
                            ->hintIcon(Heroicon::InformationCircle, 'Message sent when a patient opts in to SMS. Required for TCPA compliance. Max 500 chars. Leave blank to skip double opt-in.')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Reply YES to confirm you\'d like to receive text updates from us. Msg & data rates may apply. Reply STOP to unsubscribe.')
                            ->visible(fn (Get $get): bool => (bool) $get('sms_enabled')),
                    ]),

                Section::make('Voice')
                    ->components([
                        Toggle::make('voice_enabled')
                            ->label('Enable Voice')
                            ->helperText('Enables outbound voice calls for appointment confirmations and provider callbacks.'),
                    ]),

                Section::make('Video (telehealth consults)')
                    ->description('Patient-facing video consult functionality. Requires Prescribe-Rx to expose the patient video access token endpoint (POST /telehealth/encounters/{id}/video/token). The backend proxies this token to the patient portal — no direct Twilio Video SDK credentials are needed here unless you go fully in-house.')
                    ->components([
                        Toggle::make('video_enabled')
                            ->label('Enable video consults')
                            ->helperText('When enabled, the patient portal shows a "Join Video Consult" button for active encounters. PRX endpoint must be live before enabling.'),
                    ]),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = CommunicationSettingsData::validateAndCreate($this->form->getState());
            app(UpdateCommunicationSettingsAction::class)->execute($data);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not save communication settings')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Communication settings saved')
            ->success()
            ->send();
    }
}
