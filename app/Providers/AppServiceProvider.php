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
use Awcodes\Curator\Config\GlideManager;
use Awcodes\Curator\Glide\SymfonyResponseFactory;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;

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
        $this->configureGlideCache();
        $this->configureFilamentModalDefaults();
    }

    /**
     * Filament's default create/edit modal is too narrow for real content
     * forms — fields quarter down and wrap. Default every Create/Edit modal
     * to 7xl; individual actions can still override (the section relation
     * managers go full-screen). Pages (non-modal) ignore the width entirely.
     */
    private function configureFilamentModalDefaults(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action) => $action->modalWidth(Width::SevenExtraLarge));
        EditAction::configureUsing(fn (EditAction $action) => $action->modalWidth(Width::SevenExtraLarge));
    }

    /**
     * Curator's default Glide transform cache breaks whenever two server
     * users share it (php-fpm's www-data vs `artisan serve`'s shell user):
     * Flysystem's local adapter creates intermediate cache directories with
     * PRIVATE (700) visibility, so whichever user transforms an image first
     * locks the other out and every later thumbnail 500s with "Could not
     * write the image".
     *
     * Loosening permissions can't fix this reliably — Flysystem creates
     * intermediate dirs via mkdir(), whose mode is masked by the process
     * umask regardless of any visibility converter. Instead, LOCAL gets a
     * per-process-user cache subtree (shell user and web-server user each
     * own their whole tree; a few duplicate cached thumbnails are the only
     * cost). Production runs a single server user and uses the shared root
     * with Flysystem's restrictive defaults.
     */
    private function configureGlideCache(): void
    {
        $cachePath = storage_path('app/glide-cache');

        if (app()->environment('local') && function_exists('posix_geteuid')) {
            $user = posix_getpwuid(posix_geteuid())['name'] ?? 'shared';
            $cachePath .= '/'.$user;
        }

        app(GlideManager::class)->serverConfig([
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => storage_path('app'),
            'source_path_prefix' => 'public',
            'cache' => new Filesystem(new LocalFilesystemAdapter($cachePath)),
            'max_image_size' => 2000 * 2000,
            'base_url' => 'curator',
        ]);
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
