@extends('notices::data.index')

@section('tab-content')
    @php
        $search = $search ?? '';
        $dateFrom = request('date_from');
        $dateTo = request('date_to');
        $quickRange = request('quick_range');

        // On Overview (no search, no explicit dates/range), default picker to Today
        if ($search === '' && !$dateFrom && !$dateTo && !$quickRange) {
            $today = now()->toDateString();
            $dateFrom = $today;
            $dateTo = $today;
            $quickRange = 'today';
        }
    @endphp

    <!-- Unified Data Search -->
    <div class="mb-6">
        <form method="GET" action="{{ route('notices.data.search') }}" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4 space-y-4">
            <!-- Search row: input + buttons -->
            <div class="flex flex-col md:flex-row md:items-end gap-2 max-w-2xl">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Barcode, phone number, email address, or text..."
                        class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-dcpl-blue dark:focus:ring-dcpl-blue focus:border-transparent"
                        autofocus
                    >
                </div>

                <div class="flex flex-row gap-2 md:ml-2">
                    <button type="submit" class="h-10 px-4 bg-dcpl-blue text-white rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors text-sm font-medium flex items-center justify-center">
                        Search
                    </button>
                    <a href="{{ route('notices.data.search') }}" class="h-10 px-4 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors text-sm font-medium flex items-center justify-center">
                        Clear
                    </a>
                </div>
            </div>

            <!-- Date Range Filter (under search, aligned left) -->
            <div class="pt-2">
                <x-notices::date-range-filter
                    :dateFrom="$dateFrom"
                    :dateTo="$dateTo"
                    :quickRange="$quickRange ?? 'today'"
                />
            </div>
        </form>
    </div>

    @if($search === '')
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
            By default, this overview shows todays email reports and export batches. Enter a barcode, phone number, email address, or text to search across history.
        </p>
    @endif

    <!-- Results Summary -->
        <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-600 dark:text-slate-400 uppercase mb-1">Report Emails</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $emails->count() }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-600 dark:text-slate-400 uppercase mb-1">Export Batches</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $imports->count() }}</p>
            </div>
        </div>

        <!-- Full-width sections stacked: Report Emails on top, Export Batches below -->
        <div class="space-y-6">
            <!-- Matching Report Emails -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $emails->count() }} Matching Report Emails</h2>
                    <a href="{{ route('notices.data.report-emails', ['search' => $search]) }}" class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline">
                        View in Report Emails
                    </a>
                </div>

                @if($emails->isEmpty())
                    <div class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        No report emails matched "{{ $search }}".
                    </div>
                @else
                    @php
                        $currentSortEmails = $sort_emails ?? 'received_at';
                        $currentDirEmails  = $dir_emails  ?? 'desc';

                        $sortUrlEmails = function ($column) use ($currentSortEmails, $currentDirEmails, $search) {
                            $newDir = ($currentSortEmails === $column && $currentDirEmails === 'asc') ? 'desc' : 'asc';
                            return route('notices.data.search', array_merge(request()->query(), [
                                'search'      => $search,
                                'sort_emails' => $column,
                                'dir_emails'  => $newDir,
                            ]));
                        };

                        $sortIconEmails = function ($column) use ($currentSortEmails, $currentDirEmails) {
                            if ($currentSortEmails !== $column) {
                                return '';
                            }
                            return $currentDirEmails === 'asc' ? '▲' : '▼';
                        };

                        // Alias to generic names expected by the sortable-header partial
                        $sortUrl  = $sortUrlEmails;
                        $sortIcon = $sortIconEmails;
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">
                                        @include('notices::partials.sortable-header', ['column' => 'received_at', 'label' => 'Received'])
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">
                                        @include('notices::partials.sortable-header', ['column' => 'subject', 'label' => 'Subject'])
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($emails as $email)
                                    <tr onclick="window.location='{{ route('notices.data.email-detail', $email->id) }}';" class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-900 dark:text-slate-100">
                                            {{ $email->received_at?->format('Y-m-d H:i') ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs">
                                            @if($email->emailReportType)
                                                <span class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-medium">
                                                    {{ $email->emailReportType->display_label }}
                                                </span>
                                            @else
                                                <span class="text-slate-400 dark:text-slate-500 text-[11px]">Unclassified</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-xs text-slate-900 dark:text-slate-100">
                                            <div class="max-w-xs truncate" title="{{ $email->subject }}">{{ $email->subject }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Matching Export Batches -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $imports->count() }} Matching Export Batches</h2>
                    <a href="{{ route('notices.data.export-files', ['search' => $search]) }}" class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline">
                        View in Export Files
                    </a>
                </div>

                @if($imports->isEmpty())
                    <div class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        No export batches matched "{{ $search }}".
                    </div>
                @else
                    @php
                        $currentSortImports = $sort_imports ?? 'imported_at';
                        $currentDirImports  = $dir_imports  ?? 'desc';

                        $sortUrlImports = function ($column) use ($currentSortImports, $currentDirImports, $search) {
                            $newDir = ($currentSortImports === $column && $currentDirImports === 'asc') ? 'desc' : 'asc';
                            return route('notices.data.search', array_merge(request()->query(), [
                                'search'       => $search,
                                'sort_imports' => $column,
                                'dir_imports'  => $newDir,
                            ]));
                        };

                        $sortIconImports = function ($column) use ($currentSortImports, $currentDirImports) {
                            if ($currentSortImports !== $column) {
                                return '';
                            }
                            return $currentDirImports === 'asc' ? '▲' : '▼';
                        };

                        // Alias to generic names expected by the sortable-header partial
                        $sortUrl  = $sortUrlImports;
                        $sortIcon = $sortIconImports;
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">
                                        @include('notices::partials.sortable-header', ['column' => 'imported_at', 'label' => 'Imported At'])
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">
                                        @include('notices::partials.sortable-header', ['column' => 'source', 'label' => 'Source'])
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">
                                        @include('notices::partials.sortable-header', ['column' => 'date_for', 'label' => 'Date For'])
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Filename</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($imports as $import)
                                    @php
                                        $sourceLabelMap = [
                                            'ftp_holds'           => 'Holds',
                                            'ftp_renew'           => 'Renewals',
                                            'ftp_overdue'         => 'Overdues',
                                            'phone_notices'       => 'Phone Notices',
                                            'notification_log'    => 'Notification Log',
                                            'notification_history'=> 'Notification History',
                                            'email_reports'       => 'Email Reports',
                                            'notices_pipeline'    => 'Pipeline',
                                        ];
                                        $sourceLabel = $sourceLabelMap[$import->source] ?? ucfirst(str_replace('_', ' ', $import->source));
                                    @endphp
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-900 dark:text-slate-100">
                                            {{ $import->imported_at?->format('Y-m-d H:i') ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs">
                                            <span class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-medium">
                                                {{ $sourceLabel }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-900 dark:text-slate-100">
                                            {{ $import->date_for?->format('Y-m-d') ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-2 text-xs text-slate-900 dark:text-slate-100">
                                            @php
                                                $displayFilename = $import->display_filename ?? $import->identifier;
                                                $dateFor = $import->date_for instanceof \DateTimeInterface
                                                    ? $import->date_for->format('Y-m-d')
                                                    : (string) $import->date_for;
                                            @endphp
                                            <div class="max-w-xs truncate" title="{{ $displayFilename ?? 'N/A' }}">
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
@endsection
