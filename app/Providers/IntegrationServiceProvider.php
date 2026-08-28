<?php

namespace App\Providers;

use App\Integrations\Drivers\LocalMailDriver;
use App\Integrations\IntegrationRegistry;
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
    }
}
