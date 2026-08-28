<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Models\Lead;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The consent audit for one lead.
 *
 * READ-ONLY BY CONSTRUCTION — no create, edit or delete action anywhere on it.
 * That is not an oversight and should not be "improved": the table is
 * append-only in the model too, and an audit trail with an edit button in the
 * admin is not an audit trail. Consent changes are recorded through
 * RecordConsentAction, which writes a new row.
 *
 * LeadConsent has no Shield policy of its own, so viewing is gated on the
 * owning lead — same approach as TokensRelationManager.
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
            // Deliberately empty: see the class doc.
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
