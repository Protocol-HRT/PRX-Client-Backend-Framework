<?php

namespace App\Filament\Resources\Cms\Menus\RelationManagers;

use App\Actions\Cms\DeleteMenuItemAction;
use App\Actions\Cms\SaveMenuItemAction;
use App\Data\Cms\MenuItemData;
use App\Enums\Cms\MenuLinkType;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->maxLength(120),
                Select::make('parent_id')
                    ->label('Parent item')
                    ->options(fn (?MenuItem $record): array => $this->parentOptions($record))
                    ->placeholder('Top level')
                    ->searchable()
                    ->native(false),
                Select::make('link_type')
                    ->label('Link type')
                    ->options(collect(MenuLinkType::cases())
                        ->mapWithKeys(fn (MenuLinkType $type): array => [$type->value => $type->label()])
                        ->all())
                    ->required()
                    ->reactive()
                    ->native(false),
                Select::make('linkable_id')
                    ->label(fn (Get $get): string => self::linkType($get)?->label() ?? 'Target')
                    ->options(fn (Get $get): array => self::linkableOptions(self::linkType($get)))
                    ->searchable()
                    ->visible(fn (Get $get): bool => self::linkType($get)?->isEntity() ?? false)
                    ->required(fn (Get $get): bool => self::linkType($get)?->isEntity() ?? false),
                TextInput::make('url')
                    ->label('URL')
                    ->maxLength(2048)
                    ->visible(fn (Get $get): bool => self::isManualLink($get))
                    ->required(fn (Get $get): bool => self::isManualLink($get))
                    ->helperText(fn (Get $get): ?string => self::linkType($get) === MenuLinkType::Anchor
                        ? 'Fragment, e.g. #pricing'
                        : null),
                Select::make('target')
                    ->options([
                        '_self' => 'Same tab',
                        '_blank' => 'New tab',
                    ])
                    ->placeholder('Same tab'),
                TextInput::make('icon')
                    ->maxLength(80),
                TextInput::make('badge')
                    ->maxLength(40)
                    ->helperText('Small highlight label, e.g. "New". Special value "cta" renders this item as the header call-to-action button instead of a nav link.'),
                Toggle::make('enabled')
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['linkable', 'parent']))
            ->columns([
                TextColumn::make('label')
                    ->formatStateUsing(fn (string $state, MenuItem $record): string => str_repeat('—  ', $record->depth() - 1).$state)
                    ->searchable(),
                TextColumn::make('link_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (MenuLinkType $state): string => $state->label()),
                TextColumn::make('links_to')
                    ->label('Links to')
                    ->state(fn (MenuItem $record): ?string => $record->link_type->isEntity()
                        ? ($record->linkable?->name ?? $record->linkable?->title ?? '⚠ missing')
                        : $record->url),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('position')
                    ->label('#')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, CreateAction $action): MenuItem => $this->saveItem($data, null, $action)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (MenuItem $record, array $data, EditAction $action): MenuItem => $this->saveItem($data, $record, $action)),
                DeleteAction::make()
                    ->modalDescription('Deleting an item also deletes all of its nested child items.')
                    ->using(function (MenuItem $record): bool {
                        app(DeleteMenuItemAction::class)->execute($record);

                        return true;
                    }),
            ]);
    }

    /**
     * Build the DTO and route the write through the action layer; tree
     * integrity violations surface as a danger toast and halt the action.
     */
    private function saveItem(array $data, ?MenuItem $existing, Action $action): MenuItem
    {
        /** @var Menu $menu */
        $menu = $this->getOwnerRecord();

        $rawType = $data['link_type'] ?? null;

        $payload = [
            'menu_id' => $menu->getKey(),
            'parent_id' => filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
            'label' => $data['label'] ?? '',
            'link_type' => $rawType instanceof MenuLinkType ? $rawType->value : (string) $rawType,
            'linkable_id' => filled($data['linkable_id'] ?? null) ? (int) $data['linkable_id'] : null,
            'url' => $data['url'] ?? null,
            'target' => $data['target'] ?? null,
            'icon' => $data['icon'] ?? null,
            'badge' => $data['badge'] ?? null,
            'enabled' => (bool) ($data['enabled'] ?? true),
        ];

        try {
            return app(SaveMenuItemAction::class)->execute(
                MenuItemData::validateAndCreate($payload),
                $existing,
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not save menu item')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $action->halt();
        }
    }

    /**
     * Eligible parents: this menu's items, minus the record being edited and
     * its descendants (would create a cycle), minus items already at the
     * maximum depth (their children would exceed the limit).
     *
     * @return array<int, string>
     */
    private function parentOptions(?MenuItem $record): array
    {
        /** @var Menu $menu */
        $menu = $this->getOwnerRecord();

        $maxDepth = (int) config('cms.menu.max_depth', 3);

        $items = $menu->items()->get();

        $excluded = [];

        if ($record !== null && $record->exists) {
            $excluded[$record->id] = true;

            // Sweep until stable to collect the whole descendant subtree.
            do {
                $changed = false;

                foreach ($items as $item) {
                    if ($item->parent_id !== null && isset($excluded[$item->parent_id]) && ! isset($excluded[$item->id])) {
                        $excluded[$item->id] = true;
                        $changed = true;
                    }
                }
            } while ($changed);
        }

        return $items
            ->reject(fn (MenuItem $item): bool => isset($excluded[$item->id]) || $item->depth() >= $maxDepth)
            ->pluck('label', 'id')
            ->all();
    }

    /**
     * Form state may hold the raw string value or (when hydrated from the
     * enum-cast attribute) the enum instance — normalize to the enum.
     */
    private static function linkType(Get $get): ?MenuLinkType
    {
        $raw = $get('link_type');

        if ($raw instanceof MenuLinkType) {
            return $raw;
        }

        return MenuLinkType::tryFrom((string) $raw);
    }

    private static function isManualLink(Get $get): bool
    {
        $type = self::linkType($get);

        return $type !== null && ! $type->isEntity();
    }

    /**
     * @return array<int, string>
     */
    private static function linkableOptions(?MenuLinkType $type): array
    {
        return match ($type) {
            MenuLinkType::Page => Page::published()->orderBy('title')->pluck('title', 'id')->all(),
            MenuLinkType::Product => Product::published()->orderBy('name')->pluck('name', 'id')->all(),
            MenuLinkType::Package => Package::published()->orderBy('name')->pluck('name', 'id')->all(),
            MenuLinkType::CatalogCategory => Category::query()->where('is_visible', true)->orderBy('name')->pluck('name', 'id')->all(),
            MenuLinkType::BlogPost => BlogPost::published()->orderBy('title')->pluck('title', 'id')->all(),
            MenuLinkType::BlogCategory => BlogCategory::query()->where('is_visible', true)->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }
}
