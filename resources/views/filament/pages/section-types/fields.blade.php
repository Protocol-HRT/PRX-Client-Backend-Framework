{{-- Read-only field inventory for one section type. --}}
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                <th class="py-2 pe-4 font-medium">Field key</th>
                <th class="py-2 pe-4 font-medium">Label</th>
                <th class="py-2 pe-4 font-medium">Kind</th>
                <th class="py-2 font-medium">Required</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($fields as $field)
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="py-2 pe-4 font-mono text-xs">{{ $field['name'] }}</td>
                    <td class="py-2 pe-4">{{ $field['label'] }}</td>
                    <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ $field['kind'] }}</td>
                    <td class="py-2">
                        @if ($field['required'] === true)
                            <span class="text-danger-600 dark:text-danger-400">required</span>
                        @elseif ($field['required'] === null)
                            <span class="text-gray-400">conditional</span>
                        @else
                            <span class="text-gray-400">optional</span>
                        @endif
                    </td>
                </tr>
                @foreach ($field['children'] as $child)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-2 pe-4 font-mono text-xs">
                            <span class="text-gray-400">{{ $field['name'] }}.*.</span>{{ $child['name'] }}
                        </td>
                        <td class="py-2 pe-4">{{ $child['label'] }}</td>
                        <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ $child['kind'] }}</td>
                        <td class="py-2">
                            @if ($child['required'] === true)
                                <span class="text-danger-600 dark:text-danger-400">required</span>
                            @elseif ($child['required'] === null)
                                <span class="text-gray-400">conditional</span>
                            @else
                                <span class="text-gray-400">optional</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="4" class="py-4 text-gray-500">This type declares no editable fields.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
