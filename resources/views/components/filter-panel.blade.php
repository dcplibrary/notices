@props([
    'action' => null,
    'method' => 'GET',
    'searchLabel' => 'Search',
    'searchName' => 'search',
    'searchValue' => '',
    'searchPlaceholder' => 'Search...',
    'dateFrom' => null,
    'dateTo' => null,
    'quickRange' => 'all',
    'clearRoute' => null,
    'initialAdvancedOpen' => false,
    'hasAdvanced' => false,
])

@php
    $formAction = $action ?? url()->current();
    $clearUrl = $clearRoute ?? $formAction;
@endphp

<x-notices::card class="mb-6 dark:bg-slate-800 dark:border-slate-700"
     x-data="{ showAdvanced: {{ $initialAdvancedOpen ? 'true' : 'false' }} }">
    <form method="{{ $method }}" action="{{ $formAction }}" class="space-y-4">
        {{-- Search row: input + buttons --}}
        <div class="flex flex-col md:flex-row md:items-end gap-2 max-w-2xl">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $searchLabel }}</label>
                <input
                    type="text"
                    name="{{ $searchName }}"
                    value="{{ $searchValue }}"
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                >
            </div>

            <div class="flex flex-row gap-2 md:ml-2">
                <button
                    type="submit"
                    class="h-10 px-4 bg-dcpl-blue hover:brightness-95 text-white rounded-lg font-medium transition-colors shadow-sm flex items-center justify-center text-sm"
                >
                    Search
                </button>
                <a
                    href="{{ $clearUrl }}"
                    class="h-10 px-4 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg font-medium transition-colors flex items-center justify-center text-sm"
                >
                    Clear
                </a>
            </div>
        </div>

        {{-- Date Range row --}}
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Date Range</label>
            <x-notices::date-range-filter
                :dateFrom="$dateFrom"
                :dateTo="$dateTo"
                :quickRange="$quickRange"
                :autoSubmit="false"
            />
        </div>

        @if($hasAdvanced)
            {{-- Advanced filters toggle --}}
            <div class="flex justify-end pt-1">
                <button
                    type="button"
                    @click="showAdvanced = !showAdvanced"
                    class="inline-flex items-center gap-1 text-xs font-medium text-dcpl-blue dark:text-sky-300 hover:underline underline-offset-2"
                >
                    <span x-show="!showAdvanced">Show Advanced Filters</span>
                    <span x-show="showAdvanced" x-cloak>Hide Advanced Filters</span>
                    <svg x-show="!showAdvanced" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    <svg x-show="showAdvanced" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>

            {{-- Advanced filters + actions --}}
            <div x-show="showAdvanced" x-cloak class="border-t border-slate-200 dark:border-slate-700 pt-4 mt-2">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{ $advanced ?? '' }}
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="px-4 py-2 bg-dcpl-blue hover:brightness-95 text-white rounded-lg font-medium transition-colors shadow-sm text-sm"
                    >
                        Apply Filters
                    </button>
                    <a
                        href="{{ $clearUrl }}"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg font-medium transition-colors text-sm"
                    >
                        Clear Filters
                    </a>
                </div>
            </div>
        @endif
    </form>
</x-notices::card>
