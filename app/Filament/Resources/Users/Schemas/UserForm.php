<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('User')
                    ->vertical()
                    ->persistTabInQueryString('user-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Admin user\'s display name shown in the panel and activity logs.'),
                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Login email address. Must be unique across all admin users.'),

                                Section::make('Password')
                                    ->description('On create: set the temp password the user will receive. On edit: leave blank to keep the current password.')
                                    ->columnSpanFull()
                                    ->columns(1)
                                    ->components([
                                        TextInput::make('password')
                                            ->password()
                                            ->revealable()
                                            ->minLength(8)
                                            ->maxLength(255)
                                            ->hintIcon(Heroicon::InformationCircle, 'Min 8 chars, mixed case and numbers required. Leave blank on edit to keep the existing password.')
                                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                                            ->dehydrated(fn (?string $state) => filled($state))
                                            ->required(fn (string $operation) => $operation === 'create')
                                            ->rule(Password::min(8)->mixedCase()->numbers()),
                                    ]),
                            ]),

                        // ── Roles & access ────────────────────────────
                        Tab::make('Roles & access')
                            ->icon(Heroicon::ShieldCheck)
                            ->columns(2)
                            ->schema([
                                Select::make('roles')
                                    ->label('Roles')
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id'))
                                    ->preload()
                                    ->searchable()
                                    ->hintIcon(Heroicon::InformationCircle, 'Assigns Spatie permission roles. Controls which admin panel resources this user can access.')
                                    ->helperText('Roles map to Filament Shield permissions. super_admin bypasses all permission checks.')
                                    ->columnSpanFull(),
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Disable to revoke panel access without deleting the user.'),
                            ]),

                        // ── Status ────────────────────────────────────
                        Tab::make('Status')
                            ->icon(Heroicon::Clock)
                            ->columns(3)
                            ->schema([
                                DateTimePicker::make('email_verified_at')
                                    ->label('Email verified at')
                                    ->hintIcon(Heroicon::InformationCircle, 'Timestamp when email was verified. Set manually if bypassing the verification email.'),
                                DateTimePicker::make('last_login_at')
                                    ->label('Last login at')
                                    ->disabled()
                                    ->dehydrated(false),
                                DateTimePicker::make('invited_at')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ]);
    }
}
