<?php

namespace App\Filament\Resources\ApiClients\Schemas;

use App\Enums\TokenAbility;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApiClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(2)
                            ->nullable()
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive clients are rejected at the Origin check, even with a valid token.'),
                    ]),

                Section::make('Allowed origins')
                    ->description('CORS origins permitted to send this client\'s tokens. Leave empty to allow any origin.')
                    ->schema([
                        TagsInput::make('allowed_origins')
                            ->label('Origins')
                            ->placeholder('https://example.com')
                            ->helperText('Add each origin exactly as the browser sends it — scheme + host + optional port (e.g. https://app.example.com).'),
                    ]),

                Section::make('Default token abilities')
                    ->description('Abilities pre-selected when an admin issues a new token for this client. Can be overridden per token.')
                    ->schema([
                        CheckboxList::make('default_abilities')
                            ->label('Abilities')
                            ->options(TokenAbility::options())
                            ->required(),
                    ]),
            ]);
    }
}
