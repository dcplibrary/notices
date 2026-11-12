@extends('notices::layouts.app')

@section('title', 'Maintenance')

@section('body')
<div class="container mx-auto px-4 py-8 max-w-5xl">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-white mb-2">Maintenance</h1>
        <p class="text-slate-400 text-sm">Run safe maintenance tasks and backups from the web interface.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Import/Export Cleanup (small widget linking to full tool view) -->
        <a href="{{ route('notices.settings.maintenance.cleanup') }}" class="group block h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-5 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 group-hover:bg-amber-100 dark:group-hover:bg-amber-900/50 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0a1 1 0 01-.894-.553L6.382 4.276A2 2 0 018.105 3h7.79a2 2 0 011.723 1.276L18 7m-9 0h6" />
                        </svg>
                    </div>
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">Clean up cached import/export files</h2>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-3">
                    Safely delete old cached Polaris/Shoutbomb import/export files to reclaim disk space.
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-auto">
                    Opens detailed cleanup tool with date range options and activity log links.
                </p>
            </div>
        </a>

        <!-- Backups & Restore card -->
        <a href="{{ route('notices.settings.maintenance.config-backup') }}" class="group block h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-5 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-sky-50 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 group-hover:bg-sky-100 dark:group-hover:bg-sky-900/50 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16V8l-6-4H4z" />
                        </svg>
                    </div>
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">Backups & restore</h2>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-3">
                    Create and restore backups for configuration settings and Notices data from a single dashboard.
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-auto">
                    Opens combined backup & restore tools for configuration, data, or both.
                </p>
            </div>
        </a>
    </div>
</div>

@endsection
