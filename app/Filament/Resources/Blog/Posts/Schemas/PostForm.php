<?php

namespace App\Filament\Resources\Blog\Posts\Schemas;

use App\Enums\PostStatus;
use App\Models\Blog\BlogCategory;
use App\Models\Catalog\Tag;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Post')
                    ->vertical()
                    ->persistTabInQueryString('post-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'Post title as shown to readers and in browser tabs.')
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->hintIcon(Heroicon::InformationCircle, 'URL path for this post. Auto-generated from title; change with caution — breaks existing links.')
                                    ->helperText('URL path. Lowercase letters, numbers, hyphens.'),
                                Textarea::make('excerpt')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'Short summary shown in post listings and OG descriptions. 150–300 chars recommended.')
                                    ->helperText('Short blurb shown in listing cards.'),
                                Textarea::make('content')
                                    ->rows(12)
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'Full post body. Supports Markdown or plain text depending on frontend rendering.')
                                    ->helperText('Full post body.'),
                            ]),

                        // ── Publishing ────────────────────────────────
                        Tab::make('Publishing')
                            ->icon(Heroicon::Eye)
                            ->columns(2)
                            ->schema([
                                Select::make('status')
                                    ->options(PostStatus::class)
                                    ->default(PostStatus::Draft->value)
                                    ->required()
                                    ->hintIcon(Heroicon::InformationCircle, 'Current publication status. Draft posts are not visible on the public site.')
                                    ->native(false),
                                DateTimePicker::make('published_at')
                                    ->label('Publish at')
                                    ->nullable()
                                    ->hintIcon(Heroicon::InformationCircle, 'Schedule when this post goes live. Leave blank to publish immediately when status is Published.')
                                    ->helperText('Leave blank to publish immediately when status is Published.'),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Controls display order within listings. Lower numbers appear first.')
                                    ->helperText('Sort order within listings.'),
                                Toggle::make('featured')
                                    ->label('Featured post'),
                                TextInput::make('read_time_minutes')
                                    ->label('Read time (minutes)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->hintIcon(Heroicon::InformationCircle, 'Estimated reading time in minutes. Shown to readers. Auto-compute or set manually.')
                                    ->helperText('Auto-compute or set manually.'),
                            ]),

                        // ── Organization ──────────────────────────────
                        Tab::make('Organization')
                            ->icon(Heroicon::Tag)
                            ->columns(2)
                            ->schema([
                                Select::make('category_ids')
                                    ->label('Categories')
                                    ->multiple()
                                    ->relationship('categories', 'name')
                                    ->options(fn () => BlogCategory::query()->orderBy('name')->pluck('name', 'id'))
                                    ->preload()
                                    ->hintIcon(Heroicon::InformationCircle, 'Assigns this post to one or more blog categories shown in navigation and archive pages.')
                                    ->searchable(),
                                Select::make('tag_ids')
                                    ->label('Tags')
                                    ->multiple()
                                    ->relationship('tags', 'name')
                                    ->options(fn () => Tag::query()->where('is_visible', true)->orderBy('name')->pluck('name', 'id'))
                                    ->preload()
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional tags for filtering and related-post suggestions.')
                                    ->searchable(),
                            ]),

                        // ── Media ─────────────────────────────────────
                        Tab::make('Media')
                            ->icon(Heroicon::Photo)
                            ->schema([
                                FileUpload::make('hero_image_path')
                                    ->label('Hero image')
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('blog/posts')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->hintIcon(Heroicon::InformationCircle, 'Primary image displayed at the top of the post and in social share previews.')
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
                                    ->hintIcon(Heroicon::InformationCircle, 'Additional images for an in-post gallery. Max 12 images, 5 MB each.')
                                    ->columnSpanFull(),
                            ]),

                        // ── SEO ───────────────────────────────────────
                        Tab::make('SEO')
                            ->icon(Heroicon::MagnifyingGlass)
                            ->columns(2)
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'SEO title for this post page. Defaults to post title if blank.')
                                    ->helperText('Leave blank to fall back to the global SEO settings (/admin/settings/seo).'),
                                FileUpload::make('og_image_path')
                                    ->label('OG image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('blog/posts/og')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->hintIcon(Heroicon::InformationCircle, 'Open Graph image for social shares. 1200×630px recommended. Overrides hero if set.'),
                                Textarea::make('meta_description')
                                    ->maxLength(500)
                                    ->rows(3)
                                    ->hintIcon(Heroicon::InformationCircle, 'SEO meta description for this post. 150–160 chars recommended.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
