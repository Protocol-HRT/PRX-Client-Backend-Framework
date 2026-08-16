<?php

namespace App\Filament\Resources\Catalog\Products\RelationManagers;

use App\Models\Content\FaqCategory;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * FAQ items attached to a catalog record (faqables morph pivot). Shared by
 * ProductResource and PackageResource — select-or-add: attach existing FAQ
 * items from the content module, or author a new one in place (it is
 * created in the FAQ module and attached here). Drag rows to control the
 * per-record display order on the storefront detail page.
 */
class FaqsRelationManager extends RelationManager
{
    protected static string $relationship = 'faqs';

    protected static ?string $title = 'FAQs';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('faq_category_id')
                ->label('Category')
                ->nullable()
                ->searchable()
                ->preload()
                ->options(fn () => FaqCategory::query()->orderBy('name')->pluck('name', 'id'))
                ->hintIcon(Heroicon::InformationCircle, 'Optional FAQ category — used by the general FAQ page. Product-page placement comes from this attachment, not the category.'),
            Toggle::make('is_published')
                ->default(true)
                ->hintIcon(Heroicon::InformationCircle, 'Unpublished questions stay attached but never render on the storefront.'),
            TextInput::make('question')
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
            Textarea::make('answer')
                ->rows(6)
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->reorderable('faqables.position')
            ->defaultSort('faqables.position')
            ->columns([
                TextColumn::make('pivot.position')->label('#'),
                TextColumn::make('question')->searchable()->limit(80),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->multiple()
                    ->preloadRecordSelect(),
                CreateAction::make()
                    ->label('New FAQ')
                    ->modalHeading('New FAQ'),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
