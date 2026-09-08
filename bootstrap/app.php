<?php

use App\Http\Middleware\EnsurePatientToken;
use App\Http\Middleware\NoStorePhiResponse;
use App\Http\Middleware\VerifyApiClientOrigin;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Inbound webhooks are signed via HMAC and have no browser session,
        // so they're CSRF-exempt. Signature verification happens in the
        // VerifyPrescribeRxSignature middleware on the route itself.
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);

        $middleware->appendToGroup('api', VerifyApiClientOrigin::class);

        $middleware->alias([
            'patient' => EnsurePatientToken::class,
            'no-store' => NoStorePhiResponse::class,
        ]);

        // Laravel sorts route middleware by its priority list, which hoists the
        // authenticator to the front regardless of the order a route declares.
        // That left `no-store` INSIDE the auth check, so a 401 or 403 — the
        // responses that echo an id back to an unauthenticated caller — came
        // back with Laravel's default `no-cache, private` and was storable.
        //
        // The anchor is the CONTRACT, not Illuminate\Auth\Middleware\Authenticate:
        // the default priority list holds the interface, so naming the concrete
        // class matches nothing and the entry is silently appended to the END of
        // the list — the exact opposite of what was asked for, with no error.
        // Asserted by PortalResponseHeadersTest.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: NoStorePhiResponse::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
