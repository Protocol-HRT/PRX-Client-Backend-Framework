<?php

namespace App\Filament\Resources\Content\Faq\Schemas;

use App\Models\Catalog\Tag;
use App\Models\Content\FaqCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FaqItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Question')
                    ->columns(2)
                    ->components([
                        Select::make('faq_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->hintIcon(Heroicon::InformationCircle, 'FAQ category this question belongs to. Controls which section it appears under.')
                            ->options(fn () => FaqCategory::query()->orderBy('name')->pluck('name', 'id')),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->hintIcon(Heroicon::InformationCircle, 'Controls display order within the category. Lower numbers appear first.'),
                        TextInput::make('question')
                            ->required()
                            ->maxLength(500)
                            ->hintIcon(Heroicon::InformationCircle, 'The FAQ question as shown to site visitors.')
                            ->columnSpanFull(),
                        Textarea::make('answer')
                            ->rows(8)
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'The answer to the FAQ question. Supports rich-text formatting.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Publishing')
                    ->components([
                        Toggle::make('is_published')
                            ->default(false),
                    ]),
                Section::make('Tags')
                    ->columns(1)
                    ->components([
                        Select::make('tag_ids')
                            ->label('Tags')
                            ->multiple()
                            ->relationship('tags', 'name')
                            ->options(fn () => Tag::query()->where('is_visible', true)->orderBy('name')->pluck('name', 'id'))
                            ->preload()
                            ->hintIcon(Heroicon::InformationCircle, 'Optional tags for filtering FAQ items on the frontend.')
                            ->searchable(),
                    ]),
            ]);
    }
}
