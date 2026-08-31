<?php

namespace App\Filament\Resources\Cms\RegionItems\Schemas;

use App\Enums\Cms\Region;
use App\Enums\Cms\RegionItemKind;
use App\Filament\Support\SectionFormBuilder;
use App\Models\Cms\GlobalSection;
use App\Models\Cms\Menu;
use App\Services\Cms\SectionRegistry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RegionItemForm
{
    public static function configure(Schema $schema): Schema
    {
        $registry = app(SectionRegistry::class);

        $kindIs = fn (string $kind): callable => fn (Get $get): bool => $get('kind') === $kind;

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Layout item')
                    ->vertical()
                    ->persistTabInQueryString('layout-item-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                Select::make('region')
                                    ->options(collect(Region::cases())
                                        ->mapWithKeys(fn (Region $region): array => [$region->value => $region->label()])
                                        ->all())
                                    ->required()
                                    ->native(false),
                                Select::make('kind')
                                    ->options(collect(RegionItemKind::cases())
                                        ->mapWithKeys(fn (RegionItemKind $kind): array => [$kind->value => $kind->label()])
                                        ->all())
                                    ->required()
                                    ->reactive()
                                    ->native(false)
                                    ->disabledOn('edit')
                                    ->helperText('Cannot change after creation — delete and re-add.'),
                                Select::make('section_type')
                                    ->label('Section type')
                                    ->options($registry->options('page'))
                                    ->reactive()
                                    ->native(false)
                                    ->disabledOn('edit')
                                    ->visible($kindIs(RegionItemKind::Section->value))
                                    ->required($kindIs(RegionItemKind::Section->value)),
                                Select::make('global_section_id')
                                    ->label('Global block')
                                    ->options(fn (): array => GlobalSection::query()
                                        ->where('enabled', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->visible($kindIs(RegionItemKind::GlobalSection->value))
                                    ->required($kindIs(RegionItemKind::GlobalSection->value)),
                                Select::make('menu_id')
                                    ->label('Menu')
                                    ->options(fn (): array => Menu::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->visible($kindIs(RegionItemKind::Menu->value))
                                    ->required($kindIs(RegionItemKind::Menu->value)),
                                Toggle::make('enabled')
                                    ->default(true),
                            ]),

                        // ── Content ───────────────────────────────────
                        Tab::make('Content')
                            ->icon(Heroicon::PencilSquare)
                            ->visible($kindIs(RegionItemKind::Section->value))
                            ->schema([
                                Group::make([
                                    ...SectionFormBuilder::typeGroups('section_type'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
