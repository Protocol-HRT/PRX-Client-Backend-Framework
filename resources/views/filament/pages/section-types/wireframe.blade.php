{{-- Generalized structural wireframe: one skeleton block per field, in form order. --}}
<div class="rounded-xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-900">
    <div class="space-y-4">
        @forelse ($fields as $field)
            @if ($field['kind'] === 'repeater')
                <div>
                    <p class="mb-1 font-mono text-[10px] uppercase tracking-wide text-gray-400">{{ $field['name'] }}[] · repeats</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="space-y-2 rounded-lg border border-dashed border-gray-300 bg-white p-3 dark:border-gray-600 dark:bg-gray-800">
                                @forelse ($field['children'] as $child)
                                    @include('filament.pages.section-types.wireframe-block', ['field' => $child, 'mini' => true])
                                @empty
                                    <div class="h-2 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                                @endforelse
                            </div>
                        @endfor
                    </div>
                </div>
            @else
                @include('filament.pages.section-types.wireframe-block', ['field' => $field, 'mini' => false])
            @endif
        @empty
            <p class="text-sm text-gray-500">This type declares no editable fields.</p>
        @endforelse
    </div>
</div>
