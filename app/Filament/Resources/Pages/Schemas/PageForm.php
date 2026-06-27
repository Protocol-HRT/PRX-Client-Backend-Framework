<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PageStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
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
                        Select::make('status')
                            ->options(PageStatus::class)
                            ->default(PageStatus::Draft->value)
                            ->required()
                            ->native(false),
                        TextInput::make('template')
                            ->required()
                            ->default('default')
                            ->maxLength(64)
                            ->helperText('Reserved for future per-page Blade templates.'),
                        DateTimePicker::make('publish_at')
                            ->helperText('Optional. If set in the future, the page stays draft-equivalent until that time.'),
                    ]),
                Section::make('SEO overrides')
                    ->description('Leave blank to fall back to the global SEO settings (/admin/settings/seo).')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')->maxLength(255),
                        TextInput::make('og_image_path')
                            ->label('OG image path')
                            ->maxLength(2048)
                            ->helperText('Upload via /admin/media, then paste the path here.'),
                        Textarea::make('meta_description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('noindex')
                            ->label('Hide from search engines')
                            ->helperText('Forces this page to emit `noindex` even if global indexing is on.'),
                    ]),
            ]);
    }
}
