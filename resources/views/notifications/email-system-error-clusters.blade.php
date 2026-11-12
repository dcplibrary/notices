@extends('notices::layouts.app')

@section('body')
<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Polaris / LX Starter Error Clusters</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    NotificationLog Email rows where <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-700 rounded">delivery_string</code> is empty/NULL, grouped by minute.
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('notices.issues') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Delivery Issues
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
        <form method="GET" action="{{ route('notices.issues.email-system-errors') }}" class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">From</label>
                <input type="date" name="date_from" value="{{ $date_from }}"
                       class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">To</label>
                <input type="date" name="date_to" value="{{ $date_to }}"
                       class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
            </div>
            <div>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-dcpl-blue text-white rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors">
                    Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    Clusters ({{ number_format($clusters->count()) }})
                </h2>
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    Sorted by size
                </div>
            </div>
        </div>

        @if($clusters->isEmpty())
            <div class="p-6 text-sm text-slate-600 dark:text-slate-400">
                No clusters found for the selected date range.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider">Minute</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider">Rows</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider">First</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider">Last</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($clusters as $cluster)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $cluster->minute_bucket }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                                    {{ number_format((int) $cluster->count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                                    {{ $cluster->first_at }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                                    {{ $cluster->last_at }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

