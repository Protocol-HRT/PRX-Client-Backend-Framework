<?php

namespace App\Filament\Resources\Integrations\Schemas;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\IntegrationRegistry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * One configured integration.
 *
 * ─── Two things this form deliberately does NOT contain ────────────────
 *
 * `phi_permitted` — because it is an attestation, not a preference. It is set
 * through an action on the edit page that records who said so and why, and a
 * checkbox saved alongside a display name would capture neither. See
 * EditIntegrationInstance.
 *
 * A key/value editor over `credentials` — because such an editor cannot mask one
 * field, shows every secret at once, and rewrites the whole blob on save, so a
 * masked placeholder would overwrite the real secret with the mask. Each driver
 * declares its own credential inputs instead, and they render below.
 */
class IntegrationInstanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What this is')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(120)
                        ->helperText('Your name for it — "Klaviyo — Marketing". Shown wherever you pick a destination.'),

                    Select::make('provider')
                        ->label('Service')
                        ->required()
                        ->live()
                        ->native(false)
                        ->options(fn (): array => app(IntegrationRegistry::class)->providerOptions())
                        ->helperText(fn (Get $get): ?string => app(IntegrationRegistry::class)
                            ->provider((string) $get('provider'))['description'] ?? null),

                    TextInput::make('slug')
                        ->label('Identifier')
                        ->helperText('Used by your workflows to refer to this. Leave empty and one is made from the name. It cannot be changed once a workflow uses it.')
                        ->maxLength(120)
                        ->disabled(fn (?string $state): bool => filled($state))
                        ->dehydrated(),

                    Toggle::make('is_active')
                        ->label('Enabled')
                        ->default(true)
                        ->helperText('Switching this off removes it from every workflow step that offers it.'),
                ]),

            Section::make('What you use it for')
                ->description('Tick only what your account with this provider is actually authorised to do. A step is offered only when some enabled integration can do what it needs.')
                ->schema([
                    CheckboxList::make('capabilities')
                        ->hiddenLabel()
                        ->columns(2)
                        // BOUNDED BY WHAT THE DRIVER IMPLEMENTS. Offering a
                        // capability the code cannot perform would let an
                        // operator build a step that can only ever fail.
                        ->options(fn (Get $get): array => collect(
                            app(IntegrationRegistry::class)->capabilitiesFor((string) $get('provider'))
                        )->mapWithKeys(fn (IntegrationCapability $c): array => [$c->value => $c->label()])->all())
                        ->descriptions(fn (Get $get): array => collect(
                            app(IntegrationRegistry::class)->capabilitiesFor((string) $get('provider'))
                        )->mapWithKeys(fn (IntegrationCapability $c): array => [$c->value => $c->description()])->all()),
                ]),

            Section::make('Credentials')
                ->description('Stored encrypted. Only this server can read them.')
                ->visible(fn (Get $get): bool => filled(
                    app(IntegrationRegistry::class)->provider((string) $get('provider'))['credentials'] ?? null
                ))
                ->schema(fn (Get $get): array => self::driverSchema($get('provider'), 'credentials')),

            Section::make('Options')
                ->visible(fn (Get $get): bool => self::driverSchema($get('provider'), 'settings') !== [])
                ->schema(fn (Get $get): array => self::driverSchema($get('provider'), 'settings')),
        ]);
    }

    /** @return list<mixed> */
    private static function driverSchema(mixed $provider, string $which): array
    {
        if (! is_string($provider) || $provider === '') {
            return [];
        }

        $closure = app(IntegrationRegistry::class)->provider($provider)[$which] ?? null;

        return $closure === null ? [] : (array) $closure();
    }
}
