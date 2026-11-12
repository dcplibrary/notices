@extends('notices::data.index')

@section('tab-content')
    <!-- Search and Filter -->
    <div class="mb-6">
        <form method="GET" action="{{ route('notices.data.export-files') }}" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Filename or source table..."
                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-dcpl-blue dark:focus:ring-dcpl-blue focus:border-transparent"
                    >
                </div>

                <!-- Source Filter -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Source</label>
                    <select
                        name="source"
                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-dcpl-blue dark:focus:ring-dcpl-blue focus:border-transparent"
                    >
                        <option value="">All Sources</option>
                        @foreach($sources as $source)
                            <option value="{{ $source }}" {{ request('source') == $source ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $source)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Status</label>
                    <select
                        name="status"
                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-dcpl-blue dark:focus:ring-dcpl-blue focus:border-transparent"
                    >
                        <option value="">All Statuses</option>
                        <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Success</option>
                        <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Date Range</label>
                <x-notices::date-range-filter
                    :dateFrom="request('date_from')"
                    :dateTo="request('date_to')"
:quickRange="request('quick_range', 'all')"
                    :autoSubmit="false"
                />
            </div>

            <div class="flex gap-2">
                <a href="{{ route('notices.data.export-files') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Results Summary and Pagination -->
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Showing {{ $imports->firstItem() ?? 0 }} to {{ $imports->lastItem() ?? 0 }} of {{ number_format($imports->total()) }} imports
        </p>
        @if($imports->hasPages())
            <div class="flex items-center gap-2">
                {{ $imports->onEachSide(1)->links() }}
            </div>
        @endif
    </div>

    <!-- Import Files List -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        @if($imports->count() > 0)
            @php
                $currentSort = $sort ?? 'imported_at';
                $currentDir  = $dir  ?? 'desc';

                $sortUrl = function ($column) use ($currentSort, $currentDir) {
                    $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
                    return route('notices.data.export-files', array_merge(request()->query(), ['sort' => $column, 'dir' => $newDir]));
                };

                $sortIcon = function ($column) use ($currentSort, $currentDir) {
                    if ($currentSort !== $column) {
                        return '';
                    }
                    return $currentDir === 'asc' ? '▲' : '▼';
                };
            @endphp

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'imported_at', 'label' => 'Imported At'])
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'source', 'label' => 'Source'])
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'date_for', 'label' => 'Date For'])
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Filename
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'records', 'label' => 'Records'])
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($imports as $import)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                    {{ $import->imported_at?->format('Y-m-d H:i') ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @php
                                        $sourceLabelMap = [
                                            'ftp_holds'          => 'Holds',
                                            'ftp_renew'          => 'Renewals',
                                            'ftp_overdue'        => 'Overdues',
                                            'phone_notices'      => 'Phone Notices',
                                            'notification_log'   => 'Notification Log',
                                            'notification_history'=> 'Notification History',
                                            'email_reports'      => 'Email Reports',
                                            'notices_pipeline'   => 'Pipeline',
                                        ];
                                        $sourceLabel = $sourceLabelMap[$import->source] ?? ucfirst(str_replace('_', ' ', $import->source));
                                    @endphp
                                    <span class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-xs font-medium">
                                        {{ $sourceLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                    {{ $import->date_for?->format('Y-m-d') ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">
                                    @php
                                        $displayFilename = $import->display_filename ?? $import->identifier;
                                        $dateFor = $import->date_for instanceof \DateTimeInterface
                                            ? $import->date_for->format('Y-m-d')
                                            : (string) $import->date_for;
                                    @endphp
                                    <div class="max-w-md truncate" title="{{ $displayFilename ?? 'N/A' }}">
                                        @if($import->source === 'ftp_holds' && $dateFor)
                                            <a
                                                href="{{ route('notices.holds.logs', ['date' => $dateFor]) }}"
                                                class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            >
                                                {{ $displayFilename ?? 'N/A' }}
                                            </a>
                                        @elseif($import->source === 'ftp_renew' && $dateFor)
                                            <a
                                                href="{{ route('notices.renew.logs', ['date' => $dateFor]) }}"
                                                class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            >
                                                {{ $displayFilename ?? 'N/A' }}
                                            </a>
                                        @elseif($import->source === 'ftp_overdue' && $dateFor)
                                            <a
                                                href="{{ route('notices.overdue.logs', ['date' => $dateFor]) }}"
                                                class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            >
                                                {{ $displayFilename ?? 'N/A' }}
                                            </a>
                                        @elseif($import->source === 'notification_log' && $dateFor)
                                            <a
                                                href="{{ route('notices.data.notification-log', ['date' => $dateFor]) }}"
                                                class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            >
                                                {{ $displayFilename ?? 'N/A' }}
                                            </a>
                                        @elseif($import->source === 'phone_notices' && $dateFor)
                                            <a
                                                href="{{ route('notices.data.phone-notices', ['date' => $dateFor]) }}"
                                                class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            >
                                                {{ $displayFilename ?? 'N/A' }}
                                            </a>
                                        @elseif($import->source === 'notification_history' && $dateFor)
                                            <a
                                                href="{{ route('notices.data.notification-history', ['date' => $dateFor]) }}"
                                                class="text-dcpl-blue dark:text-sky-300 hover:underline"
                                            >
                                                {{ $displayFilename ?? 'N/A' }}
                                            </a>
                                        @else
                                            {{ $displayFilename ?? 'N/A' }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @php
                                        $recordCount = $import->records_count ?? $import->notifications_count ?? 0;
                                    @endphp
                                    @if($recordCount > 0)
                                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-full text-xs font-medium">
                                            {{ number_format($recordCount) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500 text-xs">0</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                {{ $imports->links() }}
            </div>
        @else
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No import files found</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Try adjusting your search or filter criteria.
                </p>
            </div>
        @endif
    </div>
@endsection
