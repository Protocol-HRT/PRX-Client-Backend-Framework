{{--
    The lead's quiz answers, rendered from the `quiz_answers` JSON column.

    A JSON column is the right storage: a new question is a row in the quiz, not
    a migration here. The cost is that the raw value is unreadable, so labels are
    resolved through Lead::quizAnswersForDisplay().

    An answer whose question has since been retired still renders, marked. That
    is deliberate — it is real data about a real person, and hiding it because
    the quiz moved on would quietly shrink the marketing record.
--}}
@php
    $rows = $getRecord()?->quizAnswersForDisplay() ?? [];
@endphp

<div class="fi-sc-component">
    @if (empty($rows))
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                This lead did not come through the quiz, so there are no answers to show.
            </p>
        </div>
    @else
        <dl class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
            @foreach ($rows as $row)
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">
                        {{ $row['label'] }}

                        @if ($row['retired'])
                            <span
                                class="ml-1 inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-400/10 dark:text-amber-400"
                                title="This question is no longer part of the quiz. The answer is kept because it is still real data about this person."
                            >retired</span>
                        @endif

                        <span class="mt-0.5 block font-mono text-xs text-gray-400 dark:text-gray-500">
                            {{ $row['slug'] }}
                        </span>
                    </dt>

                    <dd class="text-sm text-gray-950 sm:col-span-2 dark:text-white">
                        {{ $row['value'] !== '' ? $row['value'] : '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
