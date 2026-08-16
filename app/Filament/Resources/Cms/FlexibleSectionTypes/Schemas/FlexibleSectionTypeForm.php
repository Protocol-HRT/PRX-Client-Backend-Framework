<?php

namespace App\Filament\Resources\Cms\FlexibleSectionTypes\Schemas;

use App\Enums\Cms\FlexibleFieldKind;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class FlexibleSectionTypeForm
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Section type')
                    ->vertical()
                    ->persistTabInQueryString('section-type-tab')
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
                                    ->hintIcon(Heroicon::InformationCircle, 'Display name shown in the section picker.')
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
                                    ->hintIcon(Heroicon::InformationCircle, 'Unique identifier for this section type. Auto-generated from the name.')
                                    ->helperText('Used as the section type identifier and CSS hook. Cannot be changed after creation.'),
                                TextInput::make('icon')
                                    ->maxLength(80)
                                    ->hintIcon(Heroicon::InformationCircle, 'Icon shown next to this type in the section picker.')
                                    ->helperText('Heroicon name, e.g. heroicon-o-star'),
                                Toggle::make('enabled')
                                    ->default(true)
                                    ->helperText('Disabled types are hidden from the section picker; existing sections stop rendering until re-enabled.'),
                                Textarea::make('description')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->hintIcon(Heroicon::InformationCircle, 'Shown to editors in the section picker to explain what this type is for.')
                                    ->columnSpanFull(),
                            ]),

                        // ── Fields ────────────────────────────────────
                        Tab::make('Fields')
                            ->icon(Heroicon::ListBullet)
                            ->schema([
                                Repeater::make('schema.fields')
                                    ->label('Fields')
                                    ->helperText('The content fields editors fill in for each section of this type. Field keys become the property names in the API payload.')
                                    ->reorderable()
                                    ->columnSpanFull()
                                    ->itemLabel(fn (array $state): ?string => ($state['label'] ?? null) ?: ($state['key'] ?? null))
                                    ->addActionLabel('Add field')
                                    ->schema([
                                        TextInput::make('key')
                                            ->required()
                                            ->regex(self::KEY_PATTERN)
                                            ->maxLength(64)
                                            ->helperText('Snake_case identifier in the API payload. Renaming orphans existing content — avoid changing it once in use.'),
                                        Select::make('kind')
                                            ->required()
                                            ->live()
                                            ->options(self::kindOptions(FlexibleFieldKind::cases()))
                                            ->native(false),
                                        TextInput::make('label'),
                                        Toggle::make('required'),
                                        TextInput::make('help')
                                            ->maxLength(255),
                                        TextInput::make('max')
                                            ->numeric()
                                            ->visible(fn (callable $get): bool => in_array($get('kind'), [
                                                FlexibleFieldKind::Text->value,
                                                FlexibleFieldKind::Textarea->value,
                                                FlexibleFieldKind::Repeater->value,
                                                FlexibleFieldKind::Products->value,
                                                FlexibleFieldKind::Packages->value,
                                                FlexibleFieldKind::Number->value,
                                            ], true))
                                            ->helperText('Max length (text), max items (repeater/pickers), or max value (number).'),
                                        TextInput::make('min')
                                            ->numeric()
                                            ->visible(fn (callable $get): bool => $get('kind') === FlexibleFieldKind::Number->value)
                                            ->helperText('Minimum value.'),
                                        Toggle::make('simple')
                                            ->label('Simple items')
                                            ->visible(fn (callable $get): bool => $get('kind') === FlexibleFieldKind::Repeater->value)
                                            ->helperText('Items store the single child field\'s bare value (a flat list) instead of a keyed object. Define exactly one child field.'),
                                        Toggle::make('raw')
                                            ->label('Keep raw in API')
                                            ->visible(fn (callable $get): bool => in_array($get('kind'), [
                                                FlexibleFieldKind::Products->value,
                                                FlexibleFieldKind::Packages->value,
                                                FlexibleFieldKind::Product->value,
                                                FlexibleFieldKind::Package->value,
                                            ], true))
                                            ->helperText('Ship the stored ids untouched instead of inlined cards — pair with a resolver op that writes the inlined list to another key.'),
                                        self::visibleWhenRepeater(),
                                        Repeater::make('options')
                                            ->reorderable()
                                            ->columnSpanFull()
                                            ->visible(fn (callable $get): bool => $get('kind') === FlexibleFieldKind::Select->value)
                                            ->itemLabel(fn (array $state): ?string => ($state['label'] ?? null) ?: ($state['value'] ?? null))
                                            ->addActionLabel('Add option')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('value')
                                                    ->required(),
                                                TextInput::make('label')
                                                    ->required(),
                                            ]),
                                        Repeater::make('fields')
                                            ->label('Child fields')
                                            ->reorderable()
                                            ->columnSpanFull()
                                            ->visible(fn (callable $get): bool => in_array($get('kind'), [
                                                FlexibleFieldKind::Repeater->value,
                                                FlexibleFieldKind::Group->value,
                                            ], true))
                                            ->itemLabel(fn (array $state): ?string => ($state['label'] ?? null) ?: ($state['key'] ?? null))
                                            ->addActionLabel('Add child field')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('key')
                                                    ->required()
                                                    ->regex(self::KEY_PATTERN)
                                                    ->maxLength(64)
                                                    ->helperText('Snake_case identifier in the API payload. Renaming orphans existing content — avoid changing it once in use.'),
                                                Select::make('kind')
                                                    ->required()
                                                    ->options(self::kindOptions(FlexibleFieldKind::repeaterChildKinds()))
                                                    ->native(false),
                                                TextInput::make('label'),
                                                Toggle::make('required'),
                                                TextInput::make('help')
                                                    ->maxLength(255),
                                                self::visibleWhenRepeater(),
                                            ]),
                                    ])
                                    ->columns(2),
                            ]),
                    ]),
            ]);
    }

    /**
     * Declarative visibility rules for one field: show it only while the
     * named sibling fields hold (or don't hold) the given values.
     */
    private static function visibleWhenRepeater(): Repeater
    {
        return Repeater::make('visible_when')
            ->label('Visibility conditions')
            ->helperText('Show this field only while every condition passes. Leave empty to always show it.')
            ->columnSpanFull()
            ->defaultItems(0)
            ->addActionLabel('Add condition')
            ->columns(3)
            ->schema([
                TextInput::make('field')
                    ->required()
                    ->regex(self::KEY_PATTERN)
                    ->helperText('Sibling field key.'),
                Select::make('operator')
                    ->options(['equals' => 'Equals', 'not_equals' => 'Does not equal'])
                    ->default('equals')
                    ->native(false),
                TextInput::make('value')
                    ->required(),
            ]);
    }

    /**
     * @param  list<FlexibleFieldKind>  $kinds
     * @return array<string, string>
     */
    private static function kindOptions(array $kinds): array
    {
        $options = [];

        foreach ($kinds as $kind) {
            $options[$kind->value] = $kind->label();
        }

        return $options;
    }
}
