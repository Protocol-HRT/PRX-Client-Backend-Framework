<?php

namespace App\Filament\Resources\Catalog\Categories\Schemas;

use App\Models\Catalog\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class CategoryForm
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
                            ->hintIcon(Heroicon::InformationCircle, 'Display name shown on the storefront and in admin listings.')
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier. Auto-generated; change with caution — breaks existing links.'),
                        Select::make('parent_id')
                            ->label('Parent category')
                            ->options(fn ($record) => Category::query()
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->hintIcon(Heroicon::InformationCircle, 'Optional parent for nested category hierarchies. Leave blank for a top-level category.')
                            ->placeholder('— top-level —'),
                        TextInput::make('icon')
                            ->maxLength(64)
                            ->hintIcon(Heroicon::InformationCircle, 'Optional Heroicon name used to represent this category, e.g. heroicon-o-beaker.')
                            ->helperText('Optional Heroicon name, e.g. heroicon-o-beaker.'),
                        TextInput::make('provider_encounter_type_id')
                            ->label('Provider encounter type ID')
                            ->maxLength(255)
                            ->hintIcon(Heroicon::InformationCircle, 'UUID from the provider linking this category to a clinical encounter type for intake routing.')
                            ->helperText('Maps this category to the provider encounter type used for intake. Products in this category will use this encounter type by default.')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->maxLength(2000)
                            ->rows(3)
                            ->hintIcon(Heroicon::InformationCircle, 'Short description shown on the category landing page and in API responses.')
                            ->columnSpanFull(),
                        FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('catalog/categories')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->hintIcon(Heroicon::InformationCircle, 'Banner image displayed at the top of this category page on the storefront.')
                            ->columnSpanFull(),
                        Toggle::make('is_visible')
                            ->default(true)
                            ->helperText('Hidden categories are still saved but not surfaced on the public site.'),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->hintIcon(Heroicon::InformationCircle, 'Controls display order. Lower numbers appear first in navigation and listings.'),
                    ]),
                Section::make('SEO overrides')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')
                            ->maxLength(255)
                            ->hintIcon(Heroicon::InformationCircle, 'SEO title for this category page. Defaults to category name if blank.'),
                        Textarea::make('meta_description')
                            ->maxLength(500)
                            ->rows(3)
                            ->hintIcon(Heroicon::InformationCircle, 'SEO meta description for this category. 150–160 chars recommended.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
