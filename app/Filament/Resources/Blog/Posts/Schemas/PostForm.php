<?php

namespace App\Filament\Resources\Blog\Posts\Schemas;

use App\Enums\PostStatus;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogTag;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Post')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
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
                        Textarea::make('excerpt')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Short blurb shown in listing cards.'),
                        Textarea::make('content')
                            ->rows(12)
                            ->columnSpanFull()
                            ->helperText('Full post body.'),
                        Select::make('status')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft->value)
                            ->required()
                            ->native(false),
                        DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->nullable()
                            ->helperText('Leave blank to publish immediately when status is Published.'),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->helperText('Sort order within listings.'),
                    ]),
                Section::make('Featured')
                    ->columns(2)
                    ->components([
                        Toggle::make('featured')
                            ->label('Featured post'),
                        TextInput::make('read_time_minutes')
                            ->label('Read time (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Auto-compute or set manually.'),
                    ]),
                Section::make('Imagery')
                    ->columns(2)
                    ->components([
                        FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('blog/posts')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        FileUpload::make('gallery')
                            ->label('Gallery images')
                            ->image()
                            ->imageEditor()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('blog/posts/gallery')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->maxFiles(12)
                            ->columnSpanFull(),
                    ]),
                Section::make('Categories & Tags')
                    ->columns(2)
                    ->components([
                        Select::make('category_ids')
                            ->label('Categories')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->options(fn () => BlogCategory::query()->orderBy('name')->pluck('name', 'id'))
                            ->preload()
                            ->searchable(),
                        Select::make('tag_ids')
                            ->label('Tags')
                            ->multiple()
                            ->relationship('tags', 'name')
                            ->options(fn () => BlogTag::query()->orderBy('name')->pluck('name', 'id'))
                            ->preload()
                            ->searchable(),
                    ]),
                Section::make('SEO overrides')
                    ->description('Leave blank to fall back to the global SEO settings (/admin/settings/seo).')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')->maxLength(255),
                        FileUpload::make('og_image_path')
                            ->label('OG image')
                            ->image()
                            ->disk('public')
                            ->directory('blog/posts/og')
                            ->visibility('public')
                            ->maxSize(5120),
                        Textarea::make('meta_description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
