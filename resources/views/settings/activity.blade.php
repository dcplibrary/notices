@extends('notices::layouts.app')

@section('body')
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100 mb-1">Activity Log</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
            Activity entries: {{ $notificationActivity->total() }} &mdash; Users: {{ $userActivity->total() }}
        </p>

        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-4 mb-6 shadow-sm">
            <form method="GET" action="{{ route('notices.settings.activity') }}" class="space-y-4">
                <!-- Date Range Filter (shared with Notifications list) -->
                <div>
                    <x-notices::date-range-filter
                        :dateFrom="$filters['date_from'] ?? $date_from"
                        :dateTo="$filters['date_to'] ?? $date_to"
                        :quickRange="request('quick_range', '')"
                        :autoSubmit="false"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Combined Search -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Search activity</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Description, source, filename, notification ID, or patron barcode..."
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                    </div>

                    <!-- Activity Source -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Source</label>
                        <select
                            name="source"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                            <option value="">All sources</option>
                            @foreach($activity_sources as $source)
                                <option value="{{ $source }}" {{ ($filters['source'] ?? '') === $source ? 'selected' : '' }}>
                                    {{ $source }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Notification Status (optional, via related notification) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Notification Status</label>
                        <select
                            name="status"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                            <option value="">All statuses</option>
                            <option value="delivered" {{ ($filters['status'] ?? '') === 'delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                            <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>

                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="px-4 py-2 bg-dcpl-blue hover:brightness-95 text-white rounded-lg font-medium text-sm transition-colors shadow-sm"
                    >
                        Apply Filters
                    </button>
                    <a
                        href="{{ route('notices.settings.activity') }}"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg font-medium text-sm transition-colors"
                    >
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="space-y-8">
            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">Notification Activity</h2>
                <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
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
                        @forelse($notificationActivity as $log)
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
                                            #{{ $log->notification->id }} – {{ $log->notification->patron_barcode }}
                                        </button>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    @php
                                        $fileName = $log->activityFile->original_name
                                            ?? ($log->meta['filename'] ?? null)
                                            ?? ($log->notification->export_filename ?? null);
                                    @endphp

                                    @if($log->activityFile)
                                        <a
                                            href="{{ route('notices.data.activity-file-detail', $log->activityFile->id) }}"
                                            class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            title="View all activity linked to this file"
                                        >
                                            {{ $fileName ?? $log->activityFile->original_name ?? 'Activity file' }}
                                        </a>
                                    @elseif($fileName)
                                        <a
                                            href="{{ route('notices.data.export-files', ['search' => $fileName]) }}"
                                            class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            title="View import batch for this file"
                                        >
                                            {{ $fileName }}
                                        </a>
                                    @else
                                        {{ $log->source }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                                    <div>{{ $log->description }}</div>
                                    @if($log->activityFile)
                                        <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            File: {{ $log->activityFile->original_name }}
                                        </div>
                                    @endif

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
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">No activity found for this range.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    {{ $notificationActivity->appends(request()->except('notifications_page'))->links() }}
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">User Activity</h2>
                <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Time</th>
                            <th class="px-3 py-2 text-left font-semibold">User</th>
                            <th class="px-3 py-2 text-left font-semibold">Action</th>
                            <th class="px-3 py-2 text-left font-semibold">Description</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($userActivity as $log)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ optional($log->occurred_at)->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                    {{ $log->user_id ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ $log->action }}
                                </td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                                    <div>{{ $log->description }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">No user activity found for this range.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    {{ $userActivity->appends(request()->except('users_page'))->links() }}
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">System Maintenance Activity</h2>
                <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Time</th>
                            <th class="px-3 py-2 text-left font-semibold">Action</th>
                            <th class="px-3 py-2 text-left font-semibold">Description</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($systemActivity as $log)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ optional($log->created_at)->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                    {{ $log->action }}
                                </td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                                    <div>{{ $log->description }}</div>
                                    @if(!empty($log->meta['base_dir']) || !empty($log->meta['file_count']) || !empty($log->meta['deleted']))
                                        <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            @if(!empty($log->meta['base_dir']))
                                                Path: {{ $log->meta['base_dir'] }}
                                            @endif
                                            @if(isset($log->meta['file_count']))
                                                @if(!empty($log->meta['base_dir'])) &middot; @endif
                                                Files: {{ $log->meta['file_count'] }}
                                            @endif
                                            @if(isset($log->meta['deleted']))
                                                @if(!empty($log->meta['base_dir']) || isset($log->meta['file_count'])) &middot; @endif
                                                Deleted: {{ $log->meta['deleted'] }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">No system maintenance activity found for this range.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    {{ $systemActivity->appends(request()->except('system_page'))->links() }}
                </div>
            </div>
        </div>

        <!-- Shared notification details modal so activity links can open in-place -->
        <x-notices::unified-notification-modal :admin="true" />
    </div>
@endsection
