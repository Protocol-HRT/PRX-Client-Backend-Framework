<?php

namespace App\Filament\Partner\Resources\Organizations\Schemas;

use App\Models\Referral\ReferralSource;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Deliberately narrower than the staff form.
 *
 * A partner may name a sub-organisation and say who it reports into; they may NOT
 * set its commission rate. Letting a group hand out its own rates would let
 * someone raise their downline's percentage and, through the telescoping bands,
 * change what the business pays out — that is an operator decision.
 */
class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Organisation')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $get, $set, ?ReferralSource $record): void {
                                if ($record === null && blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),

                        Select::make('type')
                            ->required()
                            ->default('affiliate')
                            ->options([
                                'affiliate' => 'Affiliate',
                                'sales_group' => 'Sales group',
                                'partner' => 'Partner',
                            ]),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->hintIcon(Heroicon::InformationCircle, 'Recorded on every referral this organisation produces. Avoid changing it once codes are live.'),

                        // WHERE A NEW ORGANISATION SITS IS CHOSEN ONCE, AT
                        // CREATION, AND IS THEN READ-ONLY HERE.
                        //
                        // Two bugs made that the right shape rather than a
                        // limitation. Required-plus-scoped-options made a
                        // partner's OWN top row unsaveable: its parent is their
                        // upline, which is deliberately not in their options, so
                        // every edit failed `Rule::in` on a field they never
                        // touched. And an editable parent that included the
                        // record itself or its own children passed validation and
                        // then threw out of `preventCycles` mid-save.
                        //
                        // Moving an existing organisation around the tree changes
                        // who earns override bands on its sales, so it is an
                        // operator decision anyway — staff can still do it.
                        Select::make('parent_id')
                            ->label('Reports into')
                            // `$operation` rather than `$record`: the record is
                            // not injected into every closure on this component,
                            // and a closure that silently receives null made the
                            // field required-and-unsatisfiable on edit.
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->required()
                            ->options(fn (?ReferralSource $record): array => static::allowedParents($record))
                            ->searchable()
                            ->hintIcon(Heroicon::InformationCircle, 'Must be your own organisation or one beneath it.'),

                        TextInput::make('contact_name')->maxLength(255),
                        TextInput::make('contact_email')->email()->maxLength(255),

                        Toggle::make('is_active')
                            ->default(true)
                            ->hintIcon(Heroicon::InformationCircle, 'Switching off stops every code this organisation holds from earning.'),
                    ]),
            ]);
    }

    /**
     * Only the viewer's own downline may be chosen as a parent.
     *
     * Without this a partner could attach a new organisation ANYWHERE in the
     * tree — including under a competitor's group, or at the top level — which
     * would both leak the existence of other organisations and put a payee
     * somewhere nobody expected. Required rather than nullable for the same
     * reason: a blank parent means top level.
     *
     * A record and its own downline are excluded, so a cycle cannot be chosen in
     * the first place — `preventCycles()` then never has to throw out of a save.
     *
     * @return array<int, string>
     */
    private static function allowedParents(?ReferralSource $record = null): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return ReferralSource::query()
            ->whereIn('id', $user->visibleReferralSourceIds() ?? [])
            ->when($record?->exists, fn ($q) => $q->whereNotIn('id', $record->descendantAndSelfIds()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
