<?php

namespace App\Providers\Filament;

use App\Filament\Partner\Pages\PartnerDashboard;
use App\Settings\BrandSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The partner portal — affiliates and sales groups.
 *
 * A SECOND PANEL RATHER THAN POLICY SCOPING INSIDE THE ADMIN, and the reason is
 * measurable: the staff panel has 39 resources, 11 pages and 20 relation
 * managers, 25 of which expose patient PII, credentials or margin, and NOT ONE of
 * them scopes rows — the only `getEloquentQuery()` override in the whole admin
 * adds a `withCount`. Retrofitting row scoping onto all of that, and then
 * defending it forever against a 482-permission role editor, is a far larger and
 * more fragile job than a separate panel that starts empty.
 *
 * Filament discovers resources per directory, so `app/Filament/Partner/**` is
 * structurally invisible to the admin panel and vice versa. Same `users` table,
 * same `web` guard, same session — the boundary is `User::canAccessPanel()`,
 * which makes the two audiences mutually exclusive.
 *
 * SHIELD IS DELIBERATELY NOT REGISTERED HERE. Its resource permissions are keyed
 * on the MODEL, so a partner resource over `ReferralSource` would generate the
 * identical `ViewAny:ReferralSource` key the admin already uses — granting it
 * would widen the admin, and generating it would re-stub the admin's policy.
 * Access here is panel membership plus the row scoping every partner resource
 * inherits from PartnerResource. Partner roles hold ZERO permissions on purpose,
 * so even a gate failure lands in an empty panel.
 */
class PartnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('partner')
            ->path('partner')
            ->login()
            ->passwordReset()
            ->profile()
            ->brandName(fn (): string => $this->brandName())
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Partner/Resources'), for: 'App\Filament\Partner\Resources')
            ->discoverPages(in: app_path('Filament/Partner/Pages'), for: 'App\Filament\Partner\Pages')
            ->pages([PartnerDashboard::class])
            // Widgets are NOT discovered here. The dashboard names the ones it
            // wants, and they are the shared, scope-shaped classes from
            // app/Filament/Widgets — discovery would also pull in staff-only
            // widgets that have no scoping at all.
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                ConvertEmptyStringsToNull::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }

    /**
     * Brand name from settings, with a fallback: this runs during install before
     * the settings table exists, exactly as the admin panel's does.
     */
    private function brandName(): string
    {
        try {
            return app(BrandSettings::class)->name ?: config('app.name');
        } catch (\Throwable) {
            return (string) config('app.name');
        }
    }
}
