<?php

namespace App\Filament\Resources\Blog\BlogCategories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class BlogCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Blog category')
                    ->vertical()
                    ->persistTabInQueryString('blog-category-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'Category name shown as a section heading in blog listings.')
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier. Auto-generated; change with caution — breaks existing links.')
                                    ->helperText('URL path. Lowercase letters, numbers, hyphens.'),
                                Textarea::make('description')
                                    ->rows(3)
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional intro text shown under the category heading on the blog index.')
                                    ->columnSpanFull(),
                                Toggle::make('is_visible')
                                    ->label('Visible')
                                    ->default(true),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Controls display order. Lower numbers appear first.')
                                    ->helperText('Sort order in navigation and listings.'),
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
                                    ->directory('blog/categories')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->hintIcon(Heroicon::InformationCircle, 'Banner image displayed at the top of this category archive page.')
                                    ->columnSpanFull(),
                            ]),

                        // ── SEO ───────────────────────────────────────
                        Tab::make('SEO')
                            ->icon(Heroicon::MagnifyingGlass)
                            ->columns(2)
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'SEO title for this category page. Defaults to category name if blank.')
                                    ->helperText('Leave blank to fall back to the global SEO settings.'),
                                Textarea::make('meta_description')
                                    ->maxLength(500)
                                    ->rows(3)
                                    ->hintIcon(Heroicon::InformationCircle, 'SEO meta description for this category. 150–160 chars recommended.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
