@extends('notices::layouts.app')

@section('title', 'Activity File')

@section('body')
<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="{{ route('notices.settings.activity', ['date_from' => optional($file->file_date)->format('Y-m-d'), 'date_to' => optional($file->file_date)->format('Y-m-d')]) }}"
           class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Activity Log
        </a>
    </div>

    <!-- File Header -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">
                    {{ $file->original_name ?? 'Activity File #'.$file->id }}
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Type: <span class="font-mono">{{ $file->type }}</span>
                    @if($file->file_date)
                        <span class="mx-2 text-slate-400 dark:text-slate-500">&middot;</span>
                        File date: {{ $file->file_date->format('M j, Y') }}
                    @endif
                </p>
            </div>
            <div class="text-right text-sm text-slate-600 dark:text-slate-400">
                <p>Activity rows: <span class="font-semibold">{{ number_format($activityCount) }}</span></p>
                @if($firstOccurred && $lastOccurred)
                    <p class="mt-1">
                        Range: {{ optional($firstOccurred)->format('M j, Y g:i A') }}
                        &rarr;
                        {{ optional($lastOccurred)->format('M j, Y g:i A') }}
                    </p>
                @endif
            </div>
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-slate-500 dark:text-slate-400">Storage</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    @if($file->disk && $file->path)
                        <span class="font-mono">{{ $file->disk }}://{{ $file->path }}</span>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">Not stored</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500 dark:text-slate-400">Parsed At</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    {{ $file->parsed_at?->format('M j, Y g:i A') ?? 'N/A' }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500 dark:text-slate-400">Job Run</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    @if($file->jobRun)
                        Job #{{ $file->jobRun->id }}
                        @if($file->jobRun->started_at)
                            <span class="text-slate-500 dark:text-slate-400">&mdash; {{ $file->jobRun->started_at->format('M j, Y g:i A') }}</span>
                        @endif
                    @else
                        <span class="text-slate-400 dark:text-slate-500">Unknown</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <!-- Activity Rows for this File -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Activity linked to this file</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Sorted by occurred_at (newest first)</p>
        </div>

        @if($file->activityLogs->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">
                No notification activity rows are linked to this file yet.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Time</th>
                            <th class="px-3 py-2 text-left font-semibold">Notification</th>
                            <th class="px-3 py-2 text-left font-semibold">Source</th>
                            <th class="px-3 py-2 text-left font-semibold">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($file->activityLogs as $log)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ optional($log->occurred_at)->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                    @if($log->notification)
                                        <button
                                            type="button"
                                            @click="$dispatch('notification-detail-open', { id: {{ $log->notification->id }} })"
                                            class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                        >
                                            #{{ $log->notification->id }} &ndash; {{ $log->notification->patron_barcode }}
                                        </button>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ $log->source }}
                                </td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                                    <div>{{ $log->description }}</div>
                                    @if(isset($log->meta['issue_id']))
                                        <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            Issue:
                                            <button
                                                type="button"
                                                @click="$dispatch('issue-detail-open', { id: {{ $log->meta['issue_id'] }} })"
                                                class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            >
                                                #{{ $log->meta['issue_id'] }}
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-notices::unified-notification-modal :admin="true" />
</div>
@endsection
