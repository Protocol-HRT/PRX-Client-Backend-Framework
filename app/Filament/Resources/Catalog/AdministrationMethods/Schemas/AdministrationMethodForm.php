<?php

namespace App\Filament\Resources\Catalog\AdministrationMethods\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class AdministrationMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Administration method')
                    ->vertical()
                    ->persistTabInQueryString('administration-method-tab')
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
                                    ->live(onBlur: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'How the product is taken, e.g. Subcutaneous injection or Oral.')
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->helperText('Leave blank to auto-generate from the name.')
                                    ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier used in API responses and frontend routes.'),
                                TextInput::make('abbreviation')
                                    ->maxLength(16)
                                    ->hintIcon(Heroicon::InformationCircle, 'Short clinical shorthand, e.g. SubQ, IM or PO. Shown on compact product specs.'),
                                Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Controls display order in lists. Lower numbers appear first.'),
                                Toggle::make('is_active')
                                    ->default(true),
                            ]),

                        // ── Integrations ──────────────────────────────
                        Tab::make('Integrations')
                            ->icon(Heroicon::PuzzlePiece)
                            ->columns(2)
                            ->schema([
                                TextInput::make('provider_value')
                                    ->label('Provider value')
                                    ->numeric()
                                    ->minValue(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Numeric enum value the provider uses for this administration method.')
                                    ->helperText('Optional mapping to the fulfillment provider (e.g. PrescribeRx) so synced products reuse this row. Leave blank for non-provider vocabulary.'),
                            ]),
                    ]),
            ]);
    }
}
