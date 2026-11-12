@extends('notices::layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Bulk Resolve Issues</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Resolve multiple delivery issues at once
                </p>
            </div>
            <a href="{{ route('notices.issues') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Issues
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-6 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700/50 rounded-lg p-4">
            <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-1">There were errors with your submission</h3>
                    <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Resolution Type Selection -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6" x-data="{ type: '{{ old('type', $type ?? 'time_window') }}' }">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Select Resolution Method</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Time Window -->
            <button
                @click="type = 'time_window'"
                :class="type === 'time_window' ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'"
                class="p-4 rounded-lg border-2 transition-all text-left hover:border-blue-400 dark:hover:border-blue-500"
            >
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/40 rounded-lg">
                        <svg class="w-6 h-6 text-dcpl-blue dark:text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white mb-1">Time Window</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Resolve all issues detected within a specific time range</p>
                    </div>
                </div>
            </button>

            <!-- Patron -->
            <button
                @click="type = 'patron'"
                :class="type === 'patron' ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'"
                class="p-4 rounded-lg border-2 transition-all text-left hover:border-blue-400 dark:hover:border-blue-500"
            >
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/40 rounded-lg">
                        <svg class="w-6 h-6 text-dcpl-blue dark:text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white mb-1">By Patron</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Resolve all issues for a specific patron</p>
                    </div>
                </div>
            </button>

            <!-- Specific Notifications -->
            <button
                @click="type = 'notifications'"
                :class="type === 'notifications' ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'"
                class="p-4 rounded-lg border-2 transition-all text-left hover:border-blue-400 dark:hover:border-blue-500"
            >
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/40 rounded-lg">
                        <svg class="w-6 h-6 text-dcpl-blue dark:text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white mb-1">Specific Notifications</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Resolve issues for specific notification IDs</p>
                    </div>
                </div>
            </button>
        </div>

        <!-- Resolution Form -->
        <form method="POST" action="{{ route('notices.issues.bulk-resolve') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="type" x-model="type">

            <!-- Time Window Fields -->
            <div x-show="type === 'time_window'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="date"
                            value="{{ old('date', today()->toDateString()) }}"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Start Time <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="time"
                            name="start_time"
                            value="{{ old('start_time', '00:00') }}"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            End Time <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="time"
                            name="end_time"
                            value="{{ old('end_time', '23:59') }}"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                        >
                    </div>
                </div>
            </div>

            <!-- Patron Field -->
            <div x-show="type === 'patron'" x-cloak>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    Patron Barcode <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="patron_barcode"
                    value="{{ old('patron_barcode') }}"
                    placeholder="Enter patron barcode"
                    class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                >
            </div>

            <!-- Notification IDs Field -->
            <div x-show="type === 'notifications'" x-cloak>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    Notification IDs <span class="text-red-500">*</span>
                </label>
                <textarea
                    name="notification_ids_text"
                    rows="4"
                    placeholder="Enter notification IDs separated by commas or line breaks&#10;Example: 123, 456, 789"
                    class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue font-mono text-sm"
                    x-on:input="
                        const ids = $event.target.value.split(/[\s,]+/).filter(id => id.trim() !== '');
                        document.getElementById('notification_ids_json').value = JSON.stringify(ids.map(id => parseInt(id.trim())));
                    "
                >{{ old('notification_ids_text') }}</textarea>
                <input type="hidden" name="notification_ids" id="notification_ids_json" value="{{ old('notification_ids') }}">
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">You can paste IDs separated by commas, spaces, or line breaks</p>
            </div>

            <!-- Resolution Notes -->
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    Resolution Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                    name="resolution_notes"
                    rows="4"
                    required
                    placeholder="Describe how these issues were resolved..."
                    class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                >{{ old('resolution_notes') }}</textarea>
            </div>

            <!-- Warning Box -->
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-lg p-4">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-1">Warning</h4>
                        <p class="text-sm text-amber-700 dark:text-amber-400">
                            This action will mark all matching issues as resolved and cannot be undone. Please ensure your resolution notes are accurate.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 justify-end pt-4 border-t border-slate-200 dark:border-slate-700">
                <a
                    href="{{ route('notices.issues') }}"
                    class="px-6 py-2 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors"
                >
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-6 py-2 bg-green-600 dark:bg-green-500 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors"
                >
                    Resolve Issues
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    [x-cloak] {
        display: none !important;
    }
</style>
@endsection
