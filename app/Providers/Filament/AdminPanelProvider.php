<?php

namespace App\Providers\Filament;

use App\Settings\BrandSettings;
use Awcodes\Curator\CuratorPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            // White-label chrome from DB-driven BrandSettings; rescue-guarded
            // because the settings table doesn't exist yet during install.
            ->brandName(fn (): string => $this->brandValue('name') ?: config('app.name'))
            ->brandLogo(fn (): ?string => $this->brandAssetUrl('logo_path'))
            ->darkModeBrandLogo(fn (): ?string => $this->brandAssetUrl('logo_dark_path'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn (): ?string => $this->brandAssetUrl('favicon_path'))
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
                'info' => Color::Cyan,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            // Content spans the viewport (and reflows when the sidebar
            // collapses) instead of Filament's boxed default width.
            ->maxContentWidth(Width::Full)
            // Groups need icons for the collapsed sidebar to render them as
            // hover/click dropdown flyouts — without one, every child page
            // becomes its own icon in a very long rail.
            ->navigationGroups([
                NavigationGroup::make('Content')->icon('heroicon-o-document-duplicate'),
                NavigationGroup::make('Blog')->icon('heroicon-o-pencil-square'),
                NavigationGroup::make('Shop')->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make('Commerce')->icon('heroicon-o-shopping-cart'),
                NavigationGroup::make('Payments')->icon('heroicon-o-credit-card'),
                NavigationGroup::make('Leads')->icon('heroicon-o-user-plus'),
                NavigationGroup::make('Patients')->icon('heroicon-o-heart'),
                NavigationGroup::make('Users & access')->icon('heroicon-o-users'),
                NavigationGroup::make('Developer')->icon('heroicon-o-code-bracket'),
                NavigationGroup::make('Settings')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Users & access')
                    ->navigationSort(20),
                CuratorPlugin::make()->navigationGroup('Content'),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function brandValue(string $property): ?string
    {
        return rescue(fn (): ?string => app(BrandSettings::class)->{$property}, null, false);
    }

    private function brandAssetUrl(string $property): ?string
    {
        $path = $this->brandValue($property);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
