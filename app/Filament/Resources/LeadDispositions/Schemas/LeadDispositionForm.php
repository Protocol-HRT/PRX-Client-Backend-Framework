<?php

namespace App\Filament\Resources\LeadDispositions\Schemas;

use App\Models\LeadDisposition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class LeadDispositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Disposition')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $get, $set, ?LeadDisposition $record): void {
                                // Only ever derive a slug for a NEW row. Editing
                                // the label of an existing disposition must not
                                // move its slug — leads point at that string.
                                if ($record === null && blank($get('slug'))) {
                                    $set('slug', Str::snake(Str::lower($state ?? '')));
                                }
                            })
                            ->hintIcon(Heroicon::InformationCircle, 'What operators see. Safe to change at any time.'),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            // Frozen once anything references it. The model
                            // throws too; this is the half the operator can see
                            // before they hit save.
                            ->disabled(fn (?LeadDisposition $record): bool => $record !== null
                                && ($record->is_system || LeadDisposition::leadsUsing($record->slug) > 0))
                            ->helperText(fn (?LeadDisposition $record): ?string => match (true) {
                                $record?->is_system === true => 'Written by application code — this slug cannot change.',
                                $record !== null && LeadDisposition::leadsUsing($record->slug) > 0 => 'Leads reference this slug, so it is locked. Create a new disposition and move them instead.',
                                default => 'The stable key stored on each lead, and what workflows match on. Choose carefully — it is locked once leads use it.',
                            }),

                        TextInput::make('description')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->hintIcon(Heroicon::InformationCircle, 'Optional note for whoever works this stage.'),

                        Select::make('color')
                            ->options([
                                'gray' => 'Grey',
                                'info' => 'Blue',
                                'success' => 'Green',
                                'warning' => 'Amber',
                                'danger' => 'Red',
                                'primary' => 'Primary',
                            ])
                            ->default('gray')
                            ->required()
                            ->native(false)
                            ->hintIcon(Heroicon::InformationCircle, 'Badge colour in the leads table.'),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Lower numbers appear first.'),
                    ]),

                Section::make('Behaviour')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_default')
                            ->label('Starting disposition')
                            ->helperText('New leads begin here. Turning this on turns it off everywhere else.'),

                        Toggle::make('is_active')
                            ->label('Selectable')
                            ->default(true)
                            ->helperText('Off hides it from the pickers without touching leads already on it.'),

                        Toggle::make('is_system')
                            ->label('Written by application code')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Set by the system. These cannot be deleted or re-slugged.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
