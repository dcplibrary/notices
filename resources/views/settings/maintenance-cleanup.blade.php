@extends('notices::layouts.app')

@section('title', 'Cleanup')

@section('body')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="{{ route('notices.settings.maintenance') }}"
           class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Maintenance
        </a>
    </div>

    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">Clean up files and activity logs</h1>
            <p class="text-slate-400 text-sm">Delete old cached import files, and set how long notification activity logs stay in the live table.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-md border border-green-200 dark:border-green-700/50 bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-800 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-md border border-red-200 dark:border-red-700/50 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Activity log retention -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm mb-6">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-1">Activity log retention</h2>
            <p class="text-slate-600 dark:text-slate-400 text-sm">
                The nightly archive at 2:15 AM uses these settings. Older rows are copied to an archive file and removed from the live table.
            </p>
        </div>

        <form action="{{ route('notices.settings.maintenance.activity-log-retention') }}" method="POST" class="space-y-4">
            @csrf
            <div class="sm:w-40">
                <label for="activity_log_days" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Days to keep</label>
                <input
                    id="activity_log_days"
                    name="activity_log_days"
                    type="number"
                    min="1"
                    max="3650"
                    value="{{ old('activity_log_days', $activity_log_days ?? 90) }}"
                    class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-sm"
                >
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Default is 90 days.</p>
            </div>

            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input
                    type="checkbox"
                    name="keep_unresolved"
                    value="1"
                    class="mt-0.5 rounded border-slate-300 dark:border-slate-600 text-dcpl-blue focus:ring-dcpl-blue"
                    @checked(old('keep_unresolved', $activity_log_keep_unresolved ?? false))
                >
                <span>
                    Keep unresolved failures
                    <span class="block text-xs text-slate-500 dark:text-slate-400">Leave activity logs in place when the notification still has an open delivery issue.</span>
                </span>
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-slate-900 dark:text-white bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-dcpl-blue"
                >
                    Save
                </button>
                <button
                    type="submit"
                    formaction="{{ route('notices.settings.maintenance.activity-log-archive') }}"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                >
                    Run archive now
                </button>
            </div>
        </form>
    </div>

    <!-- Cached import files -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm mb-6">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-1">Cached import files</h2>
            <p class="text-slate-600 dark:text-slate-400 text-sm">
                Deletes cached import and export files older than these settings. Records already imported into the database are left alone.
            </p>
        </div>

        <form action="{{ route('notices.settings.maintenance.cleanup-import-logs') }}" method="POST" class="space-y-4">
            @csrf
            <div class="sm:w-40">
                <label for="days" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Days to keep</label>
                <input
                    id="days"
                    name="days"
                    type="number"
                    min="1"
                    max="365"
                    value="{{ old('days', 30) }}"
                    class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-sm"
                >
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Default is 30 days.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                >
                    Run cleanup now
                </button>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">
                Each run is recorded in the
                <a
                    href="{{ route('notices.settings.activity', ['search' => 'cleanup.import_logs']) }}"
                    class="text-dcpl-blue dark:text-sky-300 hover:underline"
                >
                    Activity Log
                </a>.
            </p>
        </form>
    </div>
</div>
@endsection