<?php

namespace App\Providers;

use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Cms\FlexibleSectionType;
use App\Models\Cms\GlobalSection;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\RegionItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Observers\CmsCacheObserver;
use App\Observers\PageSectionObserver;
use App\Services\Cms\PageRevisionService;
use App\Services\Cms\SectionRegistry;
use App\Services\Payments\PaymentGatewayManager;
use App\Settings\BrandSettings;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(SectionRegistry::class);
        $this->app->singleton(PageRevisionService::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureApiDocs();
        $this->configureCmsObservers();
        $this->configureBrandMailFrom();
    }

    /**
     * Outbound mail carries the install's brand name, not the .env app name.
     * Rescue-guarded: the settings table doesn't exist during install.
     */
    private function configureBrandMailFrom(): void
    {
        $brandName = rescue(fn (): ?string => app(BrandSettings::class)->name, null, false);

        if (filled($brandName)) {
            config(['mail.from.name' => $brandName]);
        }
    }

    private function configureCmsObservers(): void
    {
        // Any CMS content write bumps the versioned public-payload cache.
        Page::observe(CmsCacheObserver::class);
        PageSection::observe(CmsCacheObserver::class);
        PageSection::observe(PageSectionObserver::class);
        FlexibleSectionType::observe(CmsCacheObserver::class);
        GlobalSection::observe(CmsCacheObserver::class);
        Menu::observe(CmsCacheObserver::class);
        MenuItem::observe(CmsCacheObserver::class);
        RegionItem::observe(CmsCacheObserver::class);

        // Menu items reference linkable entities by short alias so DB rows
        // don't couple to class names. Non-enforcing: other morphs (tags,
        // categories pivots) keep their stored class-name behavior.
        Relation::morphMap([
            'page' => Page::class,
            'product' => Product::class,
            'package' => Package::class,
            'catalog_category' => Category::class,
            'blog_post' => BlogPost::class,
            'blog_category' => BlogCategory::class,
        ]);
    }

    private function configureApiDocs(): void
    {
        // Serve the Scalar UI at /api/docs and the raw OpenAPI JSON at /api/docs.json.
        // Public in all environments — the spec contains no secrets.
        Scramble::configure()
            ->expose(ui: 'api/docs', document: 'api/docs.json');

        Gate::define('viewApiDocs', fn () => true);

        // Map controller namespaces to human-readable tag groups in Scalar UI.
        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo): array {
            $class = $routeInfo->className() ?? '';

            return match (true) {
                str_contains($class, 'Api\V1\Auth\\') => ['Auth'],
                str_contains($class, 'Api\V1\Blog\\') => ['Blog'],
                str_contains($class, 'Api\V1\Catalog\\') => ['Catalog'],
                str_contains($class, 'Api\V1\Cart\\') => ['Cart'],
                str_contains($class, 'Api\V1\Checkout\\') => ['Checkout'],
                str_contains($class, 'Api\V1\Cms\\') => ['CMS'],
                str_contains($class, 'Api\V1\Content\\') => ['Content'],
                str_contains($class, 'Api\V1\Intake\\') => ['Intake'],
                str_contains($class, 'Api\V1\Leads\\') => ['Leads'],
                str_contains($class, 'Api\V1\Orders\\') => ['Orders'],
                str_contains($class, 'Api\V1\Patient') && str_ends_with($class, 'AuthController') => ['PatientAuth'],
                str_contains($class, 'Api\V1\Patient\\') => ['PatientPortal'],
                str_contains($class, 'Api\V1\Webhooks\\') => ['Webhooks'],
                str_contains($class, 'ConfigController') => ['Config'],
                default => ['General'],
            };
        });
    }

    private function configureRateLimiters(): void
    {
        // Strict limit on auth endpoints to prevent brute-force.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // General API limit — generous enough for a React SPA, tight enough to block scrapers.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by(
            $request->user()?->id ?? $request->ip()
        ));
    }
}
