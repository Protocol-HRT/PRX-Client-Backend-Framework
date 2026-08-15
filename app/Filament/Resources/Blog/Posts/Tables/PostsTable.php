<?php

namespace App\Filament\Resources\Blog\Posts\Tables;

use App\Enums\PostStatus;
use App\Services\Llm\SeoMetaGenerator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PostStatus $state): string => $state->color())
                    ->sortable(),
                IconColumn::make('featured')
                    ->boolean()
                    ->label('Featured'),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PostStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('generateSeo')
                    ->visible(fn (): bool => auth()->user()?->can('Update:BlogPost') ?? false)
                    ->label('Generate SEO')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->modalHeading('Generate SEO Metadata')
                    ->modalDescription('Review and edit the AI-generated metadata before saving.')
                    ->modalSubmitActionLabel('Save to record')
                    ->form([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->helperText('Ideal: 50–60 characters')
                            ->maxLength(60)
                            ->required(),
                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->helperText('Ideal: 150–160 characters')
                            ->maxLength(160)
                            ->rows(3)
                            ->required(),
                    ])
                    ->fillForm(function ($record): array {
                        try {
                            $generated = app(SeoMetaGenerator::class)->generateForModel($record);

                            return [
                                'meta_title' => $generated->meta_title,
                                'meta_description' => $generated->meta_description,
                            ];
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Could not generate SEO metadata')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();

                            return [
                                'meta_title' => $record->meta_title ?? '',
                                'meta_description' => $record->meta_description ?? '',
                            ];
                        }
                    })
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'meta_title' => $data['meta_title'],
                            'meta_description' => $data['meta_description'],
                        ]);
                        Notification::make()
                            ->title('SEO metadata saved')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
