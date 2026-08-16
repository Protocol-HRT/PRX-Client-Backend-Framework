{{-- Example API payload for one section type, as shipped by /api/v1. --}}
<div class="space-y-3">
    @if ($source === 'live')
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Live example — serialized from an existing section of this type through the real API pipeline, exactly as the frontend receives it inside a page's <code class="font-mono text-xs">sections[]</code> array.
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Synthesized example — no section of this type exists yet, so values are placeholders. The structure and keys match what the API will ship once one is created.
        </p>
    @endif

    @if ($payload === null)
        <p class="text-sm text-danger-600">Could not build an example payload for this type.</p>
    @else
        <pre class="max-h-[60vh] overflow-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed text-gray-100">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    @endif
</div>
