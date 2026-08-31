<?php

namespace App\Filament\Resources\Catalog\Products\RelationManagers;

use App\Filament\Support\SectionFormBuilder;
use App\Models\Catalog\CatalogItemSection;
use App\Models\Cms\GlobalSection;
use App\Services\Cms\SectionRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Injectable detail-page sections on a catalog record — the page builder's
 * SectionsRelationManager adapted for CatalogItemSection (shared by
 * ProductResource and PackageResource). Same section registry, same form
 * builder, same envelope on the API. Sections render on the record's
 * storefront detail page below the product info, in drag order.
 *
 * NOTE: no schema-level statePath() here, and per-type group visibility in
 * SectionFormBuilder must use plain $get('type') — see
 * feedback-filament-group-get-paths.
 */
class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Page Sections';

    public function form(Schema $schema): Schema
    {
        $registry = app(SectionRegistry::class);

        // A section either owns its own content ("new") or references a
        // reusable global block — identical semantics to page sections.
        $ownsOwnContent = fn (Get $get, string $operation, ?CatalogItemSection $record): bool => $operation === 'edit'
            ? $record?->global_section_id === null
            : $get('source') !== 'global';

        return $schema
            // Stack the meta card and the type's content groups full-width —
            // the default two-column root halves the modal and quarters every
            // field inside the blueprints' own grids.
            ->columns(1)
            ->components([
                Section::make('Section')
                    ->columns(2)
                    ->components([
                        Radio::make('source')
                            ->options([
                                'new' => 'New section',
                                'global' => 'Reuse a global block',
                            ])
                            ->default('new')
                            ->live()
                            ->dehydrated(false)
                            ->hiddenOn('edit')
                            ->columnSpanFull(),
                        Select::make('global_section_id')
                            ->label('Global block')
                            ->options(fn (): array => GlobalSection::query()
                                ->where('enabled', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->visible(fn (Get $get, string $operation): bool => $operation !== 'edit' && $get('source') === 'global')
                            ->required(fn (Get $get, string $operation): bool => $operation !== 'edit' && $get('source') === 'global'),
                        Select::make('type')
                            ->options($registry->options('catalog'))
                            ->reactive()
                            ->disabledOn('edit')
                            ->native(false)
                            ->visible($ownsOwnContent)
                            ->required($ownsOwnContent)
                            ->helperText('The section type cannot be changed after creation. Delete and re-add to swap types.'),
                        Placeholder::make('global_block_notice')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (string $operation, ?CatalogItemSection $record): bool => $operation === 'edit' && $record?->global_section_id !== null)
                            ->content(fn (?CatalogItemSection $record): string => 'This section renders the global block "'.($record?->globalSection?->name ?? 'unknown').'". Edit the block itself to change its content.'),
                        TextInput::make('anchor_id')
                            ->label('Anchor ID')
                            ->maxLength(64)
                            ->helperText('Optional. Used as the section\'s HTML id for in-page links.'),
                        Toggle::make('enabled')
                            ->default(true)
                            ->helperText('Disabled sections are still saved but skipped on render.'),
                    ]),
                Group::make([
                    ...SectionFormBuilder::typeGroups(),
                ])->visible($ownsOwnContent),
            ]);
    }

    public function table(Table $table): Table
    {
        $registry = app(SectionRegistry::class);

        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $registry->labelFor($state)),
                TextColumn::make('globalSection.name')
                    ->label('Global')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('anchor_id')
                    ->label('Anchor')
                    ->placeholder('—')
                    ->copyable(),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options($registry->options('catalog')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data) use ($registry): array {
                        if (! empty($data['global_section_id'])) {
                            // Global-backed section: mirror the block's type and
                            // keep the local payload empty.
                            $global = GlobalSection::query()->find($data['global_section_id']);
                            $data['type'] = $global?->type;
                            $data['data'] = [];

                            return $data;
                        }

                        $defaults = $registry->resolve($data['type'])?->defaults() ?? [];
                        $data['data'] = array_replace($defaults, $data['data'] ?? []);

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('position'));
    }
}
