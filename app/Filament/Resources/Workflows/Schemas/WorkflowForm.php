<?php

namespace App\Filament\Resources\Workflows\Schemas;

use App\Workflows\WorkflowRegistry;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The workflow builder.
 *
 * EVERY OPTION LIST IS READ FROM THE REGISTRY, never hardcoded. That is what
 * makes this screen the same screen for an install that watches leads and one
 * that watches something we have never heard of.
 *
 * The trigger and its target are two dependent selects because the target's
 * meaning changes with the type: a model trigger targets a SUBJECT ('lead'), an
 * event trigger targets an EVENT ('lead.disposition_changed'). Collapsing them
 * into one list would mix two vocabularies and make the condition fields — which
 * depend on the resolved subject — impossible to offer correctly.
 */
class WorkflowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('When this happens')
                    ->description('Pick what to watch. Conditions below narrow it further.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $get, $set, $record): void {
                                if ($record === null && blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->hintIcon(Heroicon::InformationCircle, 'Stable key for this workflow. Used in logs.'),

                        Select::make('trigger_type')
                            ->label('Trigger')
                            ->options([
                                'event_fired' => 'When something happens (recommended)',
                                'model_created' => 'When a record is created',
                                'model_updated' => 'When a record is updated',
                                'model_deleted' => 'When a record is deleted',
                            ])
                            ->default('event_fired')
                            ->required()
                            ->native(false)
                            ->live()
                            // The target list is meaningless against the previous
                            // type, and a stale value would silently point the
                            // workflow at nothing.
                            ->afterStateUpdated(fn ($set) => $set('trigger_target', null))
                            ->helperText('Events are named moments like "lead captured". Record triggers fire on any create or update of that record.'),

                        Select::make('trigger_target')
                            ->label(fn ($get): string => $get('trigger_type') === 'event_fired' ? 'Which event' : 'Which record')
                            ->options(function ($get): array {
                                $registry = app(WorkflowRegistry::class);

                                if ($get('trigger_type') === 'event_fired') {
                                    return array_map(fn (array $d): string => $d['label'], $registry->events());
                                }

                                return array_map(fn (array $d): string => $d['label'], $registry->subjects());
                            })
                            ->required()
                            ->native(false)
                            ->live()
                            // Conditions are written against the previous subject's
                            // fields; kept, they read null forever and the workflow
                            // silently never matches.
                            ->afterStateUpdated(fn ($set) => $set('conditions', []))
                            ->searchable(),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('For whoever inherits this funnel.'),
                    ]),

                Section::make('…and all of this is true')
                    ->description('Leave empty to run every time. Conditions are combined with AND.')
                    ->schema([
                        Repeater::make('conditions')
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('Add a condition')
                            ->columns(3)
                            ->schema([
                                Select::make('field')
                                    ->options(fn ($get): array => self::fieldOptions($get('../../trigger_type'), $get('../../trigger_target')))
                                    ->required()
                                    ->native(false)
                                    ->searchable(),

                                Select::make('operator')
                                    ->options([
                                        'equals' => 'is',
                                        'not_equals' => 'is not',
                                        'contains' => 'includes',
                                        'not_contains' => 'does not include',
                                    ])
                                    ->default('equals')
                                    ->required()
                                    ->native(false),

                                TextInput::make('value')
                                    ->helperText('Leave blank to match an empty value.'),
                            ])
                            ->itemLabel(fn (array $state): ?string => filled($state['field'] ?? null)
                                ? trim(($state['field'] ?? '').' '.str_replace('_', ' ', $state['operator'] ?? 'equals').' '.($state['value'] ?? ''))
                                : null),
                    ]),

                Section::make('Then do this')
                    ->description('Steps run in order. A failure does not stop the next step unless you say so.')
                    ->schema([
                        Repeater::make('actions')
                            ->relationship()
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('Add a step')
                            ->orderColumn('sort_order')
                            ->columns(2)
                            ->schema([
                                Select::make('action_type')
                                    ->label('Step')
                                    ->options(fn (): array => app(WorkflowRegistry::class)->actionOptions())
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->helperText(fn ($state): ?string => app(WorkflowRegistry::class)->actions()[$state]['description'] ?? null),

                                TextInput::make('name')
                                    ->label('Label')
                                    ->maxLength(255)
                                    ->helperText('Optional. Shows in the run log.'),

                                // A generic key/value editor rather than a bespoke
                                // form per action type. The handler owns the shape
                                // of its config, and a product that grows action
                                // types by registration cannot have a hardcoded
                                // form for each. A per-type schema is the obvious
                                // next improvement; it is not required for correctness.
                                KeyValue::make('config')
                                    ->keyLabel('Setting')
                                    ->valueLabel('Value')
                                    ->columnSpanFull()
                                    ->helperText(fn ($get): string => match ($get('action_type')) {
                                        'update_field' => 'field = the field to set, value = what to set it to.',
                                        'webhook' => 'url = where to POST. Optional: method, timeout.',
                                        'dispatch_job' => 'job = the registered job key.',
                                        default => 'Settings for this step.',
                                    }),

                                Toggle::make('is_active')->default(true),
                                Toggle::make('halt_on_failure')
                                    ->label('Stop the workflow if this fails'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['name']
                                ?: (app(WorkflowRegistry::class)->actionOptions()[$state['action_type'] ?? ''] ?? null)),
                    ]),

                Section::make('Behaviour')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')->default(true),

                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Lower runs first.'),

                        Toggle::make('stop_on_first_match')
                            ->label('Stop later workflows')
                            ->helperText('If this one matches, workflows after it on the same trigger are skipped.'),
                    ]),
            ]);
    }

    /**
     * Condition fields for the resolved subject, plus the change pseudo-fields.
     *
     * The `_original.` entries are what make transitions expressible — "moved to
     * X *from* Y" — without the operator needing to know that is a different kind
     * of thing from an ordinary field read.
     */
    private static function fieldOptions(?string $triggerType, ?string $triggerTarget): array
    {
        if ($triggerTarget === null) {
            return [];
        }

        $registry = app(WorkflowRegistry::class);

        $subjectKey = $triggerType === 'event_fired'
            ? ($registry->event($triggerTarget)['subject'] ?? null)
            : $triggerTarget;

        if ($subjectKey === null) {
            return [];
        }

        $fields = $registry->fieldsFor($subjectKey);
        $options = [];

        foreach ($fields as $field => $label) {
            $options[$field] = $label;
        }

        foreach ($fields as $field => $label) {
            $options['_original.'.$field] = $label.' — previous value';
        }

        foreach ($fields as $field => $label) {
            $options['_changed.'.$field] = $label.' — was just changed (1 or blank)';
        }

        return $options;
    }
}
