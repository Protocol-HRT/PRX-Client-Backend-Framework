<?php

namespace App\Filament\Resources\Blog\BlogCategories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->helperText('URL path. Lowercase letters, numbers, hyphens.'),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->helperText('Sort order in navigation and listings.'),
                    ]),
                Section::make('Imagery')
                    ->components([
                        FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('blog/categories')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
                Section::make('SEO overrides')
                    ->description('Leave blank to fall back to the global SEO settings.')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')->maxLength(255),
                        Textarea::make('meta_description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
