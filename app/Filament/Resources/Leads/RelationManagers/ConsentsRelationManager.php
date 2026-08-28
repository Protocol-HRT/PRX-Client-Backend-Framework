<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Actions\Leads\RecordConsentAction;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The consent audit for one lead.
 *
 * APPEND-ONLY, NOT READ-ONLY, and the distinction is the whole design. There is
 * no edit and no delete anywhere on it — an audit trail with an edit button is
 * not an audit trail, and the model refuses both regardless. But there IS a way
 * to record a decision, because an audit nobody can write to is not an audit
 * either.
 *
 * WHY THIS ACTION HAD TO EXIST: it was the only consent surface in the product,
 * and the lead form's consent toggles wrote no audit row — so no path in the
 * admin could record a withdrawal at all. Somebody asking to be taken off a list
 * had their opt-out entered into a cached boolean that nothing downstream reads,
 * while the audit — which everything downstream DOES read — still said granted,
 * and the next workflow run subscribed them at the destination. A control that
 * appears to work and does nothing is worse than no control.
 *
 * The action APPENDS. A withdrawal is a new entry saying `granted = false`; a
 * change of mind after that is another entry. Everything goes through
 * RecordConsentAction, which also keeps the lead's cached booleans in step.
 *
 * LeadConsent has no Shield policy of its own, so viewing is gated on the owning
 * lead — same approach as TokensRelationManager. Recording is gated on being
 * able to UPDATE that lead, because it changes what may be sent to them.
 */
class ConsentsRelationManager extends RelationManager
{
    protected static string $relationship = 'consents';

    protected static ?string $title = 'Consent audit';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        // Resolved here rather than inside the action's `visible()` closure:
        // Filament rebinds those closures, so `$this` is not the relation
        // manager by the time one runs and the owner record is not reachable
        // from it. The owner is known when the table is built, which is early
        // enough.
        $lead = $this->getOwnerRecord();
        $canRecord = auth()->user()?->can('update', $lead) ?? false;

        return $table
            ->defaultSort('consented_at', 'desc')
            ->emptyStateHeading('No consent recorded')
            ->emptyStateDescription('Consents captured before the audit existed appear as "backfill" rows without wording, because the wording they saw was never stored.')
            ->columns([
                TextColumn::make('consented_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('channel')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('granted')
                    ->label('Granted')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->tooltip(fn (bool $state): string => $state ? 'Opted in' : 'Declined or withdrawn'),

                TextColumn::make('consent_text')
                    ->label('What they agreed to')
                    ->wrap()
                    ->placeholder('Not recorded')
                    ->tooltip(fn ($record): ?string => $record->consent_text)
                    ->description(fn ($record): ?string => $record->consent_version
                        ? "Version {$record->consent_version}"
                        : null),

                TextColumn::make('source')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'backfill' ? 'warning' : 'gray')
                    ->placeholder('—'),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('user_agent')
                    ->label('Device')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => $record->user_agent)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded by')
                    ->placeholder('The visitor')
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('record')
                    ->label('Record a decision')
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Record a consent decision')
                    ->modalDescription('This adds an entry to the history. Nothing already recorded changes.')
                    ->modalSubmitActionLabel('Record it')
                    ->visible($canRecord)
                    ->schema([
                        Select::make('channel')
                            ->required()
                            ->options(['email' => 'Email', 'sms' => 'SMS'])
                            ->helperText('Consent is per channel. Withdrawing email does not withdraw SMS.'),

                        // Explicit values rather than a boolean radio: `required`
                        // and `false` argue with each other, and the wrong
                        // outcome here is a withdrawal recorded against somebody
                        // who agreed — silently, in a row nobody can edit.
                        Radio::make('decision')
                            ->label('What are you recording?')
                            ->required()
                            ->options([
                                'withdrew' => 'They withdrew or declined',
                                'agreed' => 'They agreed',
                            ]),

                        Textarea::make('consent_text')
                            ->label('What they were told, or how you know')
                            ->rows(2)
                            ->maxLength(2000)
                            ->helperText('For a withdrawal, how the request reached you — "asked by email on the 3rd". '
                                .'Leave empty if you genuinely do not know; an invented sentence is worse than a blank, '
                                .'because this entry cannot be edited afterwards.'),
                    ])
                    ->action(function (array $data) use ($lead): void {
                        // Through the one write path, so the lead's cached
                        // booleans move with the audit rather than away from it.
                        // `source: admin` and the user id are what keep an
                        // operator-recorded consent from ever being mistaken for
                        // something the visitor did themselves.
                        app(RecordConsentAction::class)->execute(
                            lead: $lead,
                            channel: $data['channel'],
                            granted: $data['decision'] === 'agreed',
                            text: $data['consent_text'] ?: null,
                            source: 'admin',
                            userId: auth()->id(),
                        );

                        Notification::make()
                            ->success()
                            ->title($data['decision'] === 'agreed' ? 'Consent recorded' : 'Withdrawal recorded')
                            ->body('It takes effect on the next workflow run — nothing needs editing.')
                            ->send();
                    }),
            ])
            // No edit, no delete: see the class doc.
            ->recordActions([])
            ->toolbarActions([]);
    }
}
