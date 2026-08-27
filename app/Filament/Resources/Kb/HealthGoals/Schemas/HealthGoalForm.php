<?php

namespace App\Filament\Resources\Kb\HealthGoals\Schemas;

use App\Cms\Support\CopyFields;
use App\Models\Kb\HealthGoal;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * `name` and `prompt` are two fields on purpose, and it is the one thing on
 * this form worth explaining twice.
 *
 * `name` is the label — "Sleep" — used in admin tables, in filters, and on a
 * knowledge-base page listing what a compound is for. `prompt` is what the
 * visitor reads in the quiz — "Sleep that actually restores". They are
 * different registers, and a single field forces one of the two to read badly:
 * an admin table full of sentences, or a quiz that asks someone to pick
 * "Sleep".
 */
class HealthGoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Health goal')
                    ->vertical()
                    ->persistTabInQueryString('goal-tab')
                    ->columnSpanFull()
                    ->tabs([

                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'The short label — "Sleep", "Body composition". Used in the admin and wherever the goal is named rather than offered.')
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'Used in URLs and by the quiz. Changing it after launch breaks saved links and any reporting keyed on it.'),
                                CopyFields::inline('prompt')
                                    ->label('Quiz wording')
                                    ->columnSpanFull()
                                    ->helperText('What the visitor actually reads and picks. Write it as an outcome in their words — "Sleep that actually restores", not "Sleep".'),
                                Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional. Shown where the goal needs explaining rather than just naming.'),
                                Select::make('parent_id')
                                    ->label('Parent goal')
                                    ->options(fn (?HealthGoal $record): array => HealthGoal::query()
                                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional grouping, for when a goal is a narrower case of another.'),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Order the quiz offers goals in. Lower first.'),
                            ]),

                        Tab::make('Presentation')
                            ->icon(Heroicon::Swatch)
                            ->columns(2)
                            ->schema([
                                TextInput::make('icon')
                                    ->maxLength(64)
                                    ->hintIcon(Heroicon::InformationCircle, 'Heroicon name or emoji, shown on the quiz card.'),
                                ColorPicker::make('color')
                                    ->hintIcon(Heroicon::InformationCircle, 'Accent for this goal wherever it is shown as a card or chip.'),
                                FileUpload::make('image_path')
                                    ->image()
                                    ->disk('public')
                                    ->directory('health-goals')
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional image for the quiz card.'),
                            ]),

                        Tab::make('Availability')
                            ->icon(Heroicon::CheckCircle)
                            ->columns(1)
                            ->schema([
                                Section::make('Where this goal appears')
                                    ->description('These are separate on purpose. Taking a goal out of the quiz should not unpick the ingredient, product and compound mappings that already point at it — turn off the quiz toggle, and everything mapped stays intact.')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Off hides the goal everywhere, including from pages that already reference it.'),
                                        Toggle::make('show_in_quiz')
                                            ->label('Offer in the quiz')
                                            ->default(true)
                                            ->helperText('Off keeps the goal working for everything already mapped to it, but stops visitors picking it.'),
                                    ]),
                            ]),

                        Tab::make('SEO')
                            ->icon(Heroicon::MagnifyingGlass)
                            ->columns(1)
                            ->schema([
                                TextInput::make('meta_title')->maxLength(60),
                                Textarea::make('meta_description')->rows(3)->maxLength(160),
                            ]),
                    ]),
            ]);
    }
}
