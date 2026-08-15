<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Actions\Cms\RestorePageRevisionAction;
use App\Enums\Cms\RevisionCause;
use App\Models\Cms\PageRevision;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use RuntimeException;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revisions';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Captured')
                    ->dateTime()
                    ->description(fn (PageRevision $record): string => $record->created_at->diffForHumans()),
                TextColumn::make('cause')
                    ->badge()
                    ->formatStateUsing(fn (RevisionCause $state): string => $state->label()),
                TextColumn::make('creator.name')
                    ->label('By')
                    ->placeholder('—'),
                TextColumn::make('sections')
                    ->label('Sections')
                    ->state(fn (PageRevision $record): int => $record->sectionCount()),
                TextColumn::make('content_hash')
                    ->label('Hash')
                    ->limit(8)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Inspect')
                    ->icon('heroicon-o-eye')
                    ->modalSubmitAction(false)
                    ->modalHeading('Revision snapshot')
                    ->modalContent(fn (PageRevision $record): HtmlString => new HtmlString(
                        '<pre class="text-xs overflow-auto max-h-96">'
                        .e(json_encode($record->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        .'</pre>'
                    )),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This replaces the page settings and ALL sections with the captured state. The current state is snapshotted first so you can undo.')
                    ->visible(fn (): bool => auth()->user()?->can('Update:Page') ?? false)
                    ->action(function (PageRevision $record): void {
                        try {
                            app(RestorePageRevisionAction::class)->execute($record);
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->title('Could not restore revision')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Revision restored')
                            ->body('The page and its sections now match the captured state.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
