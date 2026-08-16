<?php

namespace App\Filament\Resources\Cms\GlobalSections\Schemas;

use App\Filament\Support\SectionFormBuilder;
use App\Services\Cms\SectionRegistry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class GlobalSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        $registry = app(SectionRegistry::class);

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Global block')
                    ->vertical()
                    ->persistTabInQueryString('block-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(64)
                                    ->alphaDash()
                                    ->disabledOn('edit')
                                    ->helperText('Stable identifier used as a CSS hook by the frontend. Cannot be changed after creation.'),
                                Select::make('type')
                                    ->options($registry->options())
                                    ->required()
                                    ->reactive()
                                    ->native(false)
                                    ->disabledOn('edit')
                                    ->helperText('Defines the content shape. Cannot be changed after creation.'),
                                Toggle::make('enabled')
                                    ->default(true)
                                    ->helperText('Disabled blocks are skipped wherever they are referenced.'),
                            ]),

                        // ── Content ───────────────────────────────────
                        Tab::make('Content')
                            ->icon(Heroicon::PencilSquare)
                            ->schema([
                                ...SectionFormBuilder::typeGroups(),
                            ]),
                    ]),
            ]);
    }
}
