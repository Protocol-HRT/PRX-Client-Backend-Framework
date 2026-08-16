<?php

namespace App\Filament\Resources\Catalog\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Reviews on a catalog record (reviews morph). Shared by ProductResource
 * and PackageResource. Rows are admin-curated for now (`source` stays
 * "admin"); portal/integration-sourced rows will appear here for the same
 * moderation flow once those pipelines exist. Only approved reviews reach
 * the storefront API.
 */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Reviews';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('rating')
                ->required()
                ->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star'])
                ->native(false),
            TextInput::make('author_name')
                ->label('Author display name')
                ->required()
                ->maxLength(255)
                ->hintIcon(Heroicon::InformationCircle, 'Shown publicly next to the review, e.g. "Sarah M.".'),
            TextInput::make('title')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('body')
                ->rows(5)
                ->columnSpanFull(),
            Toggle::make('is_approved')
                ->label('Approved')
                ->default(true)
                ->hintIcon(Heroicon::InformationCircle, 'Only approved reviews appear on the storefront and count toward the rating.'),
            DateTimePicker::make('reviewed_at')
                ->label('Review date')
                ->default(now())
                ->hintIcon(Heroicon::InformationCircle, 'Displayed on the storefront and used for newest-first ordering.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('author_name')
            ->defaultSort('reviewed_at', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state)),
                TextColumn::make('author_name')->searchable(),
                TextColumn::make('title')->limit(40)->placeholder('—')->toggleable(),
                IconColumn::make('is_approved')->label('Approved')->boolean(),
                TextColumn::make('source')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewed_at')->dateTime('M j, Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_approved')->label('Approved'),
            ])
            ->headerActions([
                CreateAction::make()->modalHeading('New review'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
