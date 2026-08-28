<?php

namespace App\Providers;

use App\Integrations\Drivers\GoHighLevelDriver;
use App\Integrations\Drivers\KlaviyoDriver;
use App\Integrations\Drivers\LocalMailDriver;
use App\Integrations\Drivers\TwilioDriver;
use App\Integrations\IntegrationRegistry;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the integration catalogue, and declares which vendors THIS build ships
 * drivers for.
 *
 * THE SPLIT MIRRORS WorkflowServiceProvider'S, on purpose. Everything in
 * `App\Integrations` is generic and knows no vendor; this file is where names
 * appear. Another company deploying this backend adds their own provider here —
 * or registers from their own package — and the admin form, the capability
 * filter and the workflow action palette all follow, with no fork.
 *
 * A provider registered here is only an OFFER. Nothing happens until an operator
 * creates an instance, pastes credentials and ticks the capabilities their
 * account is actually authorised for.
 *
 * ─── On credential schemas ─────────────────────────────────────────────
 *
 * Each provider declares its own secret fields as Filament components rather
 * than the form rendering a generic key/value editor over the credentials blob.
 * Three reasons, all learned here: a key/value editor cannot mask one field, it
 * shows every secret at once, and it rewrites the whole blob on save — so a
 * masked placeholder would overwrite the real secret with the mask. The closure
 * keeps this file usable outside the admin panel, where Filament is not booted.
 */
class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationRegistry::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(IntegrationRegistry::class);

        $this->registerFirstPartyProviders($registry);
    }

    private function registerFirstPartyProviders(IntegrationRegistry $registry): void
    {
        $registry->registerProvider(
            'local_mail',
            LocalMailDriver::class,
            'This site\'s own email',
            'Sends through the mail provider configured in Settings → Communications. No extra account '
            .'needed, and no credentials to paste here. Transactional messages only — it cannot honour '
            .'a marketing unsubscribe, so it does not offer marketing email.',
            // No credential fields: the keys live in Communications settings,
            // and a second copy here could disagree with them.
            credentials: fn (): array => [],
            settings: fn (): array => [
                TextInput::make('settings.from_name')
                    ->label('From name override')
                    ->helperText('Leave empty to use the brand name from Settings → Brand.')
                    ->maxLength(120),
            ],
        );

        $registry->registerProvider(
            'klaviyo',
            KlaviyoDriver::class,
            'Klaviyo',
            'Profiles, lists and events. Klaviyo has no way to push somebody straight into a flow — '
            .'record an event and let the flow trigger on it instead.',
            credentials: fn (): array => [
                TextInput::make('credentials.private_key')
                    ->label('Private API key')
                    ->password()
                    ->revealable()
                    ->required()
                    // THE SCOPES ARE NAMED HERE BECAUSE "Test connection" CANNOT
                    // CATCH A MISSING ONE. It reads /accounts/, which needs only
                    // `accounts:read` — so a read-only key reports a healthy
                    // connection and then 403s on every write. That happened on
                    // this install: the first key carried no write scope at all,
                    // and no lead had ever reached Klaviyo.
                    ->helperText('Starts with pk_. Give it these scopes when you create it: '
                        .'accounts:read, profiles:write, lists:write, subscriptions:write, events:write. '
                        .'Scopes are FIXED when a key is created — to change them, mint a new key and '
                        .'paste it here; this one cannot be widened. Note that "Test connection" only '
                        .'proves the key is valid: it cannot see which scopes the key has, so a read-only '
                        .'key passes the test and then fails everything else.'),
            ],
            settings: fn (): array => [
                // The name → id map. Without it, every workflow step would have
                // to carry an opaque list id, and rebuilding a list would break
                // every step that named it.
                Repeater::make('settings.lists')
                    ->label('List names')
                    ->helperText('Give your Klaviyo lists names your workflows can use. A workflow step says '
                        .'"quiz-completers"; this is where that becomes a list ID.')
                    ->defaultItems(0)
                    ->addActionLabel('Map a list')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Name used in workflows')->required(),
                        TextInput::make('list_id')->label('Klaviyo list ID')->required(),
                    ]),
            ],
        );

        $registry->registerProvider(
            'gohighlevel',
            GoHighLevelDriver::class,
            'GoHighLevel',
            'Contacts, tags and workflow enrolment. GoHighLevel has no events API — start one of its '
            .'workflows instead.',
            credentials: fn (): array => [
                TextInput::make('credentials.access_token')
                    ->label('API token')
                    ->password()
                    ->revealable()
                    ->required(),
            ],
            settings: fn (): array => [
                TextInput::make('settings.location_id')
                    ->label('Location ID')
                    ->required()
                    ->helperText('The sub-account these contacts belong to. Every call is scoped to it, so '
                        .'nothing works without it. Not a secret — it names the account, it does not open it.'),

                TextInput::make('settings.source')
                    ->label('Source label')
                    ->helperText('Optional. Recorded against each contact so you can tell where they came from.')
                    ->maxLength(120),
            ],
        );

        $registry->registerProvider(
            'twilio',
            TwilioDriver::class,
            'Twilio',
            'Text messages. Twilio will sign a business associate agreement, so this is one of the few '
            .'channels where health content can be legitimate — if your own contract covers it.',
            credentials: fn (): array => [
                TextInput::make('credentials.account_sid')
                    ->label('Account SID')
                    ->required()
                    ->helperText('Starts with AC.'),

                TextInput::make('credentials.auth_token')
                    ->label('Auth token')
                    ->password()
                    ->revealable()
                    ->required(),
            ],
            settings: fn (): array => [
                TextInput::make('settings.messaging_service_sid')
                    ->label('Messaging Service SID')
                    ->helperText('Preferred over a single number: it carries your sender pool, opt-out '
                        .'handling and compliance registration. Starts with MG.'),

                TextInput::make('settings.from_number')
                    ->label('From number')
                    ->helperText('Used only when there is no Messaging Service. E.164 format, e.g. +15550123.'),
            ],
        );
    }
}
