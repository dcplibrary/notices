@extends('notices::layouts.app')

@section('title', 'Cleanup Import/Export Files')

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
            <h1 class="text-3xl font-bold text-white mb-2">Clean up cached import/export files</h1>
            <p class="text-slate-400 text-sm">Delete old cached Polaris/Shoutbomb import/export files from local storage.</p>
        </div>
    </div>

    <!-- Cleanup Import/Export Logs Card -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm mb-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-1">Run cleanup task</h2>
                <p class="text-slate-600 dark:text-slate-400 text-sm">
                    Deletes cached import/export files older than a specified number of days from the local import
                    storage directory. This does not affect data already imported into the database.
                </p>
            </div>
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0a1 1 0 01-.894-.553L6.382 4.276A2 2 0 018.105 3h7.79a2 2 0 011.723 1.276L18 7m-9 0h6" />
                </svg>
            </div>
        </div>

        <form
            action="{{ route('notices.settings.maintenance.cleanup-import-logs') }}"
            method="POST"
            class="space-y-4"
        >
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
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
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Default is 30 days. Older files will be deleted.</p>
                </div>

                <div class="flex-1 flex items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                    >
                        <span>Run cleanup</span>
                    </button>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        This runs <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-900 text-[11px]">notices:cleanup-import-logs --from=&lt;oldest&gt; --to=&lt;cutoff&gt; --force --no-interaction</code>
                    </p>
                </div>
            </div>

            <div class="mt-2 text-sm space-y-1">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    View recent cleanup entries in the
                    <a
                        href="{{ route('notices.settings.activity', ['search' => 'cleanup.import_logs']) }}"
                        class="text-dcpl-blue dark:text-sky-300 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Activity Log
                    </a>.
                </p>
            </div>

            <div class="mt-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-md p-3 text-xs text-amber-900 dark:text-amber-200">
                <p class="font-semibold mb-1">What this does</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Operates only on cached import/export files in the local storage directory.</li>
                    <li>Does not remove any records from the database.</li>
                    <li>Logs details of each run to the Activity Log for auditing.</li>
                </ul>
            </div>
        </form>
    </div>
</div>
@endsection