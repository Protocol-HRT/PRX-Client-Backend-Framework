<?php

use App\Http\Middleware\EnsurePatientToken;
use App\Http\Middleware\VerifyApiClientOrigin;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
