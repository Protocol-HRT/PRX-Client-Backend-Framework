<?php

namespace App\Filament\Resources\Catalog\Ingredients\Schemas;

use App\Enums\Catalog\SexEligibility;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Ingredient')
                    ->vertical()
                    ->persistTabInQueryString('ingredient-tab')
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
                                    ->hintIcon(Heroicon::InformationCircle, 'Active ingredient name, e.g. Sermorelin. Referenced by product concentration rows.')
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
                                TextInput::make('short_name')
                                    ->maxLength(64)
                                    ->hintIcon(Heroicon::InformationCircle, 'Compact label used where space is tight, e.g. ingredient chips on product cards.'),
                                Textarea::make('description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Controls display order in lists. Lower numbers appear first.'),
                                Toggle::make('is_active')
                                    ->default(true),
                            ]),

                        // ── Eligibility ───────────────────────────────
                        // The gate the intake quiz applies BEFORE ranking.
                        // Relevance orders things that are all acceptable;
                        // this decides what is acceptable at all.
                        Tab::make('Eligibility')
                            ->icon(Heroicon::ShieldCheck)
                            ->columns(2)
                            ->schema([
                                Select::make('sex_eligibility')
                                    ->label('Who can be offered this')
                                    ->options(SexEligibility::options())
                                    ->default(SexEligibility::Any->value)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->columnSpanFull()
                                    ->helperText(fn (?string $state): string => SexEligibility::tryFrom($state ?? '')?->description()
                                        ?? 'Controls whether the intake quiz may recommend this to a visitor.')
                                    ->hintIcon(Heroicon::InformationCircle, 'Physiological applicability, not gender identity. The quiz question wording is authored separately, so this does not decide what the visitor is asked — only which answers this ingredient is suitable for.'),

                                TextInput::make('min_age')
                                    ->label('Minimum age')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(120)
                                    ->placeholder('No minimum')
                                    ->hintIcon(Heroicon::InformationCircle, 'Leave blank for no lower bound. Blank is not 18 — a bound nobody set must not start filtering people out.'),

                                TextInput::make('max_age')
                                    ->label('Maximum age')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(120)
                                    ->placeholder('No maximum')
                                    ->gte('min_age')
                                    ->hintIcon(Heroicon::InformationCircle, 'Leave blank for no upper bound.'),

                                Textarea::make('eligibility_note')
                                    ->label('Why — shown in the protocol')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->helperText('Optional. The reason, in your words, for the rules above — "not recommended over 65 due to cardiovascular risk". This is quoted in the generated protocol and PDF; the age numbers alone cannot explain themselves.'),
                            ]),

                        // ── Integrations ──────────────────────────────
                        Tab::make('Integrations')
                            ->icon(Heroicon::PuzzlePiece)
                            ->columns(2)
                            ->schema([
                                TextInput::make('provider_ingredient_id')
                                    ->label('Provider ingredient ID')
                                    ->maxLength(36)
                                    ->hintIcon(Heroicon::InformationCircle, 'UUID of the matching ingredient on the provider side.')
                                    ->helperText('Optional mapping to the fulfillment provider (e.g. PrescribeRx) so synced products reuse this row. Leave blank for non-provider vocabulary.'),
                            ]),
                    ]),
            ]);
    }
}
