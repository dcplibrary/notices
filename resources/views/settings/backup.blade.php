@extends('notices::layouts.app')

@section('title', 'Backups & Restore')

@section('body')
<div class="container mx-auto px-4 py-8 max-w-6xl">
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
            <h1 class="text-3xl font-bold text-white mb-2">Backups & restore</h1>
            <p class="text-slate-400 text-sm">
                Create and restore backups for Notices configuration and core data. Choose configuration settings, Notices data, or both.
            </p>
        </div>
    </div>

    <!-- Info -->
    <div class="mb-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/40 rounded-md p-3 text-[11px] text-blue-900 dark:text-blue-100">
            <p class="font-semibold mb-1">What you can back up</p>
            <ul class="list-disc list-inside space-y-0.5">
                <li><strong>Configuration settings</strong>: Display overrides and UI messages.</li>
                <li><strong>Notices data</strong>: Notifications, delivery issues, failed delivery strings, ingested emails, import batches, activity logs/files, job runs, and user activity logs.</li>
            </ul>
        </div>
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-md p-3 text-[11px] text-amber-900 dark:text-amber-100">
            <p class="font-semibold mb-1">Restore caution</p>
            <ul class="list-disc list-inside space-y-0.5">
                <li>Restoring configuration or data will <strong>replace existing records</strong> in the corresponding tables.</li>
                <li>Perform restores only in controlled environments or maintenance windows.</li>
                <li>Consider a full database backup at the server level before restoring.</li>
            </ul>
        </div>
    </div>

    <!-- Unified backup options -->
    <div class="mb-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col gap-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Create backup</h2>
            <p class="text-xs text-slate-600 dark:text-slate-400">
                Choose what to back up (configuration, data, or both) and where to send the backup file.
            </p>
        </div>
        <form action="{{ route('notices.settings.maintenance.backup.create') }}" method="POST" class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            @csrf
            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">What to back up</label>
                    <div class="space-y-1 text-xs">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="scope" value="config" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue">
                            <span>Configuration only</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="scope" value="data" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue">
                            <span>Notices data only</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="scope" value="both" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue" checked>
                            <span>Full backup (configuration + data)</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label for="backup-description" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Description (optional)</label>
                    <input
                        id="backup-description"
                        name="description"
                        type="text"
                        maxlength="255"
                        class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-xs"
                        placeholder="e.g. Pre-upgrade snapshot"
                        value="{{ old('description') }}"
                    >
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 text-xs items-start sm:items-center">
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-1">
                        <input type="radio" name="mode" value="download" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue" checked>
                        <span>Download</span>
                    </label>
                    <label class="flex items-center gap-1">
                        <input type="radio" name="mode" value="email" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue">
                        <span>Email</span>
                    </label>
                    <label class="flex items-center gap-1">
                        <input type="radio" name="mode" value="local" class="text-blue-600 border-slate-300 focus:ring-dcpl-blue">
                        <span>Server</span>
                    </label>
                </div>
                <input
                    id="backup-email"
                    name="email"
                    type="email"
                    class="w-full sm:w-56 rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-xs"
                    placeholder="Email (optional)"
                    value="{{ old('email') }}"
                >
                <button
                    type="submit"
                    class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 whitespace-nowrap"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16V8l-6-4H4z" />
                    </svg>
                    Create backup
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-stretch">
        <!-- Configuration Backup & Restore -->
        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm h-full flex flex-col">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Configuration restore</h2>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Restore display overrides and UI messages from a configuration backup file created above.
                </p>

                <div class="space-y-4">
                    <!-- Restore from uploaded config file -->
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">From file upload</h4>
                        <form action="{{ route('notices.settings.maintenance.config-backup.restore-upload') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input
                                type="file"
                                name="backup_file"
                                accept="application/json,text/plain"
                                class="block w-full text-xs text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 rounded-md cursor-pointer bg-white dark:bg-slate-900 focus:outline-none focus:ring-1 focus:ring-dcpl-blue focus:border-dcpl-blue"
                            >
                            <button
                                type="submit"
                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16V8l-6-4H4z" />
                                </svg>
                                Restore configuration from file
                            </button>
                        </form>
                    </div>

                    <!-- Restore from server config backup -->
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">From server backup</h4>
                        @if(empty($config_backup_files))
                            <p class="text-xs text-slate-500 dark:text-slate-400">No configuration backups found on server yet.</p>
                        @else
                            <form action="{{ route('notices.settings.maintenance.config-backup.restore-local') }}" method="POST" class="space-y-3">
                                @csrf
                                <select
                                    name="path"
                                    class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-xs"
                                >
                                    @foreach($config_backup_files as $path)
                                        <option value="{{ $path }}">{{ basename($path) }}</option>
                                    @endforeach
                                </select>
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                >
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16V8l-6-4H4z" />
                                    </svg>
                                    Restore configuration from server backup
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Notices Data Restore -->
        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm h-full flex flex-col">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Notices data restore</h2>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Restore core Notices data from a data backup file created above.
                </p>

                <div class="space-y-4">
                    <!-- Restore from uploaded data file -->
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">From file upload</h4>
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
                                Restore data from file
                            </button>
                        </form>
                    </div>

                    <!-- Restore from server data backup -->
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">From server backup</h4>
                        @if(empty($data_backup_files))
                            <p class="text-xs text-slate-500 dark:text-slate-400">No Notices data backups found on server yet.</p>
                        @else
                            <form action="{{ route('notices.settings.maintenance.data-backup.restore-local') }}" method="POST" class="space-y-3">
                                @csrf
                                <select
                                    name="path"
                                    class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-xs"
                                >
                                    @foreach($data_backup_files as $path)
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
                                    Restore data from server backup
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full restore (combined backup) -->
    <div class="mt-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Full restore</h2>
        <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
            Restore configuration settings and Notices data together from a full backup file.
        </p>

        <div class="space-y-4">
            <div>
                <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">From file upload</h4>
                <form action="{{ route('notices.settings.maintenance.backup-full.restore-upload') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
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
                        Restore full backup from file
                    </button>
                </form>
            </div>

            <div>
                <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">From server backup</h4>
                @if(empty($combined_backup_files ?? []))
                    <p class="text-xs text-slate-500 dark:text-slate-400">No full backups found on server yet.</p>
                @else
                    <form action="{{ route('notices.settings.maintenance.backup-full.restore-local') }}" method="POST" class="space-y-3">
                        @csrf
                        <select
                            name="path"
                            class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-xs"
                        >
                            @foreach($combined_backup_files as $path)
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
                            Restore full backup from server
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
