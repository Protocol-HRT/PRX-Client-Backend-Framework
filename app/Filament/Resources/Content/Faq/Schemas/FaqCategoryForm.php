<?php

namespace App\Filament\Resources\Content\Faq\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class FaqCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('FAQ category')
                    ->vertical()
                    ->persistTabInQueryString('faq-category-tab')
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
                                    ->hintIcon(Heroicon::InformationCircle, 'Category name shown as a section heading in the FAQ.')
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->alphaDash()
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier for this FAQ category. Auto-generated from name.'),
                                Textarea::make('description')
                                    ->rows(3)
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional intro text shown under the category heading on the FAQ page.')
                                    ->columnSpanFull(),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Controls display order of categories on the FAQ page. Lower numbers appear first.'),
                                Toggle::make('is_visible')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }
}
