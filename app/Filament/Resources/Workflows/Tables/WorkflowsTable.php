<?php

namespace App\Filament\Resources\Workflows\Tables;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowRun;
use App\Workflows\WorkflowRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WorkflowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            // Aggregated in one query rather than two per row. The list page is
            // the first screen an operator opens and it should not scale with the
            // number of workflows.
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'runs as recent_ran_count' => fn ($q) => $q
                    ->where('created_at', '>=', now()->subDay())
                    ->where('status', '!=', WorkflowRun::STATUS_SKIPPED),
                'runs as recent_skipped_count' => fn ($q) => $q
                    ->where('created_at', '>=', now()->subDay())
                    ->where('status', WorkflowRun::STATUS_SKIPPED),
            ]))
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium'),

                TextColumn::make('trigger_target')
                    ->label('Trigger')
                    ->badge()
                    ->color('info')
                    // Show the operator's language, not the storage key.
                    ->formatStateUsing(function (?string $state, Workflow $record): string {
                        $registry = app(WorkflowRegistry::class);

                        return $record->trigger_type === 'event_fired'
                            ? ($registry->event((string) $state)['label'] ?? (string) $state)
                            : ($registry->subject((string) $state)['label'] ?? (string) $state);
                    }),

                TextColumn::make('actions_count')
                    ->label('Steps')
                    ->counts('actions')
                    ->badge()
                    ->color('gray'),

                // The number that tells an operator whether it is working, without
                // opening it. A workflow that has only ever skipped is the common
                // "why isn't this firing" case.
                TextColumn::make('recent')
                    ->label('Last 24h')
                    ->getStateUsing(fn (Workflow $record): string => sprintf(
                        '%d ran / %d skipped',
                        $record->recent_ran_count ?? 0,
                        $record->recent_skipped_count ?? 0,
                    ))
                    ->color('gray'),

                TextColumn::make('priority')->sortable()->toggleable(),

                IconColumn::make('stop_on_first_match')->label('Stops others')->boolean()->toggleable(),

                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('trigger_type')->options([
                    'event_fired' => 'Event',
                    'model_created' => 'Record created',
                    'model_updated' => 'Record updated',
                    'model_deleted' => 'Record deleted',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
