{{-- One skeleton block: shape follows the field kind, caption is the payload key. --}}
@php
    $name = $field['name'];
    $kind = $field['kind'];
    $isHeading = (bool) preg_match('/heading|headline|title(?!_)|^label$/', $name);
    $isCta = (bool) preg_match('/cta|button|url|link/', $name);
@endphp

<div>
    @unless ($mini)
        <p class="mb-1 font-mono text-[10px] uppercase tracking-wide text-gray-400">{{ $name }} · {{ $kind }}</p>
    @endunless

    @if (in_array($kind, ['image', 'svg'], true))
        <div @class(['flex items-center justify-center rounded-lg bg-gray-200 dark:bg-gray-700', 'h-10' => $mini, 'h-28' => ! $mini])>
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Z" />
            </svg>
        </div>
    @elseif (in_array($kind, ['rich text', 'textarea'], true))
        <div class="space-y-1.5">
            <div class="h-2.5 w-full rounded bg-gray-300 dark:bg-gray-600"></div>
            <div class="h-2.5 w-11/12 rounded bg-gray-300 dark:bg-gray-600"></div>
            @unless ($mini)
                <div class="h-2.5 w-3/4 rounded bg-gray-300 dark:bg-gray-600"></div>
            @endunless
        </div>
    @elseif (in_array($kind, ['toggle', 'checkbox'], true))
        <div class="flex items-center gap-2">
            <div class="h-4 w-8 rounded-full bg-gray-300 dark:bg-gray-600"></div>
            @if ($mini)<span class="font-mono text-[10px] text-gray-400">{{ $name }}</span>@endif
        </div>
    @elseif (in_array($kind, ['select', 'checkbox list'], true))
        <div class="h-6 w-32 rounded-md border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800"></div>
    @elseif (in_array($kind, ['products', 'packages'], true))
        <div class="flex gap-2">
            @for ($i = 0; $i < 3; $i++)
                <div class="h-14 w-24 rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800"></div>
            @endfor
        </div>
    @elseif ($isCta)
        <div @class(['inline-block rounded-full bg-gray-800 dark:bg-gray-300', 'h-4 w-16' => $mini, 'h-8 w-32' => ! $mini])></div>
    @elseif ($isHeading)
        <div @class(['rounded bg-gray-400 dark:bg-gray-500', 'h-3 w-2/3' => $mini, 'h-5 w-2/3' => ! $mini])></div>
    @else
        <div @class(['rounded bg-gray-300 dark:bg-gray-600', 'h-2.5 w-1/2' => $mini, 'h-3.5 w-1/2' => ! $mini])></div>
    @endif

    @if ($mini && ! in_array($kind, ['toggle', 'checkbox'], true))
        <p class="mt-0.5 font-mono text-[9px] text-gray-400">{{ $name }}</p>
    @endif
</div>
