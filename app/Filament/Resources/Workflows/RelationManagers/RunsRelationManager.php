<?php

namespace App\Filament\Resources\Workflows\RelationManagers;

use App\Models\Workflow\WorkflowRun;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The run log — including every evaluation that did NOT match.
 *
 * Read-only. This is the screen that answers "why didn't my workflow fire?",
 * which is the question operators actually ask; a log showing only successes
 * would leave them guessing at their own conditions.
 */
class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    protected static ?string $title = 'Run log';

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
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No runs yet')
            ->emptyStateDescription('Evaluations appear here as soon as the trigger fires — including the ones that did not match.')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WorkflowRun::STATUS_COMPLETED => 'success',
                        WorkflowRun::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('skip_reason')
                    ->label('Why not')
                    ->wrap()
                    ->placeholder('—')
                    ->tooltip(fn (?string $state): ?string => $state),

                TextColumn::make('subject_id')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state, WorkflowRun $record): string => $state === null
                        ? '—'
                        : class_basename((string) $record->subject_type)." #{$state}"),

                TextColumn::make('actionRuns.action_type')
                    ->label('Steps')
                    ->badge()
                    ->color(fn ($state, WorkflowRun $record): string => $record->actionRuns
                        ->contains('status', 'failed') ? 'danger' : 'gray')
                    ->placeholder('—'),

                TextColumn::make('error')->wrap()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    WorkflowRun::STATUS_COMPLETED => 'Ran',
                    WorkflowRun::STATUS_SKIPPED => 'Skipped',
                    WorkflowRun::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
