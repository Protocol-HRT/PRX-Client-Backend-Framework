<?php

namespace App\Filament\Resources\EmailSubscribers\Schemas;

use App\Enums\SubscriberStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmailSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('UUID')
                    ->required()
                    ->hintIcon(Heroicon::InformationCircle, 'Unique identifier for this subscriber record.'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->hintIcon(Heroicon::InformationCircle, 'Subscriber\'s email address. Must be unique.'),
                TextInput::make('first_name')
                    ->hintIcon(Heroicon::InformationCircle, 'Subscriber\'s first name. Used for personalised email salutations.'),
                TextInput::make('last_name')
                    ->hintIcon(Heroicon::InformationCircle, 'Subscriber\'s last name.'),
                TextInput::make('phone')
                    ->tel()
                    ->hintIcon(Heroicon::InformationCircle, 'Subscriber\'s phone number. Used for SMS consent tracking.'),
                TextInput::make('source')
                    ->required()
                    ->hintIcon(Heroicon::InformationCircle, 'Where this subscriber signed up, e.g. "footer-form", "checkout", "lead-capture".'),
                Select::make('status')
                    ->options(SubscriberStatus::class)
                    ->default('subscribed')
                    ->required()
                    ->hintIcon(Heroicon::InformationCircle, 'Current subscription status. Unsubscribed records are excluded from campaign sends.'),
                Toggle::make('email_consent')
                    ->required(),
                Toggle::make('sms_consent')
                    ->required(),
                DateTimePicker::make('consent_given_at')
                    ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the subscriber gave marketing consent.'),
                TextInput::make('utm_source')
                    ->hintIcon(Heroicon::InformationCircle, 'UTM source parameter from the URL at signup, e.g. google or newsletter.'),
                TextInput::make('utm_medium')
                    ->hintIcon(Heroicon::InformationCircle, 'UTM medium parameter, e.g. cpc or email.'),
                TextInput::make('utm_campaign')
                    ->hintIcon(Heroicon::InformationCircle, 'UTM campaign parameter identifying the marketing campaign.'),
                TextInput::make('utm_term')
                    ->hintIcon(Heroicon::InformationCircle, 'UTM term parameter, typically the paid keyword.'),
                TextInput::make('utm_content')
                    ->hintIcon(Heroicon::InformationCircle, 'UTM content parameter for A/B test differentiation.'),
                TextInput::make('referrer')
                    ->hintIcon(Heroicon::InformationCircle, 'HTTP referrer URL at the time of signup.'),
                TextInput::make('landing_url')
                    ->url()
                    ->hintIcon(Heroicon::InformationCircle, 'The page URL the subscriber was on when they signed up.'),
                TextInput::make('ip_address')
                    ->hintIcon(Heroicon::InformationCircle, 'IP address recorded at consent time for compliance purposes.'),
                TextInput::make('user_agent')
                    ->hintIcon(Heroicon::InformationCircle, 'Browser user-agent string recorded at consent time.'),
                DateTimePicker::make('subscribed_at')
                    ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the subscriber opted in.'),
                DateTimePicker::make('unsubscribed_at')
                    ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the subscriber opted out. Null if still subscribed.'),
                TextInput::make('meta')
                    ->hintIcon(Heroicon::InformationCircle, 'Additional metadata stored as a JSON string (read-only in most flows).'),
            ]);
    }
}
