<?php

namespace App\Filament\Support;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\Contracts\EnrollsInAutomations;
use App\Integrations\Contracts\SyncsContacts;
use App\Integrations\Contracts\TracksEvents;
use App\Integrations\FieldMap;
use App\Integrations\IntegrationRegistry;
use App\Models\Integrations\IntegrationInstance;
use App\Workflows\Actions\PushToIntegrationAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Config forms for the integration-backed workflow actions.
 *
 * ─── Why these are forms and not a key/value editor ────────────────────
 *
 * Every other action's config is a generic key/value box, which is honest for
 * `{"field": "status"}` and useless here: an operator cannot be expected to know
 * that `on_phi` accepts `redact`, or to type an instance slug from memory. More
 * importantly, a free-text box cannot warn about anything — and the one thing
 * this particular config must do is tell somebody, at the moment they choose it,
 * that the field they just mapped is health data going somewhere unattested.
 *
 * ─── The instance list is a query, never a constant ────────────────────
 *
 * Options come from enabled instances offering the required capability. So the
 * palette is a consequence of what the operator has configured rather than a
 * list somebody maintains in code, and switching an integration off removes it
 * from every form that offers it.
 */
class IntegrationActionForms
{
    /** @return list<mixed> */
    public static function sendEmail(): array
    {
        return [
            self::instanceSelect(IntegrationCapability::TransactionalEmail, 'Send through'),

            TextInput::make('config.to')
                ->label('Send to which field')
                ->default('email')
                ->required()
                ->helperText('A field on the record — normally "email".'),

            TextInput::make('config.subject')->label('Subject')->required()->maxLength(200),

            Textarea::make('config.body')->label('Message')->required()->rows(6),
        ];
    }

    /** @return list<mixed> */
    public static function sendSms(): array
    {
        return [
            self::instanceSelect(IntegrationCapability::Sms, 'Send through'),

            TextInput::make('config.to')
                ->label('Send to which field')
                ->default('phone')
                ->required(),

            Textarea::make('config.body')
                ->label('Message')
                ->required()
                ->rows(3)
                ->maxLength(1600)
                ->helperText('Keep it short — long messages are split and billed per part.'),
        ];
    }

    /** @return list<mixed> */
    public static function pushToIntegration(): array
    {
        return [
            self::instanceSelect(IntegrationCapability::Crm, 'Send to')->live(),

            Select::make('config.operation')
                ->label('What to do')
                ->required()
                ->default(PushToIntegrationAction::OP_SYNC_CONTACT)
                ->live()
                // BOUNDED BY WHAT THE CHOSEN PROVIDER CAN ACTUALLY DO. Klaviyo
                // has events and forbids direct enrolment; GoHighLevel is the
                // reverse. Offering both to both would let an operator build a
                // step that can only ever fail.
                ->options(fn (Get $get): array => self::operationsFor($get('config.integration'))),

            TextInput::make('config.group')
                ->label('Add to list or tag')
                ->helperText('The name of a list or tag at the far end. Leave empty to only update the person.')
                ->visible(fn (Get $get): bool => $get('config.operation') === PushToIntegrationAction::OP_SYNC_CONTACT),

            TextInput::make('config.event')
                ->label('Event name')
                ->required()
                ->visible(fn (Get $get): bool => $get('config.operation') === PushToIntegrationAction::OP_TRACK_EVENT),

            TextInput::make('config.automation')
                ->label('Automation ID')
                ->required()
                ->visible(fn (Get $get): bool => $get('config.operation') === PushToIntegrationAction::OP_ENROLL),

            Repeater::make('config.mappings')
                ->label('Fields to send')
                // ZERO BY DEFAULT. A repeater that starts with one blank row
                // saves an empty mapping nobody asked for, which this codebase
                // has been bitten by before.
                ->defaultItems(0)
                ->addActionLabel('Add a field')
                ->columns(3)
                ->schema([
                    Select::make('source')
                        ->label('From')
                        ->required()
                        ->searchable()
                        ->options(fn (): array => self::sourceOptions()),

                    TextInput::make('destination')
                        ->label('To (their field name)')
                        ->required(),

                    Select::make('on_phi')
                        ->label('If health data')
                        ->default(FieldMap::ON_PHI_BLOCK)
                        ->options([
                            FieldMap::ON_PHI_BLOCK => 'Refuse to send',
                            FieldMap::ON_PHI_REDACT => 'Send "[redacted]"',
                            FieldMap::ON_PHI_SEND => 'Send it anyway',
                        ])
                        ->helperText('Only applies when the destination is not marked as permitted for health data.'),
                ]),
        ];
    }

    private static function instanceSelect(IntegrationCapability $capability, string $label): Select
    {
        return Select::make('config.integration')
            ->label($label)
            ->required()
            ->options(fn (): array => app(IntegrationRegistry::class)
                ->instancesOffering($capability)
                ->mapWithKeys(fn (IntegrationInstance $instance): array => [
                    $instance->slug => $instance->name.($instance->phi_permitted ? '' : '  — not permitted for health data'),
                ])
                ->all());
    }

    /** @return array<string, string> */
    private static function operationsFor(mixed $slug): array
    {
        if (! is_string($slug) || $slug === '') {
            return [];
        }

        $instance = IntegrationInstance::query()->where('slug', $slug)->first();

        if ($instance === null) {
            return [];
        }

        $driver = app(IntegrationRegistry::class)->provider($instance->provider)['driver'] ?? null;

        if ($driver === null) {
            return [];
        }

        $operations = [];

        if (is_a($driver, SyncsContacts::class, true)) {
            $operations[PushToIntegrationAction::OP_SYNC_CONTACT] = 'Create or update the person';
        }

        if (is_a($driver, TracksEvents::class, true)) {
            $operations[PushToIntegrationAction::OP_TRACK_EVENT] = 'Record an event';
        }

        if (is_a($driver, EnrollsInAutomations::class, true)) {
            $operations[PushToIntegrationAction::OP_ENROLL] = 'Start an automation';
        }

        return $operations;
    }

    /**
     * Every field an operator may map, LABELLED WITH ITS SENSITIVITY.
     *
     * The label carries the warning because that is where the decision is made.
     * A separate validation message after saving arrives too late to inform the
     * choice, and a note at the top of the form is read once and then not again.
     *
     * @return array<string, string>
     */
    private static function sourceOptions(): array
    {
        $map = app(FieldMap::class);
        $options = [];

        // 'lead' is this install's only mappable subject today. When a second
        // one exists this should read the workflow's own trigger target.
        foreach ($map->sourcesFor('lead') as $key => $source) {
            $suffix = match ($source['class']->value) {
                'phi' => '  ⚕ health data',
                'sensitive' => '  · personal',
                default => '',
            };

            $options[$key] = $source['label'].$suffix;
        }

        return $options;
    }
}
