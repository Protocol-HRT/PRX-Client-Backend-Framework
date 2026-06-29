<?php

namespace App\Providers;

use App\Services\Payments\PaymentGatewayManager;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureApiDocs();
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
