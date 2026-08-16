<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Actions\Cms\DetachGlobalSectionAction;
use App\Filament\Support\SectionFormBuilder;
use App\Models\Cms\GlobalSection;
use App\Models\PageSection;
use App\Services\Cms\SectionRegistry;
use Filament\Actions\Action;
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
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Sections';

    public function form(Schema $schema): Schema
    {
        $registry = app(SectionRegistry::class);

        // A section either owns its own content ("new") or references a
        // reusable global block. Global-backed sections hide the type/data
        // fields — the block itself is the single place its content is edited.
        $ownsOwnContent = fn (Get $get, string $operation, ?PageSection $record): bool => $operation === 'edit'
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
                            ->options($registry->options())
                            ->reactive()
                            ->disabledOn('edit')
                            ->native(false)
                            ->visible($ownsOwnContent)
                            ->required($ownsOwnContent)
                            ->helperText('The section type cannot be changed after creation. Delete and re-add to swap types.'),
                        Placeholder::make('global_block_notice')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (string $operation, ?PageSection $record): bool => $operation === 'edit' && $record?->global_section_id !== null)
                            ->content(fn (?PageSection $record): string => 'This section renders the global block "'.($record?->globalSection?->name ?? 'unknown').'". Edit the block itself to change its content, or detach below.'),
                        TextInput::make('anchor_id')
                            ->label('Anchor ID')
                            ->maxLength(64)
                            ->helperText('Optional. Used as the section\'s HTML id for in-page links (e.g. "pricing").'),
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
                    ->options($registry->options()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::Screen)
                    ->mutateDataUsing(function (array $data) use ($registry): array {
                        if (! empty($data['global_section_id'])) {
                            // Global-backed section: mirror the block's type and
                            // keep the local payload empty — content is rendered
                            // from the block itself.
                            $global = GlobalSection::query()->find($data['global_section_id']);
                            $data['type'] = $global?->type;
                            $data['data'] = [];

                            return $data;
                        }

                        // Merge in defaults for unset keys when creating a new section
                        $defaults = $registry->resolve($data['type'])?->defaults() ?? [];
                        $data['data'] = array_replace($defaults, $data['data'] ?? []);

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::Screen),
                Action::make('detach')
                    ->label('Detach copy')
                    ->icon('heroicon-o-scissors')
                    ->visible(fn (PageSection $record): bool => $record->global_section_id !== null
                        && (auth()->user()?->can('Update:Page') ?? false))
                    ->requiresConfirmation()
                    ->modalDescription('This copies the global block\'s current content into this section and stops following the block. Future edits to the block will no longer affect this section.')
                    ->action(function (PageSection $record): void {
                        app(DetachGlobalSectionAction::class)->execute($record);

                        Notification::make()
                            ->title('Section detached')
                            ->body('The section now owns an independent copy of the content.')
                            ->success()
                            ->send();
                    }),
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
