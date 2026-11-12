@extends('notices::layouts.app')

@section('body')
<div class="notices-staff-page pb-10">
<div class="max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <x-dcpl::page-heading
                title="Delivery Issues"
                description="Track and resolve notification delivery problems"
                class="min-w-0" />
            <div class="flex gap-3">
                <a href="{{ route('notices.issues', array_merge(request()->all(), ['show_resolved' => request('show_resolved') ? null : '1'])) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 {{ request('show_resolved') ? 'bg-dcpl-blue text-white' : 'bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300' }} rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors">
                    @if(request('show_resolved'))
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                        Hide Resolved
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Show Resolved
                    @endif
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <x-dcpl::sub-tab-nav label="Issue type">
        <x-dcpl::sub-tab label="Overview"
                         href="{{ route('notices.issues') }}"
                         :active="!$selected_type && !request()->routeIs('notices.issues.orphaned')" />
        <x-dcpl::sub-tab label="Invalid Contact Information"
                         href="{{ route('notices.issues', ['type' => 'failed_contacts']) }}"
                         :active="$selected_type === 'failed_contacts'" />
        <x-dcpl::sub-tab label="LX Starter Failures"
                         href="{{ route('notices.issues.orphaned', ['orphaned_days' => $orphaned_days ?? 30]) }}"
                         :active="request()->routeIs('notices.issues.orphaned')" />
    </x-dcpl::sub-tab-nav>

    <!-- Date Range Filters -->
    @if(!$selected_type)
    <div class="mb-6">
        <form method="GET" action="{{ route('notices.issues') }}" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
            @if($selected_type)
                <input type="hidden" name="type" value="{{ $selected_type }}">
            @endif
            @if(request('show_resolved'))
                <input type="hidden" name="show_resolved" value="{{ request('show_resolved') }}">
            @endif
            <x-notices::date-range-filter
                :dateFrom="$filters['date_from'] ?? ''"
                :dateTo="$filters['date_to'] ?? ''"
                :quickRange="request('quick_range', '7days')"
                :autoSubmit="true"
            />
        </form>
    </div>
    @elseif($selected_type === 'failed_contacts')
    <div class="mb-6">
        <form method="GET" action="{{ route('notices.issues', ['type' => 'failed_contacts']) }}" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
            @if(request('show_resolved'))
                <input type="hidden" name="show_resolved" value="{{ request('show_resolved') }}">
            @endif
            <x-notices::date-range-filter
                :dateFrom="$filters['date_from'] ?? ''"
                :dateTo="$filters['date_to'] ?? ''"
                :quickRange="request('quick_range', 'all')"
                :autoSubmit="true"
            />
        </form>
    </div>
    @endif

    @if(session('success'))
        <div class="mb-6 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700/50 rounded-lg p-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm font-medium text-green-700 dark:text-green-400">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(!$selected_type)
    @php
        // Only preserve date-range params for cross-page links/clickthroughs.
        $baseQuery = array_filter(request()->only(['date_from', 'date_to', 'quick_range']), function ($v) {
            return null !== $v && '' !== $v;
        });
        $dateFrom = request('date_from');
        $dateTo = request('date_to');
        $lxDays = 30;
        if ($dateFrom && $dateTo) {
            try {
                $lxDays = max(1, \Carbon\Carbon::parse($dateFrom)->diffInDays(\Carbon\Carbon::parse($dateTo)) + 1);
            } catch (\Throwable $e) {
                $lxDays = 30;
            }
        }
    @endphp

    <!-- Quick Stats Dashboard (Overview only) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Failed Contacts -->
        @php
            $failedContactsCount = $selected_type === 'failed_contacts' 
                ? $failed_strings->count() 
                : ($failed_contact_counts['combined']['contacts'] ?? 0);
        @endphp
        <a href="{{ route('notices.issues', array_merge($baseQuery, ['type' => 'failed_contacts'])) }}"
           class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Failed Contacts</p>
                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-300 mt-1">{{ number_format($failedContactsCount) }}</p>
                </div>
                <div class="flex-shrink-0 p-3 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>
        </a>

        <!-- Affected Patrons -->
        @php
            $affectedPatronsCount = $selected_type === 'failed_contacts' 
                ? $failed_strings->sum('affected_patron_count') 
                : ($failed_contact_counts['combined']['patrons'] ?? 0);
        @endphp
        <a href="{{ route('notices.issues', array_merge($baseQuery, ['type' => 'failed_contacts'])) }}"
           class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Affected Patrons</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($affectedPatronsCount) }}</p>
                </div>
                <div class="flex-shrink-0 p-3 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
        </a>

        <!-- Total Failures -->
        @php
            if ($selected_type === 'failed_contacts') {
                $totalFailuresCount = $failed_strings->sum('failure_count');
            } else {
                // For overview, use the same combined counts (includes mismatched_preference)
                $totalFailuresCount = $failed_contact_counts['combined']['failures'] ?? 0;
            }
        @endphp
        <a href="{{ route('notices.index', array_merge($baseQuery, ['status' => 'failed'])) }}"
           class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Total Failures</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ number_format($totalFailuresCount) }}</p>
                </div>
                <div class="flex-shrink-0 p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2z"></path>
                    </svg>
                </div>
            </div>
        </a>

        <!-- LX Starter Failures -->
        <a href="{{ route('notices.issues.orphaned', ['orphaned_days' => $lxDays]) }}"
           class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">LX Starter Failures</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400 mt-1">{{ number_format($lx_starter_failures ?? 0) }}</p>
                </div>
                <div class="flex-shrink-0 p-3 bg-orange-50 dark:bg-orange-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                </div>
            </div>
        </a>
    </div>

    <!-- Failure Charts (Overview only) -->
    <div class="mb-6">
        <!-- notices/issues charts: pie+fallback v2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 w-full">
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Failures by Delivery Method</h3>
                    <a href="{{ route('notices.index', array_merge($baseQuery, ['status' => 'failed'])) }}"
                       class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline">
                        View failed notifications
                    </a>
                </div>
                <div class="h-56 relative">
                    <canvas id="failedByMethodChart" class="absolute inset-0"></canvas>
                    <div id="failedByMethodFallback" class="absolute inset-0 overflow-auto px-1">
                        @php
                            $failedByMethodRows = ($failed_by_method ?? collect())->values();
                        @endphp
                        @if($failedByMethodRows->isEmpty())
                            <div class="h-full flex items-center justify-center text-sm text-slate-500 dark:text-slate-400">
                                No failed notifications in this range.
                            </div>
                        @else
                            @php
                                $methodTotal = (int) $failedByMethodRows->sum(fn ($r) => (int) ($r->failed ?? 0));
                            @endphp
                            <ul class="space-y-2 py-2">
                                @foreach($failedByMethodRows as $row)
                                    @php
                                        $count = (int) ($row->failed ?? 0);
                                        $pct = $methodTotal > 0 ? round(($count / $methodTotal) * 100, 1) : 0;
                                    @endphp
                                    <li class="flex items-center justify-between gap-3 text-sm text-slate-700 dark:text-slate-200">
                                        <span class="truncate">{{ $row->label ?? 'Unknown' }}</span>
                                        <span class="tabular-nums font-semibold whitespace-nowrap">
                                            {{ number_format($count) }} <span class="text-slate-500 dark:text-slate-400 font-medium">({{ $pct }}%)</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Failures by Notification Type</h3>
                    <a href="{{ route('notices.index', array_merge($baseQuery, ['status' => 'failed'])) }}"
                       class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline">
                        View failed notifications
                    </a>
                </div>
                <div class="h-56 relative">
                    <canvas id="failedByTypeChart" class="absolute inset-0"></canvas>
                    <div id="failedByTypeFallback" class="absolute inset-0 overflow-auto px-1">
                        @php
                            $failedByTypeRows = ($failed_by_type ?? collect())->values();
                        @endphp
                        @if($failedByTypeRows->isEmpty())
                            <div class="h-full flex items-center justify-center text-sm text-slate-500 dark:text-slate-400">
                                No failed notifications in this range.
                            </div>
                        @else
                            @php
                                $typeTotal = (int) $failedByTypeRows->sum(fn ($r) => (int) ($r->failed ?? 0));
                            @endphp
                            <ul class="space-y-2 py-2">
                                @foreach($failedByTypeRows as $row)
                                    @php
                                        $count = (int) ($row->failed ?? 0);
                                        $pct = $typeTotal > 0 ? round(($count / $typeTotal) * 100, 1) : 0;
                                    @endphp
                                    <li class="flex items-center justify-between gap-3 text-sm text-slate-700 dark:text-slate-200">
                                        <span class="truncate">{{ $row->notification_type ?? 'Unknown' }}</span>
                                        <span class="tabular-nums font-semibold whitespace-nowrap">
                                            {{ number_format($count) }} <span class="text-slate-500 dark:text-slate-400 font-medium">({{ $pct }}%)</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Chart.js for charts on this page -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endif

    <!-- Polaris → LX Starter Communication Failures Summary -->
    <!-- Overview only -->
    @if($orphaned_data['total_orphaned'] > 0 && !$selected_type)
    <div class="mb-6">
        <div class="block bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 rounded-lg border-2 border-orange-300 dark:border-orange-700 shadow-md hover:shadow-xl transition-all group">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 p-3 bg-orange-100 dark:bg-orange-900/40 rounded-lg group-hover:bg-orange-200 dark:group-hover:bg-orange-900/60 transition-colors">
                            <svg class="w-7 h-7 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                Polaris → LX Starter Communication Failure
                                <svg class="w-5 h-5 text-orange-500 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">Notifications exported but never sent</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-4xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($orphaned_data['total_orphaned']) }}</p>
                        <form method="GET" action="{{ route('notices.issues') }}" class="mt-2">
                            @if($selected_type)
                                <input type="hidden" name="type" value="{{ $selected_type }}">
                            @endif
                            <select name="orphaned_days" onchange="this.form.submit()" class="text-xs px-2 py-1 border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                <option value="1" {{ $orphaned_days == 1 ? 'selected' : '' }}>last 24 hours</option>
                                <option value="3" {{ $orphaned_days == 3 ? 'selected' : '' }}>last 3 days</option>
                                <option value="7" {{ $orphaned_days == 7 ? 'selected' : '' }}>last 7 days</option>
                                <option value="14" {{ $orphaned_days == 14 ? 'selected' : '' }}>last 14 days</option>
                                <option value="30" {{ $orphaned_days == 30 ? 'selected' : '' }}>last 30 days</option>
                            </select>
                        </form>
                    </div>
                </div>

                @if($orphaned_data['pattern_analysis']['has_pattern'])
                <div class="mb-4 p-3 bg-white/60 dark:bg-slate-800/60 rounded-lg border border-orange-200 dark:border-orange-800">
                    <p class="text-sm text-slate-700 dark:text-slate-300">
                        <strong>Pattern:</strong> {{ $orphaned_data['pattern_analysis']['description'] }}
                    </p>
                </div>
                @endif

                <!-- Mini Chart -->
                <div class="bg-white/60 dark:bg-slate-800/60 rounded-lg p-4">
                    <canvas id="orphanedChart" height="80"></canvas>
                </div>

                <a href="{{ route('notices.issues.orphaned', ['orphaned_days' => $orphaned_days ?? 30]) }}"
                   class="mt-4 flex items-center justify-center gap-2 text-sm text-orange-700 dark:text-orange-300 font-medium hover:underline">
                    <span>Click to view full analysis and clusters</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Prepare data for Chart.js - show daily summary with all days (including zeros)
        const orphanedDaily = @json($orphaned_data['daily_summary']);
        const labels = orphanedDaily.map(c => {
            const date = new Date(c.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        const data = orphanedDaily.map(c => c.count);

        // Create chart
        const ctx = document.getElementById('orphanedChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Orphaned Notifications',
                    data: data,
                    borderColor: 'rgb(234, 88, 12)',
                    backgroundColor: 'rgba(234, 88, 12, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgb(234, 88, 12)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Orphaned: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
    @endif

    <!-- SMS Opt-Out Failures Card -->
    @if($sms_opt_out_data['total_patrons'] > 0)
    <div class="mb-6">
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-lg border-2 border-purple-300 dark:border-purple-700 shadow-md">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 p-3 bg-purple-100 dark:bg-purple-900/40 rounded-lg">
                            <svg class="w-7 h-7 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">SMS Opt-Out Failures</h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">Patrons who opted out from SMS notifications</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-4xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($sms_opt_out_data['total_patrons']) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">affected patrons</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">last 30 days</p>
                    </div>
                </div>

                <div class="bg-white/60 dark:bg-slate-800/60 rounded-lg p-4">
                    <div class="flex items-start gap-3 mb-4">
                        <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Recommended Action</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                Contact patrons to update their phone number or switch to a different delivery method (Email, Voice, or Mail).
                            </p>
                        </div>
                    </div>

                    <!-- List of affected patrons -->
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($sms_opt_out_data['patrons']->take(10) as $patron)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-700 rounded border border-purple-200 dark:border-purple-800 gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white font-mono">{{ $patron['patron_barcode'] }}</p>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">{{ $patron['failure_reason'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-500 mt-1">
                                    {{ $patron['notification_count'] }} failed notification(s) • Last: {{ $patron['last_failed_at']->format('M j, Y') }}
                                </p>
                            </div>
                            @if($patron['patron_id'])
                            <a href="https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/{{ $patron['patron_id'] }}/record"
                               target="_blank"
                               class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-dcpl-blue text-white text-sm font-medium rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors whitespace-nowrap">
                                Update Patron's Phone Number or Delivery Option
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    @if($sms_opt_out_data['total_patrons'] > 10)
                    <p class="text-xs text-slate-500 dark:text-slate-500 mt-3 text-center">
                        Showing 10 of {{ $sms_opt_out_data['total_patrons'] }} affected patrons
                    </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($selected_type === 'failed_contacts')
        <!-- Failed Contacts View -->
        <x-notices::filter-panel
            :action="route('notices.issues', ['type' => 'failed_contacts', 'show_resolved' => request('show_resolved') ? 1 : null])"
            method="GET"
            searchLabel="Search"
            searchName="search"
            :searchValue="$filters['search'] ?? ''"
            searchPlaceholder="Patron, barcode, phone, or email..."
            :dateFrom="$filters['date_from'] ?? ''"
            :dateTo="$filters['date_to'] ?? ''"
            :quickRange="request('quick_range', 'all')"
            :clearRoute="route('notices.issues', ['type' => 'failed_contacts', 'show_resolved' => request('show_resolved') ? 1 : null])"
            :hasAdvanced="false"
        />

        @if($failed_strings->isEmpty())
            <!-- Empty State -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-green-500 dark:text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">No Failed Contacts</h3>
                <p class="text-slate-600 dark:text-slate-400">
                    There are currently no email addresses or phone numbers experiencing delivery failures.
                </p>
            </div>
        @else
            <!-- Failed Strings Table (matching dashboard card format) -->
            @php
                // Use override-aware delivery method labels for filter pills.
                $lookupLabelService = app(\Dcplibrary\Notices\Services\LookupLabelService::class);
                $emailLabel = $lookupLabelService->getDeliveryLabel(2);
                $voiceLabel = $lookupLabelService->getDeliveryLabel(3);
                $smsLabel   = $lookupLabelService->getDeliveryLabel(8);
            @endphp
            <div x-data="{ filter: 'all', showResolved: {{ request('show_resolved') ? 'true' : 'false' }} }" class="bg-purple-50 dark:bg-purple-900/20 rounded-2xl border border-purple-200 dark:border-purple-700 shadow-sm overflow-hidden">
                <!-- Fix Patron Notification Issues Header + filters -->
                <div class="px-6 py-4 border-b border-purple-100/80 dark:border-purple-800/60 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-purple-500/10 dark:bg-purple-900/60 flex items-center justify-center">
                            <!-- Alert icon -->
                            <svg class="w-5 h-5 text-purple-500 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">Fix Patron Notification Issues</h3>
                                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-white/70 dark:bg-slate-900/40 border border-purple-200 dark:border-purple-800 text-[11px] font-semibold text-slate-700 dark:text-slate-200 tabular-nums min-w-[3rem]">
                                    {{ number_format($failed_strings->count()) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Filter buttons -->
                    <div class="flex items-center gap-2 text-xs">
                        <button
                            type="button"
                            @click="filter = 'all'"
                            :class="filter === 'all'
                                ? 'bg-purple-600 text-white shadow-sm'
                                : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                            class="px-4 py-1.5 rounded-full font-semibold"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            @click="filter = 'email'"
                            :class="filter === 'email'
                                ? 'bg-purple-600 text-white shadow-sm'
                                : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                            class="px-4 py-1.5 rounded-full font-semibold"
                        >
                            {{ $emailLabel }} Issues
                        </button>
                        <button
                            type="button"
                            @click="filter = 'voice'"
                            :class="filter === 'voice'
                                ? 'bg-purple-600 text-white shadow-sm'
                                : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                            class="px-4 py-1.5 rounded-full font-semibold"
                        >
                            {{ $voiceLabel }} Issues
                        </button>
                        <button
                            type="button"
                            @click="filter = 'sms'"
                            :class="filter === 'sms'
                                ? 'bg-purple-600 text-white shadow-sm'
                                : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                            class="px-4 py-1.5 rounded-full font-semibold"
                        >
                            {{ $smsLabel }} Issues
                        </button>
                        <button
                            type="button"
                            @click="showResolved = !showResolved"
                            :class="showResolved
                                ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-100 dark:text-slate-900'
                                : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                            class="px-4 py-1.5 rounded-full font-semibold"
                        >
                            Show Resolved
                        </button>
                    </div>
                </div>

                <!-- Header row -->
                <div class="bg-slate-50 dark:bg-slate-800/40">
                    <div class="px-6 py-3 grid grid-cols-[7.5rem_minmax(10rem,1fr)_minmax(10rem,1.15fr)_5.25rem_minmax(14rem,2fr)_7.5rem] gap-3 text-[11px] font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wide">
                        <div class="truncate">Barcode</div>
                        <div>Name</div>
                        <div>Phone / Email</div>
                        <div class="text-center whitespace-nowrap">Method</div>
                        <div>Issue Type</div>
                        <div class="text-right">Last Attempt</div>
                    </div>
                </div>

                <!-- Rows -->
                <div class="bg-white dark:bg-slate-900/40 divide-y divide-purple-100 dark:divide-purple-900/40">
                    @foreach($failed_strings as $failedString)
                        @php
                            $patron   = $failedString->patron ?? null;
                            $deliveryOptionId = (int) ($failedString->delivery_option_id ?? 0);
                            $isSms   = $deliveryOptionId === 8;
                            $isVoice = $deliveryOptionId === 3;
                            $isPhone  = ($failedString->string_type ?? null) === 'phone' || in_array($deliveryOptionId, [3, 8], true);
                            // Filter key for pills: email / voice / sms
                            $issueType = $isSms ? 'sms' : ($isVoice ? 'voice' : 'email');
                            $emailValue = !$isPhone ? $failedString->delivery_string : ($patron->email ?? null);
                            $phoneValue = $isPhone ? $failedString->delivery_string : ($patron->phone ?? null);
                            
                            // Check if this is a mismatched preference issue
                            $isMismatched = ($failedString->issue_type ?? null) === 'mismatched_preference';

                            // Derive ui_messages key from failure context
                            if ($isMismatched) {
                                $messageKey = 'mismatched_preference';
                            } elseif (!$isPhone) {
                                // Email failures – categorize based on failure details/source
                                $reason = strtolower($failedString->failure_details ?? $failedString->failure_source ?? '');
                                if (str_contains($reason, 'invalid')) {
                                    $messageKey = 'email_invalid_address';
                                } elseif (str_contains($reason, 'bounce') || str_contains($reason, 'bounced')) {
                                    $messageKey = 'email_bounce';
                                } else {
                                    $messageKey = 'email_delivery_failure';
                                }
                            } else {
                                // SMS/Voice (Shoutbomb) failures – mirror mapping used in notification-detail-modal
                                $failureType = $failedString->failure_source ?? '';
                                $messageKey = match($failureType) {
                                    'Opted Out'            => 'sms_opt_out',
                                    'Invalid Phone Number' => 'sms_invalid_phone',
                                    'shoutbomb_invalid_phone' => 'sms_invalid_phone',
                                    'Landline'            => 'sms_landline',
                                    'Carrier Issue'       => 'sms_carrier_issue',
                                    'shoutbomb_voice_system_error' => 'sms_delivery_failure',
                                    'shoutbomb_voice_failure' => 'sms_delivery_failure',
                                    default               => 'sms_delivery_failure',
                                };
                            }

                            // Resolve label: override -> default config -> prettified key
                            $labelsConfig = config('notices.ui_labels', []);
                            $defaultLabel = $labelsConfig[$messageKey] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $messageKey));
                            $override    = \Dcplibrary\Notices\Models\UiMessage::where('key', $messageKey)->first();
                            $issueLabel  = $override->label ?? $defaultLabel;
                            
                            // For mismatched preference, use the issue details as the label if available
                            if ($isMismatched && !empty($failedString->failure_details)) {
                                // Extract a short description from issue_details
                                $issueLabel = 'Mismatched Preference';
                            }
                        @endphp
                    <div
                            class="px-6 py-3 grid grid-cols-[7.5rem_minmax(10rem,1fr)_minmax(10rem,1.15fr)_5.25rem_minmax(14rem,2fr)_7.5rem] gap-3 items-center text-xs text-slate-700 dark:text-slate-200 hover:bg-purple-50/80 dark:hover:bg-purple-900/40 transition-colors cursor-pointer"
                            x-show="(filter === 'all' || filter === '{{ $issueType }}') && (showResolved || !{{ (int)($failedString->is_resolved ?? 0) }})"
                            @if(isset($failedString->issue_type) && $failedString->issue_type === 'mismatched_preference')
                                onclick="window.location.href='{{ route('notices.issues', ['issue_type' => 'mismatched_preference', 'search' => $patron->patron_barcode ?? $failedString->example_patron_barcode ?? '']) }}'"
                            @else
                                @click="$dispatch('failed-contact-open', { id: {{ json_encode($failedString->id) }} })"
                            @endif
                        >
                            <!-- Barcode -->
                            <div class="font-mono text-[11px] text-slate-600 dark:text-slate-400 truncate">
                                {{ $patron->patron_barcode ?? $failedString->example_patron_barcode ?? '—' }}
                            </div>

                            <!-- Name -->
                            <div class="flex flex-col gap-0.5">
                                @if($patron && ($patron->patron_last_name || $patron->patron_first_name))
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ $patron->patron_last_name ?? '' }}@if($patron->patron_last_name && $patron->patron_first_name), @endif{{ $patron->patron_first_name ?? '' }}
                                    </div>
                                @else
                                    <div class="font-medium text-slate-900 dark:text-white">Unknown patron</div>
                                @endif
                                @if($patron && $patron->patron_id)
                                    <a
                                        href="https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/{{ $patron->patron_id }}/record"
                                        target="_blank"
                                        class="text-[10px] text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                        @click.stop
                                    >
                                        View in Polaris
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>

                            <!-- Phone / Email (problematic contact) -->
                            <div class="text-xs text-slate-800 dark:text-slate-100 break-all">
                                @if($isPhone)
                                    {{ $phoneValue ?? '—' }}
                                @else
                                    {{ $emailValue ?? '—' }}
                                @endif
                            </div>

                            <!-- Delivery Method (Icon) -->
                            <div class="flex justify-center">
                                @if((int) $failedString->delivery_option_id === 1)
                                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Mail" aria-label="Mail">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                @elseif((int) $failedString->delivery_option_id === 2)
                                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Email" aria-label="Email">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                    </svg>
                                @elseif((int) $failedString->delivery_option_id === 3)
                                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Voice" aria-label="Voice">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                @elseif((int) $failedString->delivery_option_id === 8)
                                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="SMS" aria-label="SMS">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                    </svg>
                                @else
                                    <span class="text-slate-500 dark:text-slate-500 italic" title="Unknown" aria-label="Unknown">—</span>
                                @endif
                            </div>

                            <!-- Issue Type (UI message override label) -->
                            <div class="text-xs font-medium">
                                {{ $issueLabel }}
                            </div>

                            <!-- Last Attempt -->
                            <div class="text-xs text-slate-700 dark:text-slate-200 text-right">
                                @if($failedString->last_failed_at)
                                    {{ \Carbon\Carbon::parse($failedString->last_failed_at)->format('Y-m-d H:i') }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    @elseif($selected_type)
        <!-- Search and Filters -->
        <div class="mb-6">
            <form method="GET" action="{{ route('notices.issues') }}" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <!-- Preserve type filter -->
                @if($selected_type)
                    <input type="hidden" name="type" value="{{ $selected_type }}">
                @endif
                @if(!empty($filters['date_from']))
                    <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
                @endif
                @if(!empty($filters['date_to']))
                    <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
                @endif
                @if(request('quick_range'))
                    <input type="hidden" name="quick_range" value="{{ request('quick_range') }}">
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Combined Search -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Search
                        </label>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Patron, barcode, title, or item..."
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                    </div>

                    <!-- Delivery Method -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Delivery Method
                        </label>
                        <select
                            name="delivery_option_id"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                            <option value="">All Methods</option>
                            @foreach($delivery_options as $option)
                                <option value="{{ $option->id }}" {{ ($filters['delivery_option_id'] ?? '') == $option->id ? 'selected' : '' }}>
                                    {{ $option->delivery_option }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-dcpl-blue text-white rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors text-sm font-medium"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search
                    </button>
                    <a
                        href="{{ route('notices.issues', ['type' => $selected_type]) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors text-sm font-medium"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results Count and Bulk Actions -->
        <div class="mb-4 flex items-center justify-between">
            @if($issues->total() > 0)
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Showing {{ $issues->firstItem() }} to {{ $issues->lastItem() }} of {{ $issues->total() }} issues
                </p>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    No issues match the current filters.
                </p>
            @endif

            <!-- Bulk Resolve Button (hidden until items selected) -->
            <div x-data="{ selectedCount: 0 }" @update-selection.window="selectedCount = $event.detail.count">
                <button
                    x-show="selectedCount > 0"
                    x-cloak
                    @click="$dispatch('bulk-resolve-open')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 dark:bg-green-500 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors text-sm font-medium"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Resolve <span x-text="selectedCount"></span> Selected
                </button>
            </div>
        </div>

        <!-- Issues Table -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden"
             x-data="{
                 selectedIssues: [],
                 selectAll: false,

                 toggleAll() {
                     if (this.selectAll) {
                         this.selectedIssues = Array.from(document.querySelectorAll('[data-issue-id]')).map(el => parseInt(el.dataset.issueId));
                     } else {
                         this.selectedIssues = [];
                     }
                     this.updateCount();
                 },

                 toggle(id) {
                     const index = this.selectedIssues.indexOf(id);
                     if (index > -1) {
                         this.selectedIssues.splice(index, 1);
                     } else {
                         this.selectedIssues.push(id);
                     }
                     this.selectAll = this.selectedIssues.length === document.querySelectorAll('[data-issue-id]').length;
                     this.updateCount();
                 },

                 updateCount() {
                     this.$dispatch('update-selection', { count: this.selectedIssues.length });
                 }
             }"
             @bulk-resolve-open.window="if (selectedIssues.length > 0) $refs.bulkResolveModal.showModal()"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-100 dark:bg-slate-900/50">
                        <tr>
                            @php
                                $currentSort = $sort ?? 'notification_date';
                                $currentDir = $dir ?? 'desc';

                                $sortUrl = function($column) use ($currentSort, $currentDir, $filters, $selected_type) {
                                    $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
                                    $params = array_merge($filters, ['sort' => $column, 'dir' => $newDir]);
                                    if ($selected_type) {
                                        $params['type'] = $selected_type;
                                    }
                                    return route('notices.issues', $params);
                                };

                                $sortIcon = function($column) use ($currentSort, $currentDir) {
                                    if ($currentSort !== $column) {
                                        return '';
                                    }
                                    return $currentDir === 'asc' ? '▲' : '▼';
                                };
                            @endphp

                            <th class="px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    x-model="selectAll"
                                    @change="toggleAll()"
                                    class="rounded border-slate-300 dark:border-slate-600 text-dcpl-blue focus:ring-dcpl-blue dark:bg-slate-700"
                                >
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'patron_barcode', 'label' => 'Patron'])
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'item', 'label' => 'Item'])
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'issue_type', 'label' => 'Issue'])
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'delivery_option_id', 'label' => 'Method'])
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                @include('notices::partials.sortable-header', ['column' => 'notification_date', 'label' => 'Date'])
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($issues as $issue)
                            <tr
                                class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors"
                                :class="{ 'bg-dcpl-blue/10 dark:bg-dcpl-blue/15': selectedIssues.includes({{ $issue->id }}) }"
                            >
                                <!-- Checkbox -->
                                <td class="px-4 py-4" @click.stop>
                                    <input
                                        type="checkbox"
                                        data-issue-id="{{ $issue->id }}"
                                        :checked="selectedIssues.includes({{ $issue->id }})"
                                        @change="toggle({{ $issue->id }})"
                                        class="rounded border-slate-300 dark:border-slate-600 text-dcpl-blue focus:ring-dcpl-blue dark:bg-slate-700"
                                    >
                                </td>

                                <!-- Patron -->
                                <td class="px-6 py-4 text-sm cursor-pointer" @click="$dispatch('issue-detail-open', { id: {{ $issue->id }} })">
                                    @php
                                        $payload = $issue->notification ? json_decode($issue->notification->raw_payload, true) : null;
                                        $patronFirstName = $payload[0]['patron_first_name'] ?? '';
                                        $patronLastName = $payload[0]['patron_last_name'] ?? '';
                                    @endphp
                                    <div class="text-slate-900 dark:text-white">{{ $patronLastName }}, {{ $patronFirstName }}</div>
                                    <a href="{{ route('notices.patron', $issue->patron_barcode) }}" class="text-dcpl-blue dark:text-sky-300 hover:underline font-mono text-xs">
                                        {{ $issue->patron_barcode }}
                                    </a>
                                </td>

                                <!-- Item -->
                                <td class="px-6 py-4 text-sm cursor-pointer" @click="$dispatch('issue-detail-open', { id: {{ $issue->id }} })">
                                    @php
                                        $title = $payload[0]['title'] ?? '';
                                        $itemBarcode = $payload[0]['item_barcode'] ?? '';
                                    @endphp
                                    <div class="text-slate-900 dark:text-white truncate max-w-xs" title="{{ $title }}">{{ $title }}</div>
                                    <div class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ $itemBarcode }}</div>
                                </td>

                                <!-- Issue Description -->
                                <td class="px-6 py-4 text-sm" @click="$dispatch('issue-detail-open', { id: {{ $issue->id }} })">
                                    @php
                                        $deliveryMethod = '';
                                        if ($issue->issue_type === 'lx_starter_failure' && $issue->notification && $issue->notification->deliveryOption) {
                                            $deliveryMethod = $issue->notification->deliveryOption->display_label;
                                        }

                                        $issueDescription = match($issue->issue_type) {
                                            'failed_delivery_string' => $issue->failedDeliveryString ? "Failed contact: {$issue->failedDeliveryString->delivery_string}" : 'Invalid contact information',
                                            'lx_starter_failure' => $deliveryMethod ? "{$deliveryMethod} not delivered" : 'Not delivered to patron',
                                            'null_preference' => 'No Notification Option',
                                            'manual_review' => 'Requires manual review',
                                            'system_error' => 'System processing error',
                                            default => $issue->issue_details ?? str_replace('_', ' ', ucwords($issue->issue_type))
                                        };
                                    @endphp
                                    <div class="cursor-pointer">
                                        <div class="text-slate-900 dark:text-white">{{ $issueDescription }}</div>
                                        @if($issue->issue_type === 'failed_delivery_string' && $issue->notification && $issue->notification->delivery_option_id == 8)
                                            <x-notices::status-message key="sms_invalid_phone" class="mt-1" />
                                        @endif
                                        @if($issue->issue_type === 'null_preference')
                                            <x-notices::status-message key="null_preference" class="mt-1" />
                                        @endif
                                        @if($issue->issue_type === 'lx_starter_failure')
                                            <a href="https://lx-na3.iiivega.com/davi/audience/patrons/{{ $issue->patron_barcode }}"
                                               target="_blank"
                                               onclick="event.stopPropagation()"
                                               class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1">
                                                Check LX Starter
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                </svg>
                                            </a>
                                        @elseif($issue->issue_type === 'null_preference')
                                            <a href="https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/{{ $issue->patron_id }}/record"
                                               target="_blank"
                                               onclick="event.stopPropagation()"
                                               class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1">
                                                Edit Patron
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>

                                <!-- Delivery Method (Icon) -->
                                <td class="px-6 py-4 text-sm cursor-pointer" @click="$dispatch('issue-detail-open', { id: {{ $issue->id }} })">
                                    @if($issue->notification && $issue->notification->deliveryOption)
                                        @if($issue->notification->delivery_option_id == 1)
                                            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Mail">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                        @elseif($issue->notification->delivery_option_id == 2)
                                            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Email">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                            </svg>
                                        @elseif($issue->notification->delivery_option_id == 3)
                                            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Voice">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                        @elseif($issue->notification->delivery_option_id == 8)
                                            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="SMS">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                            </svg>
                                        @endif
                                    @else
                                        <span class="text-slate-500 dark:text-slate-500 italic" title="Unknown">?</span>
                                    @endif
                                </td>

                                <!-- Date -->
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400 cursor-pointer" @click="$dispatch('issue-detail-open', { id: {{ $issue->id }} })">
                                    @if($issue->notification && $issue->notification->first_exported_at)
                                        <div>{{ \Carbon\Carbon::parse($issue->notification->first_exported_at)->format('M j, Y') }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-500">{{ \Carbon\Carbon::parse($issue->notification->first_exported_at)->format('g:i A') }}</div>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $issues->appends(array_merge($filters, ['type' => $selected_type, 'sort' => $sort, 'dir' => $dir]))->links() }}
        </div>

        <!-- Bulk Resolve Modal -->
        <dialog x-ref="bulkResolveModal" class="backdrop:bg-black/50 bg-white dark:bg-slate-800 rounded-lg shadow-xl p-0 max-w-md w-full">
            <div class="p-6" x-data="{ submitting: false }">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Bulk Resolve Issues</h3>
                    <button @click="$refs.bulkResolveModal.close()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('notices.issues.bulk-resolve') }}" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="issue_ids" :value="JSON.stringify(selectedIssues)">

                    <div class="mb-4">
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                            You are about to resolve <strong x-text="selectedIssues.length"></strong> issue(s).
                        </p>

                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Resolution Notes <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            name="resolution_notes"
                            rows="4"
                            required
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                            placeholder="Describe how these issues were resolved..."
                        ></textarea>
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button
                            type="button"
                            @click="$refs.bulkResolveModal.close()"
                            :disabled="submitting"
                            class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="px-4 py-2 bg-green-600 dark:bg-green-500 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors disabled:opacity-50 inline-flex items-center gap-2"
                        >
                            <svg x-show="submitting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="submitting ? 'Resolving...' : 'Resolve Issues'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif

    @if(!$selected_type)
        <script>
            // Shared query params for chart click-throughs
            const issuesBaseQuery = @json($baseQuery);

            const chartColors = (count) => {
                const colors = [];
                for (let i = 0; i < count; i++) {
                    // Evenly distributed hues for a readable palette.
                    const hue = Math.round((360 / Math.max(count, 1)) * i);
                    colors.push(`hsla(${hue}, 75%, 55%, 0.75)`);
                }
                return colors;
            };

            // Failures by delivery method
            const failedByMethodRows = @json(($failed_by_method ?? collect())->values());
            const methodLabels = failedByMethodRows.map(r => r.label);
            const methodCounts = failedByMethodRows.map(r => Number(r.failed));
            const methodIds = failedByMethodRows.map(r => Number(r.delivery_option_id));
            const methodTotal = methodCounts.reduce((acc, n) => acc + (Number.isFinite(n) ? n : 0), 0);
            const methodFallbackEl = document.getElementById('failedByMethodFallback');

            const methodCtx = document.getElementById('failedByMethodChart')?.getContext('2d');
            if (typeof Chart !== 'undefined' && methodCtx && methodLabels.length && methodTotal > 0) {
                try {
                    const chart = new Chart(methodCtx, {
                        type: 'pie',
                        data: {
                            labels: methodLabels,
                            datasets: [{
                                label: 'Failures',
                                data: methodCounts,
                                backgroundColor: chartColors(methodLabels.length),
                                borderColor: 'rgba(15, 23, 42, 0.15)', // slate-900 w/ alpha
                                borderWidth: 1,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10,
                                        boxHeight: 10,
                                        padding: 10,
                                        font: { size: 11 },
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => {
                                            const idx = ctx.dataIndex;
                                            const cnt = Number(methodCounts[idx] ?? 0);
                                            const pct = methodTotal > 0 ? (cnt / methodTotal) * 100 : 0;
                                            return `${ctx.label}: ${pct.toFixed(1)}% (${cnt} of ${methodTotal})`;
                                        }
                                    }
                                },
                            },
                            onClick: (evt, elements) => {
                                if (!elements || !elements.length) return;
                                const idx = elements[0].index;
                                const deliveryOptionId = methodIds[idx];
                                const url = new URL(@json(route('notices.index')));
                                const params = { ...issuesBaseQuery, status: 'failed', delivery_option_id: String(deliveryOptionId) };
                                Object.entries(params).forEach(([k, v]) => {
                                    if (v === null || v === undefined || v === '') return;
                                    url.searchParams.set(k, String(v));
                                });
                                window.location.href = url.toString();
                            }
                        }
                    });

                    // Only hide fallback once the chart is successfully created.
                    if (chart && methodFallbackEl) {
                        methodFallbackEl.classList.add('hidden');
                    }
                } catch (e) {
                    // Keep fallback visible if chart fails to render.
                    console.error('[notices] failedByMethodChart render failed', e);
                }
            }

            // Failures by notification type
            const failedByTypeRows = @json(($failed_by_type ?? collect())->values());
            const typeLabels = failedByTypeRows.map(r => r.notification_type);
            const typeCounts = failedByTypeRows.map(r => Number(r.failed));
            const typeIds = failedByTypeRows.map(r => Number(r.notification_type_id));
            const typeTotal = typeCounts.reduce((acc, n) => acc + (Number.isFinite(n) ? n : 0), 0);
            const typeFallbackEl = document.getElementById('failedByTypeFallback');

            const typeCtx = document.getElementById('failedByTypeChart')?.getContext('2d');
            if (typeof Chart !== 'undefined' && typeCtx && typeLabels.length && typeTotal > 0) {
                try {
                    const chart = new Chart(typeCtx, {
                        type: 'pie',
                        data: {
                            labels: typeLabels,
                            datasets: [{
                                label: 'Failures',
                                data: typeCounts,
                                backgroundColor: chartColors(typeLabels.length),
                                borderColor: 'rgba(15, 23, 42, 0.15)', // slate-900 w/ alpha
                                borderWidth: 1,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10,
                                        boxHeight: 10,
                                        padding: 10,
                                        font: { size: 11 },
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => {
                                            const idx = ctx.dataIndex;
                                            const cnt = Number(typeCounts[idx] ?? 0);
                                            const pct = typeTotal > 0 ? (cnt / typeTotal) * 100 : 0;
                                            return `${ctx.label}: ${pct.toFixed(1)}% (${cnt} of ${typeTotal})`;
                                        }
                                    }
                                },
                            },
                            onClick: (evt, elements) => {
                                if (!elements || !elements.length) return;
                                const idx = elements[0].index;
                                const url = new URL(@json(route('notices.index')));
                                const notificationTypeId = typeIds[idx];
                                if (!Number.isFinite(notificationTypeId) || notificationTypeId <= 0) return;
                                const params = { ...issuesBaseQuery, status: 'failed', notification_type: String(notificationTypeId) };
                                Object.entries(params).forEach(([k, v]) => {
                                    if (v === null || v === undefined || v === '') return;
                                    url.searchParams.set(k, String(v));
                                });
                                window.location.href = url.toString();
                            }
                        }
                    });

                    // Only hide fallback once the chart is successfully created.
                    if (chart && typeFallbackEl) {
                        typeFallbackEl.classList.add('hidden');
                    }
                } catch (e) {
                    // Keep fallback visible if chart fails to render.
                    console.error('[notices] failedByTypeChart render failed', e);
                }
            }
        </script>
    @endif

    <!-- Issue Detail Modal -->
    <x-notices::issue-detail-modal />

    <!-- Failed Contact Edit/Resolve Modal -->
    <x-notices::failed-contact-modal />
 
    <!-- Notification Detail Modal (for cross-component navigation) -->
    <x-notices::unified-notification-modal :admin="true" />
</div>
</div>
@endsection
