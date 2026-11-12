@extends('notices::layouts.app')

@section('title', 'Configuration Backup & Restore')

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
            <h1 class="text-3xl font-bold text-white mb-2">Configuration backup & restore</h1>
            <p class="text-slate-400 text-sm">
                Back up display and UI message settings, download or email backups, and restore from a saved file.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Create Backup Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Create configuration backup</h2>
            <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                Exports lookup table overrides and UI messages to a JSON backup that can be downloaded, emailed, or stored on the server.
            </p>

            <form action="{{ route('notices.settings.maintenance.config-backup.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description (optional)</label>
                    <input
                        id="description"
                        name="description"
                        type="text"
                        maxlength="255"
                        class="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm focus:border-dcpl-blue focus:ring-dcpl-blue text-sm"
                        placeholder="e.g. Before changing overdue notice labels"
                        value="{{ old('description') }}"
                    >
                </div>

                <div>
                    <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Backup destination</span>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="mode" value="download" class="text-dcpl-blue border-slate-300 focus:ring-dcpl-blue" checked>
                            <span>Download JSON file</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="mode" value="email" class="text-dcpl-blue border-slate-300 focus:ring-dcpl-blue">
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
                            <input type="radio" name="mode" value="local" class="text-dcpl-blue border-slate-300 focus:ring-dcpl-blue">
                            <span>Store on server (storage/app/notices/config-backups)</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16V8l-6-4H4z" />
                        </svg>
                        Create backup
                    </button>
                </div>

                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Backup includes only Notices configuration tables (lookup overrides and UI messages). It does not include core notification data.
                </p>
            </form>
        </div>

        <!-- Restore Backup Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Restore configuration</h2>
            <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                Restore configuration from an uploaded backup file or from a backup stored on the server.
            </p>

            <div class="space-y-6">
                <!-- Restore from uploaded file -->
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1">Restore from file upload</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">
                        Upload a JSON configuration backup file exported from this interface.
                    </p>
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
                        <form action="{{ route('notices.settings.maintenance.config-backup.restore-local') }}" method="POST" class="space-y-3">
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
                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16V8l-6-4H4z" />
                                </svg>
                                Restore from server backup
                            </button>
                        </form>
                    @endif
                </div>

                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-md p-3 text-[11px] text-amber-900 dark:text-amber-100">
                    <p class="font-semibold mb-1">What gets restored</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <li>Lookup table overrides (Display Settings)</li>
                        <li>UI Messages (custom labels and text)</li>
                        <li>Existing records in these tables are replaced by the backup contents.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection