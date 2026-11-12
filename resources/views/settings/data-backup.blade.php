@extends('notices::layouts.app')

@section('title', 'Notices Data Backup & Restore')

@section('body')
<div class="container mx-auto px-4 py-8 max-w-5xl">
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
            <h1 class="text-3xl font-bold text-white mb-2">Notices data backup & restore</h1>
            <p class="text-slate-400 text-sm">
                Create full backups of core Notices data tables (notifications, issues, logs) and restore them for disaster recovery.
            </p>
        </div>
    </div>

    <div class="mb-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-md p-3 text-[11px] text-amber-900 dark:text-amber-100">
        <p class="font-semibold mb-1">Caution</p>
        <ul class="list-disc list-inside space-y-0.5">
            <li>Restoring a data backup will <strong>replace existing data</strong> in the included tables.</li>
            <li>Only use restore in controlled scenarios (test environments or carefully planned maintenance windows).</li>
            <li>Consider backing up your database at the server level before performing a restore.</li>
        </ul>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Create Data Backup Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Create data backup</h2>
            <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                Export core Notices data tables (notifications, issues, activity logs, import batches, etc.) to a JSON backup.
            </p>

            <form action="{{ route('notices.settings.maintenance.data-backup.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description (optional)</label>
                    <input
                        id="description"
                        name="description"
                        type="text"
                        maxlength="255"
                        class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-sm"
                        placeholder="e.g. Before upgrading Notices or Polaris integration"
                        value="{{ old('description') }}"
                    >
                </div>

                <div>
                    <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Backup destination</span>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="mode" value="download" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue" checked>
                            <span>Download JSON file</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="mode" value="email" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue">
                            <span>Email backup file</span>
                        </label>
                        <div class="ml-6">
                            <label for="email" class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Recipient email (optional)</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-xs"
                                placeholder="Defaults to mail.from.address if left blank"
                                value="{{ old('email') }}"
                            >
                        </div>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="mode" value="local" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue">
                            <span>Store on server (storage/app/notices/data-backups)</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                        </svg>
                        Create data backup
                    </button>
                </div>

                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Backup includes notifications, delivery issues, failed delivery strings, ingested emails, import batches, activity files/logs, job runs, and user activity logs.
                </p>
            </form>
        </div>

        <!-- Restore Data Backup Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Restore data</h2>
            <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                Restore Notices data from an uploaded backup file or from a backup stored on the server.
            </p>

            <div class="space-y-6">
                <!-- Restore from uploaded file -->
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1">Restore from file upload</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">
                        Upload a JSON Notices data backup file exported from this interface.
                    </p>
                    <form action="{{ route('notices.settings.maintenance.data-backup.restore-upload') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <input
                            type="file"
                            name="backup_file"
                            accept="application/json,text/plain"
                            class="block w-full text-xs text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 rounded-md cursor-pointer bg-white dark:bg-slate-900 focus:outline-none focus:ring-1 focus:ring-dcpl-blue focus:border-dcpl-blue"
                        >
                        <button
                            type="submit"
                            class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-red-600 hover:bg-red-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                            </svg>
                            Restore from file
                        </button>
                    </form>
                </div>

                <!-- Restore from local backup -->
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1">Restore from server backup</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">
                        Choose a backup file previously saved on the server.
                    </p>
                    @if(empty($backup_files))
                        <p class="text-xs text-slate-500 dark:text-slate-400">No server backups found yet.</p>
                    @else
                        <form action="{{ route('notices.settings.maintenance.data-backup.restore-local') }}" method="POST" class="space-y-3">
                            @csrf
                            <select
                                name="path"
                                class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-xs"
                            >
                                @foreach($backup_files as $path)
                                    <option value="{{ $path }}">{{ basename($path) }}</option>
                                @endforeach
                            </select>
                            <button
                                type="submit"
                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-red-600 hover:bg-red-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                                </svg>
                                Restore from server backup
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection