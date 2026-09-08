<?php

namespace App\Filament\Resources\Referrals\Schemas;

use App\Models\Referral\ReferralSource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ReferralSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Who is being credited')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $get, $set, ?ReferralSource $record): void {
                                // Derive a slug for NEW rows only. The slug is
                                // snapshotted onto every click this source
                                // produces, so moving it on an existing record
                                // would make old rows disagree with new ones.
                                if ($record === null && blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            })
                            ->hintIcon(Heroicon::InformationCircle, 'The affiliate, group or partner as you refer to them.'),

                        Select::make('type')
                            ->required()
                            ->default('affiliate')
                            ->options([
                                'affiliate' => 'Affiliate',
                                'sales_group' => 'Sales group',
                                'partner' => 'Partner',
                                'internal' => 'Internal',
                            ])
                            ->live()
                            ->hintIcon(Heroicon::InformationCircle, 'A sales group can own affiliates. The others stand alone.'),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->hintIcon(Heroicon::InformationCircle, 'Recorded on every referral this source produces, so it survives even if the source is later removed. Avoid changing it once codes are live.'),

                        Select::make('parent_id')
                            ->label('Reports into')
                            ->relationship(
                                'parent',
                                'name',
                                // ANY source may parent any other — the tree is
                                // arbitrary depth, matching how a real sales
                                // organisation nests. Excluded here are only the
                                // choices that would create a cycle: itself and
                                // its own downline. The model re-checks on save,
                                // because a select is a convenience and not a
                                // constraint.
                                fn ($query, ?ReferralSource $record) => $query->when(
                                    $record,
                                    fn ($q) => $q->whereNotIn('id', $record->descendantAndSelfIds()),
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('Top level')
                            ->hintIcon(Heroicon::InformationCircle, 'Who this sits under. A group sees its own numbers plus everyone beneath it, however many levels deep.'),
                    ]),

                Section::make('Codes')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code_prefix')
                            ->label('Code prefix')
                            ->maxLength(12)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->placeholder('Defaults to the slug')
                            ->hintIcon(Heroicon::InformationCircle, 'Codes generated for this source start with it, e.g. "acme" gives "acme-7k2f9x". Makes a code recognisable at a glance.'),

                        TextInput::make('commission_rate')
                            ->label('Commission rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step('0.01')
                            ->hintIcon(Heroicon::InformationCircle, 'Used to calculate commission automatically when a sale is captured. Each level earns the difference between its rate and the rate beneath it.'),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('company_name')->maxLength(255),
                        TextInput::make('contact_name')->maxLength(255),
                        TextInput::make('contact_email')->email()->maxLength(255),
                        TextInput::make('contact_phone')->tel()->maxLength(32),
                        Textarea::make('commission_notes')
                            ->label('Notes on terms')
                            ->rows(2)
                            ->columnSpanFull()
                            ->maxLength(255),
                    ]),

                Section::make('Status')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true)
                            ->hintIcon(Heroicon::InformationCircle, 'Switching this off stops EVERY code this source holds from earning, immediately. Nothing already recorded is affected — use this instead of deleting.'),

                        Toggle::make('portal_enabled')
                            ->label('Portal access')
                            ->hintIcon(Heroicon::InformationCircle, 'Not yet enforced — the partner portal admits anyone attached to this organisation. Set it to record intent; a future release will gate on it.'),
                    ]),
            ]);
    }
}
