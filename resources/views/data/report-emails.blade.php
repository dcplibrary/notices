@extends('notices::data.index')

@section('tab-content')
    <!-- Search and Filter -->
    <div class="mb-6">
        <form method="GET" action="{{ route('notices.data.report-emails') }}" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Subject, sender, or body..."
                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-dcpl-blue dark:focus:ring-dcpl-blue focus:border-transparent"
                    >
                </div>

                <!-- Report Type -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Report Type</label>
                    <select
                        name="report_type"
                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-dcpl-blue dark:focus:ring-dcpl-blue focus:border-transparent"
                    >
                        <option value="">All Types</option>
                        @foreach($reportTypes as $type)
                            <option value="{{ $type->id }}" {{ request('report_type') == $type->id ? 'selected' : '' }}>
                                {{ $type->display_label }}
                            </option>
                        @endforeach
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
                <a href="{{ route('notices.data.emails') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Results Summary and Pagination -->
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Showing {{ $emails->firstItem() ?? 0 }} to {{ $emails->lastItem() ?? 0 }} of {{ number_format($emails->total()) }} emails
        </p>
        @if($emails->hasPages())
            <div class="flex items-center gap-2">
                {{ $emails->onEachSide(1)->links() }}
            </div>
        @endif
    </div>

    <!-- Emails List -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        @if($emails->count() > 0)
            @php
                $currentSort = $sort ?? 'received_at';
                $currentDir  = $dir  ?? 'desc';

                $sortUrl = function ($column) use ($currentSort, $currentDir) {
                    $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
                    return route('notices.data.report-emails', array_merge(request()->query(), ['sort' => $column, 'dir' => $newDir]));
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
                                @include('notices::partials.sortable-header', ['column' => 'received_at', 'label' => 'Received'])
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'subject', 'label' => 'Subject'])
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Affected Patrons
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($emails as $email)
                            <tr onclick="window.location='{{ route('notices.data.email-detail', $email->id) }}';"
                                class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                    {{ $email->received_at?->format('Y-m-d H:i') ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($email->emailReportType)
                                        <span class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-xs font-medium">
                                            {{ $email->emailReportType->display_label }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500 text-xs">Unclassified</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">
                                    <div class="max-w-md truncate">{{ $email->subject }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @php
                                        $affectedCount = 0;

                                        // For Polaris Summary Reports, count unique patron barcodes
                                        if ($email->parsed_data && $email->emailReportType?->code === 'polaris_summary_report') {
                                            $uniquePatrons = collect();

                                            // Collect unique patrons from successes
                                            if (!empty($email->parsed_data['successes'])) {
                                                foreach ($email->parsed_data['successes'] as $success) {
                                                    if (!empty($success['patron_barcode'])) {
                                                        $uniquePatrons->push($success['patron_barcode']);
                                                    }
                                                }
                                            }

                                            // Collect unique patrons from failures
                                            if (!empty($email->parsed_data['failures'])) {
                                                foreach ($email->parsed_data['failures'] as $failure) {
                                                    if (!empty($failure['patron_barcode'])) {
                                                        $uniquePatrons->push($failure['patron_barcode']);
                                                    }
                                                }
                                            }

                                            $affectedCount = $uniquePatrons->unique()->count();
                                        }
                                        // For Shoutbomb voice failure reports (uses 'barcode' field)
                                        elseif ($email->parsed_data && isset($email->parsed_data['failures'])) {
                                            $uniquePatrons = collect($email->parsed_data['failures'])
                                                ->map(fn($item) => $item['barcode'] ?? $item['patron_barcode'] ?? null)
                                                ->filter()
                                                ->unique();
                                            $affectedCount = $uniquePatrons->count();
                                        }
                                        // For Shoutbomb invalid phones (has 'invalid' and 'opted_out' arrays)
                                        elseif ($email->parsed_data && (isset($email->parsed_data['invalid']) || isset($email->parsed_data['opted_out']))) {
                                            $uniquePatrons = collect();

                                            // Collect from invalid array
                                            if (!empty($email->parsed_data['invalid'])) {
                                                foreach ($email->parsed_data['invalid'] as $item) {
                                                    if (!empty($item['barcode'])) {
                                                        $uniquePatrons->push($item['barcode']);
                                                    }
                                                }
                                            }

                                            // Collect from opted_out array
                                            if (!empty($email->parsed_data['opted_out'])) {
                                                foreach ($email->parsed_data['opted_out'] as $item) {
                                                    if (!empty($item['barcode'])) {
                                                        $uniquePatrons->push($item['barcode']);
                                                    }
                                                }
                                            }

                                            $affectedCount = $uniquePatrons->unique()->count();
                                        }
                                        // For Shoutbomb voice system errors (uses 'patron_phone' to identify unique patrons)
                                        elseif ($email->parsed_data && isset($email->parsed_data['errors'])) {
                                            $uniquePhones = collect($email->parsed_data['errors'])
                                                ->pluck('patron_phone')
                                                ->filter()
                                                ->unique();
                                            $affectedCount = $uniquePhones->count();
                                        }
                                        // Otherwise fall back to linked notifications count
                                        else {
                                            $affectedCount = $email->notifications_count ?? 0;
                                        }
                                    @endphp

                                    @if($affectedCount > 0)
                                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-full text-xs font-medium">
                                            {{ number_format($affectedCount) }} {{ Str::plural('patron', $affectedCount) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500 text-xs">None</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                {{ $emails->links() }}
            </div>
        @else
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No emails found</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Try adjusting your search or filter criteria.
                </p>
            </div>
        @endif
    </div>
@endsection
