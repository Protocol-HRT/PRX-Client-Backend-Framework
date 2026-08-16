<?php

namespace App\Filament\Resources\Catalog\Packages\Tables;

use App\Enums\CatalogStatus;
use App\Filament\Resources\Catalog\Actions\MatchToPrxTableAction;
use App\Services\Llm\SeoMetaGenerator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->prefix('/packages/')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CatalogStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('plans_count')
                    ->label('Plans')
                    ->counts('plans')
                    ->sortable(),
                TextColumn::make('retail_price')
                    ->label('Retail')
                    ->money('usd')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('sale_price')
                    ->label('Sale')
                    ->money('usd')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
                TextColumn::make('provider_package_id')
                    ->label('PRX ID')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('position', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options(CatalogStatus::class),
                TernaryFilter::make('is_featured'),
                TernaryFilter::make('requires_lab')->label('Requires lab'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                MatchToPrxTableAction::make()
                    ->visible(fn ($record): bool => $record->provider_package_id === null && (auth()->user()?->can('Update:Package') ?? false)),
                MatchToPrxTableAction::unmap()
                    ->visible(fn ($record): bool => $record->provider_package_id !== null && (auth()->user()?->can('Update:Package') ?? false)),
                Action::make('generateSeo')
                    ->visible(fn (): bool => auth()->user()?->can('Update:Package') ?? false)
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
                    BulkAction::make('publish')
                        ->visible(fn (): bool => auth()->user()?->can('Update:Package') ?? false)
                        ->label('Approve & Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => CatalogStatus::Published]))
                        ->successNotificationTitle('Packages published'),
                    BulkAction::make('draft')
                        ->visible(fn (): bool => auth()->user()?->can('Update:Package') ?? false)
                        ->label('Set to Draft')
                        ->icon('heroicon-o-pencil')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => CatalogStatus::Draft]))
                        ->successNotificationTitle('Packages set to Draft'),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
