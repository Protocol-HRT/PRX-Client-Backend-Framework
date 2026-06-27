<?php

namespace App\Filament\Resources\EmailSubscribers\Schemas;

use App\Enums\SubscriberStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmailSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('UUID')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('source')
                    ->required(),
                Select::make('status')
                    ->options(SubscriberStatus::class)
                    ->default('subscribed')
                    ->required(),
                Toggle::make('email_consent')
                    ->required(),
                Toggle::make('sms_consent')
                    ->required(),
                DateTimePicker::make('consent_given_at'),
                TextInput::make('utm_source'),
                TextInput::make('utm_medium'),
                TextInput::make('utm_campaign'),
                TextInput::make('utm_term'),
                TextInput::make('utm_content'),
                TextInput::make('referrer'),
                TextInput::make('landing_url')
                    ->url(),
                TextInput::make('ip_address'),
                TextInput::make('user_agent'),
                DateTimePicker::make('subscribed_at'),
                DateTimePicker::make('unsubscribed_at'),
                TextInput::make('meta'),
            ]);
    }
}
