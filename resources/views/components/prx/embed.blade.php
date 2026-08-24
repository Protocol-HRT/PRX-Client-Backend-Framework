{{--
    PrescribeRx embed iframe wrapper.

    Required props:
      $payload — array from PrxEmbedPayloadBuilder->forLead($lead). Keys:
                 embedCode, prefill, packages, products, planIds,
                 skipSteps, metadata.
      $environment — 'sandbox' | 'production' (drives which sdk.js + iframe
                     origin to load).
      $completeUrl — POST endpoint our parent page pings with the encounter
                     ID returned by onComplete (best-effort UI sync; the
                     webhook is source of truth).
--}}
@props([
    'payload',
    'environment' => 'sandbox',
    'completeUrl' => null,
])

@php
    $sdkBase = $environment === 'production'
        ? 'https://prescribe-rx.com'
        : 'https://demo.prescribe-rx.com';

    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $completeUrl ??= url('/api/internal/checkout/embed-complete');
@endphp

<div class="space-y-4">
    @if (empty($payload['embedCode']))
        <div class="rounded-xl border-2 border-dashed p-10 text-center"
             style="border-color: rgba(220,38,38,0.3); background: rgba(220,38,38,0.04);">
            <p class="font-display font-semibold text-lg" style="color: #b91c1c;">Embed code not configured</p>
            <p class="font-body text-sm mt-2" style="color: #6a6a6a;">
                Set <code class="font-mono">prescribe_rx_embed_code</code> in
                <a href="{{ url('/admin/settings/integrations') }}" class="underline" target="_blank">/admin/settings/integrations</a>
                to load the embed.
            </p>
        </div>
    @else
        {{-- The SDK injects the iframe inside this element. --}}
        <div id="prx-intake"
             class="rounded-xl overflow-hidden border min-h-[640px]"
             style="border-color: rgba(0,0,0,0.08); background: #ffffff;">
            <div class="flex items-center justify-center py-20">
                <span class="font-body text-sm" style="color: #6a6a6a;">Loading clinical intake…</span>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    @if (! empty($payload['embedCode']))
        <script src="{{ $sdkBase }}/embed/sdk.js" async></script>
        <script>
            (function () {
                const payload = @json($payload);
                const completeUrl = @json($completeUrl);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                // Guard: PRX may fire onReady more than once if the iframe's
                // inner Livewire components remount. Without this flag, every
                // re-mount would re-fire prefill / selectProducts /
                // setSkipSteps — each call is a postMessage + Livewire
                // roundtrip on the PRX side, producing a request storm.
                let isConfigured = false;
                let isInitialized = false;

                function bootEmbed() {
                    if (!window.PrescribeRx || typeof window.PrescribeRx.init !== 'function') {
                        // SDK not yet ready — try again on next frame.
                        return setTimeout(bootEmbed, 50);
                    }

                    if (isInitialized) return;
                    isInitialized = true;

                    window.PrescribeRx.init('prx-intake', {
                        embedCode: payload.embedCode,

                        onReady() {
                            // First ready only — bail on subsequent fires.
                            // The SDK's internal state already reflects the
                            // values we pushed; re-pushing them just churns.
                            if (isConfigured) {
                                return;
                            }
                            isConfigured = true;

                            try {
                                if (payload.prefill && Object.keys(payload.prefill).length) {
                                    window.PrescribeRx.prefill(payload.prefill);
                                }
                                if (payload.packages && payload.packages.length) {
                                    window.PrescribeRx.selectPackages(payload.packages);
                                }
                                if (payload.products && payload.products.length) {
                                    window.PrescribeRx.selectProducts(payload.products);
                                }
                                if (payload.planIds && payload.planIds.length === 1) {
                                    window.PrescribeRx.selectPlan(payload.planIds[0]);
                                }
                                if (payload.skipSteps && payload.skipSteps.length) {
                                    window.PrescribeRx.setSkipSteps(payload.skipSteps);
                                }
                            } catch (e) {
                                console.error('[prx-embed] error during onReady:', e);
                                // Allow retry on next ready if the first run threw.
                                isConfigured = false;
                            }
                        },

                        onStepChange(data) {
                            // Useful hook for analytics later. Avoid logging
                            // payload bodies — they may contain PHI.
                            console.debug('[prx-embed] step', data.step + '/' + data.total, data.stepName);
                        },

                        onComplete(data) {
                            console.info('[prx-embed] intake complete', {
                                encounter_id: data?.encounter_id,
                                patient_id: data?.patient_id,
                            });

                            // Best-effort POST to our server so the UI can
                            // update before the webhook lands. The server
                            // treats this as advisory — webhook is source of
                            // truth for status. Never trust onComplete data
                            // for billing or fulfillment decisions.
                            if (completeUrl && csrfToken) {
                                fetch(completeUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                    },
                                    body: JSON.stringify({
                                        lead_uuid: payload.metadata?.lead_uuid,
                                        encounter_id: data?.encounter_id,
                                        patient_id: data?.patient_id,
                                    }),
                                    credentials: 'same-origin',
                                }).catch((err) => console.warn('[prx-embed] complete-ping failed', err));
                            }
                        },

                        onError(data) {
                            console.error('[prx-embed] error:', data?.message || data);
                        },
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootEmbed);
                } else {
                    bootEmbed();
                }
            })();
        </script>
    @endif
@endpush
