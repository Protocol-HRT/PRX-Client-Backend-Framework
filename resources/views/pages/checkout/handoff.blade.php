@php
    /** @var \App\Models\Lead $lead */
    /** @var array $payload */
    /** @var string $environment */
@endphp

<x-layouts.minimal title="Clinical intake">
    <div class="min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-5 sm:px-8 lg:px-10">

            <header class="mb-8">
                <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">
                    Step 2 of 2 · Clinical intake
                </p>
                <h1 class="text-3xl sm:text-4xl font-semibold text-gray-900">
                    Tell us about your health
                </h1>
                <p class="mt-3 text-base text-gray-500 max-w-2xl">
                    The form below is hosted by our partner clinic on a HIPAA-covered platform.
                    Your name, contact, and product selections were already passed in — you'll start at the clinical questions.
                </p>
            </header>

            <x-prx.embed
                :payload="$payload"
                :environment="$environment" />

            <p class="mt-6 text-xs text-center text-gray-400">
                Ref <code class="font-mono">{{ \Illuminate\Support\Str::limit($lead->uuid, 8, '') }}…</code>
                · Subtotal ${{ number_format((float) ($lead->cart_subtotal ?? 0), 2) }}
            </p>
        </div>
    </div>
</x-layouts.minimal>
