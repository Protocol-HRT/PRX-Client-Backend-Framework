<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    ]),

                Section::make('Password')
                    ->description('On create: set the temp password the user will receive. On edit: leave blank to keep the current password.')
                    ->columns(1)
                    ->components([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->rule(\Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()),
                    ]),

                Section::make('Roles & access')
                    ->description('Roles map to Filament Shield permissions. super_admin bypasses all permission checks.')
                    ->columns(2)
                    ->components([
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id'))
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Disable to revoke panel access without deleting the user.'),
                    ]),

                Section::make('Status')
                    ->collapsed()
                    ->columns(3)
                    ->components([
                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at'),
                        DateTimePicker::make('last_login_at')
                            ->label('Last login at')
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('invited_at')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
