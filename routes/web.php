<?php

use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Prescribe-RX checkout handoff — server-rendered embed page.
// This is the only server-rendered public page in the application; everything
// else is served by the decoupled React frontend consuming /api/v1/* routes.
// ---------------------------------------------------------------------------
Route::get('/checkout/handoff/{lead:uuid}', function (\App\Models\Lead $lead) {
    $settings = app(\App\Settings\IntegrationSettings::class);
    $payload  = app(\App\Services\PrescribeRx\Embed\PrxEmbedPayloadBuilder::class)->forLead($lead);

    return view('pages.checkout.handoff', [
        'lead'        => $lead,
        'payload'     => $payload,
        'environment' => $settings->prescribe_rx_environment,
    ]);
})->name('checkout.handoff');

// Internal advisory ping from the prescribe-rx embed's onComplete callback.
// NOT authoritative — the signed webhook below is the source of truth.
Route::post('/api/internal/checkout/embed-complete', \App\Http\Controllers\PrescribeRx\EmbedCompleteController::class)
    ->name('checkout.embed-complete');

// Prescribe-RX webhook receiver — HMAC signature verified by middleware.
// CSRF exempt (set in bootstrap/app.php). Handles encounter / order / shipment
// status events. Idempotent; at-least-once delivery from PRX.
Route::post('/api/webhooks/prescribe-rx', \App\Http\Controllers\PrescribeRx\WebhookController::class)
    ->middleware(\App\Http\Middleware\VerifyPrescribeRxSignature::class)
    ->name('webhooks.prescribe-rx');
