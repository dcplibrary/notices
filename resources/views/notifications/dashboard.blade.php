@extends('notices::layouts.app')

@section('title', 'Notification Dashboard')

@section('body')
<div class="notices-staff-page pb-10">
    <div class="max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div class="border-l-4 border-dcpl-blue pl-4">
            <h1 class="font-outfit text-3xl font-semibold tracking-tight text-dcpl-text mb-1">Overview</h1>
            <p class="text-sm text-gray-600">
                Current status of notification delivery.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200">
                <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2m-9 4h10M5 21h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span class="font-medium">{{ $date_from }}&nbsp;–&nbsp;{{ $date_to }}</span>
            </div>

            @if(!empty($quick_range) && $quick_range !== 'custom')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span class="uppercase tracking-wide">{{ str_replace('_', ' ', $quick_range) }}</span>
                </span>
            @endif
        </div>
    </div>

    <!-- Global Search Bar -->
    <div x-data="globalSearch()" @keydown.window.escape="close()" class="mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm ring-1 ring-dcpl-blue/5 p-4 flex flex-col gap-3 md:flex-row md:items-center md:gap-4">
            <div class="flex-1 flex items-center gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        x-model="query"
                        @keydown.enter.prevent="search()"
                        type="text"
                        class="w-full h-11 pl-9 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                        placeholder="Search patrons, emails, phone numbers, notifications, issues..."
                    />
                </div>
            </div>
            <button
                @click="search()"
                class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-dcpl-blue hover:brightness-95 shadow-sm"
            >
                Search
            </button>
        </div>

        <!-- Results modal -->
        <div
            x-show="open"
            x-transition
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40"
            style="display:none"
        >
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl max-w-3xl w-full mx-4 max-h-[80vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
                        Search results for “<span x-text="query"></span>”
                    </h2>
                    <button @click="close()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <template x-if="loading">
                        <div class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">
                            Searching…
                        </div>
                    </template>

                    <template x-if="!loading && results.length === 0">
                        <div class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">
                            No results found.
                        </div>
                    </template>

                    <ul class="divide-y divide-slate-200 dark:divide-slate-800" x-show="!loading && results.length">
                        <template x-for="(r, idx) in results" :key="idx">
                            <li class="px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide"
                                              :class="badgeClass(r.type)">
                                            <span x-text="r.type"></span>
                                        </span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400" x-text="r.location"></span>
                                    </div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white truncate" x-text="r.label"></p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 truncate" x-text="r.description"></p>
                                </div>
                                <a
                                    :href="r.url"
                                    class="flex-shrink-0 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-dcpl-blue/10 text-dcpl-blue hover:bg-dcpl-blue/15"
                                >
                                    Open
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="mb-6">
        <form method="GET" action="{{ route('notices.dashboard') }}">
            <x-notices::date-range-filter
                :dateFrom="$date_from"
                :dateTo="$date_to"
                :quickRange="$quick_range ?? 'yesterday'"
            />
        </form>
    </div>

    <!-- Status badges row -->
    @php
        // Failed contacts represent patrons with unresolved contact problems across Email + Voice/SMS.
        // This is a contact-health metric, not an additional status bucket on top of failed notifications.
        $failedContactsPatrons  = (int) ($failed_contact_counts['combined']['patrons']  ?? 0);
        $failedContactsContacts = (int) ($failed_contact_counts['combined']['contacts'] ?? 0);
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <!-- TOTAL -->
        <a href="{{ route('notices.index', ['date_from' => $date_from, 'date_to' => $date_to]) }}"
           class="block rounded-2xl px-6 py-5 bg-dcpl-blue/10 border border-dcpl-blue/25 shadow-sm hover:shadow-md hover:border-dcpl-blue/40 transition-all lg:col-span-1">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-dcpl-text/70 uppercase">
                    <svg class="h-3.5 w-3.5 text-dcpl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span>Total</span>
                </div>
                <div class="font-outfit text-4xl md:text-5xl font-semibold text-dcpl-blue leading-tight">
                    {{ number_format($stats['totals']->total ?? 0) }}
                </div>
                <div class="text-sm font-medium text-slate-600 dark:text-slate-400 leading-tight">
                    100%
                </div>
            </div>
        </a>

        <!-- DELIVERED (green card) -->
        <a href="{{ route('notices.index', ['date_from' => $date_from, 'date_to' => $date_to, 'status' => 'delivered']) }}"
           class="block rounded-2xl px-6 py-5 bg-emerald-50 border border-emerald-100 dark:bg-slate-900/70 dark:border-slate-700 shadow-sm hover:shadow-md transition-all lg:col-span-1">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                    <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Delivered</span>
                </div>
                <div class="text-4xl md:text-5xl font-semibold text-emerald-700 dark:text-emerald-400 leading-tight">
                    {{ number_format($stats['totals']->delivered ?? 0) }}
                </div>
                <div class="text-sm font-medium text-slate-600 dark:text-slate-400 leading-tight">
                    {{ number_format($stats['totals']->success_rate ?? 0, 1) }}%
                </div>
            </div>
        </a>

        <!-- PENDING -->
        <a href="{{ route('notices.index', ['date_from' => $date_from, 'date_to' => $date_to, 'status' => 'pending']) }}"
           class="block rounded-2xl px-6 py-5 bg-amber-50 border border-amber-100 dark:bg-slate-900/70 dark:border-slate-700 shadow-sm hover:shadow-md transition-all order-3 lg:col-span-1 lg:order-4">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                    <svg class="h-3.5 w-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 0 0118 0z" />
                    </svg>
                    <span>Pending</span>
                </div>
                <div class="text-4xl md:text-5xl font-semibold text-amber-800 dark:text-amber-400 leading-tight">
                    {{ number_format($stats['totals']->pending ?? 0) }}
                </div>
                @if(($stats['totals']->total ?? 0) > 0)
                    <div class="text-sm font-medium text-slate-600 dark:text-slate-400 leading-tight">
                        {{ number_format((($stats['totals']->pending ?? 0) / $stats['totals']->total) * 100, 1) }}%
                    </div>
                @endif
            </div>
        </a>

        <!-- FAILED (deliveries) -->
        <a href="{{ route('notices.index', ['date_from' => $date_from, 'date_to' => $date_to, 'status' => 'failed']) }}"
           class="block rounded-2xl px-6 py-5 bg-rose-50 border border-rose-100 dark:bg-slate-900/70 dark:border-slate-700 shadow-sm hover:shadow-md transition-all order-4 lg:col-span-1 lg:order-5">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                    <svg class="h-3.5 w-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>Failed Deliveries</span>
                </div>
                <div class="text-4xl md:text-5xl font-semibold text-rose-700 dark:text-rose-400 leading-tight">
                    {{ number_format($stats['totals']->failed ?? 0) }}
                </div>
                @if(($stats['totals']->total ?? 0) > 0)
                    <div class="text-sm font-medium text-slate-600 dark:text-slate-400 leading-tight">
                        {{ number_format((($stats['totals']->failed ?? 0) / $stats['totals']->total) * 100, 1) }}%
                    </div>
                @endif
            </div>
        </a>

        <!-- FAILED CONTACTS (patron contact problems – separate from delivery status buckets) -->
        <a href="{{ route('notices.issues', ['type' => 'failed_contacts', 'date_from' => $date_from, 'date_to' => $date_to]) }}"
           class="block rounded-2xl px-6 py-5 bg-purple-50 border border-purple-100 dark:bg-slate-900/70 dark:border-slate-700 shadow-sm hover:shadow-md transition-all order-5 lg:col-span-1 lg:order-6">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                    <svg class="h-3.5 w-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Failed Contacts</span>
                </div>
                <div class="text-4xl md:text-5xl font-semibold text-purple-700 dark:text-purple-400 leading-tight">
                    {{ number_format($failedContactsContacts) }}
                </div>
                <div class="text-sm font-medium text-slate-600 dark:text-slate-400 leading-tight">
                    Invalid Contact Information
                </div>
            </div>
        </a>
    </div>

    <!-- Exceptions & Alerts -->
    <div class="mb-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Patrons Not Receiving Notifications (Failed Contacts CTA) -->
            @php
                $smsLabel   = $delivery_labels['sms'] ?? 'SMS';
                $voiceLabel = $delivery_labels['voice'] ?? 'Voice';

                $combinedCounts = [
                    'patrons'  => (int) (($failed_contact_counts['sms_voice']['patrons'] ?? 0)
                                  + ($failed_contact_counts['email']['patrons'] ?? 0)),
                    'contacts' => (int) (($failed_contact_counts['sms_voice']['contacts'] ?? 0)
                                  + ($failed_contact_counts['email']['contacts'] ?? 0)),
                ];

                $combinedContacts = ($failed_contacts_smsvoice ?? collect())
                    ->concat($failed_contacts_email ?? collect())
                    ->sortByDesc('failure_count')
                    ->values();
            @endphp
            <div class="lg:col-span-2">
                <x-notices::failed-contact-card
                    variant="sms_voice"
                    title="Fix Patron Notification Issues"
                    :counts="$combinedCounts"
                    :contacts="$combinedContacts"
                    contactLabel="contact(s)"
                />
            </div>

            <!-- Polaris → LX Starter Communication Failures -->
            @if($orphaned_data['total_orphaned'] > 0)
            <div
                x-data="{
                    goToIssues() {
                        const url = @js(route('notices.issues.orphaned', ['orphaned_days' => $orphaned_days ?? 30]));
                        window.location.href = url;
                    }
                }"
                @click="goToIssues()"
                class="cursor-pointer block bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 rounded-lg border-2 border-orange-300 dark:border-orange-700 shadow-md hover:shadow-xl transition-all group"
            >
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 p-3 bg-orange-100 dark:bg-orange-900/40 rounded-lg group-hover:bg-orange-200 dark:group-hover:bg-orange-900/60 transition-colors">
                                <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    Polaris → LX Starter Failures
                                    <svg class="w-4 h-4 text-orange-500 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </h3>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">Never sent by LX Starter</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($orphaned_data['total_orphaned']) }}</p>
                            <form method="GET" action="{{ route('notices.dashboard') }}" class="mt-1" @click.stop>
                                @if($date_from)
                                    <input type="hidden" name="date_from" value="{{ $date_from }}">
                                @endif
                                @if($date_to)
                                    <input type="hidden" name="date_to" value="{{ $date_to }}">
                                @endif
                                @if($quick_range)
                                    <input type="hidden" name="quick_range" value="{{ $quick_range }}">
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
                        <p class="text-xs text-slate-700 dark:text-slate-300 line-clamp-2">
                            <strong>Pattern:</strong> {{ Str::limit($orphaned_data['pattern_analysis']['description'], 120) }}
                        </p>
                    </div>
                    @endif

                    <!-- Mini Chart -->
                    <div class="bg-white/60 dark:bg-slate-800/60 rounded-lg p-3">
                        <canvas id="dashboardOrphanedChart" height="60"></canvas>
                    </div>

                    <a href="{{ route('notices.issues.orphaned', ['orphaned_days' => $orphaned_days ?? 30]) }}"
                       class="mt-3 flex items-center justify-center gap-2 text-sm text-orange-700 dark:text-orange-300 font-medium hover:underline"
                       @click.stop>
                        <span>View full analysis</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                // Prepare data for Chart.js - show daily summary with all days (including zeros)
                const dashboardOrphanedDaily = @json($orphaned_data['daily_summary']);
                const dashboardLabels = dashboardOrphanedDaily.map(c => {
                    const date = new Date(c.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                });
                const dashboardData = dashboardOrphanedDaily.map(c => c.count);

                // Create chart
                const dashboardCtx = document.getElementById('dashboardOrphanedChart').getContext('2d');
                new Chart(dashboardCtx, {
                    type: 'line',
                    data: {
                        labels: dashboardLabels,
                        datasets: [{
                            label: 'Orphaned',
                            data: dashboardData,
                            borderColor: 'rgb(234, 88, 12)',
                            backgroundColor: 'rgba(234, 88, 12, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
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
                                    precision: 0,
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            </script>
            @endif

            <!-- Latest Imports (moved under Fix Patron Notification Issues) -->
            <div class="rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
                <div class="px-5 py-3 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/80">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300 border border-indigo-200/70 dark:border-indigo-500/40 shadow-sm">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v4H4zM4 12h16v8H4z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 tracking-wide">Latest Imports</h3>
                    </div>
                    @if(isset($import_status['is_current']))
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $import_status['is_current'] ? 'Up to date' : 'Check import jobs' }}
                        </p>
                    @endif
                </div>
                <div class="px-5 py-2 flex-1">
                    @if(isset($import_status))
                        <ul class="divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                            @foreach($import_status['sources'] as $sourceKey => $data)
                                @continue($sourceKey === 'email_reports')
                                @php
                                    $dateFor = $data['date_for'] ?? null;

                                    // Prefer direct log views for FTP sources; email detail for reports; fall back to export-files viewer otherwise
                                    if ($sourceKey === 'ftp_holds' && $dateFor) {
                                        $exportUrl = route('notices.holds.logs', ['date' => $dateFor]);
                                    } elseif ($sourceKey === 'ftp_renew' && $dateFor) {
                                        $exportUrl = route('notices.renew.logs', ['date' => $dateFor]);
                                    } elseif ($sourceKey === 'ftp_overdue' && $dateFor) {
                                        $exportUrl = route('notices.overdue.logs', ['date' => $dateFor]);
                                    } elseif ($sourceKey === 'email_reports' && !empty($data['email_id'])) {
                                        $exportUrl = route('notices.data.email-detail', ['id' => $data['email_id']]);
                                    } else {
                                        $exportUrl = route('notices.data.export-files', [
                                            'source' => $sourceKey,
                                            'search' => $data['identifier'] ?? null,
                                        ]);
                                    }
                                    $status      = $data['status'] ?? 'error';
                                    $statusColor = [
                                        'success' => 'bg-emerald-500',
                                        'warning' => 'bg-amber-400',
                                        'error'   => 'bg-rose-500',
                                    ][$status] ?? 'bg-emerald-500';
                                @endphp
                                <li>
                                    <a href="{{ $exportUrl }}" target="_blank" rel="noopener" class="flex items-center justify-between gap-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 flex-shrink-0">
                                                <span class="h-2.5 w-2.5 rounded-full {{ $statusColor }}"></span>
                                            </span>
                                            <span class="text-xs text-slate-900 dark:text-slate-100 break-all">
                                                {{ $data['identifier'] ?? $data['label'] }}
                                            </span>
                                        </div>
                                        @if(!empty($data['last_import']))
                                            <span class="ml-4 text-[11px] text-slate-500 whitespace-nowrap">
                                                {{ $data['last_import'] }}
                                            </span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach

                            {{-- Individual Shoutbomb / summary report emails (latest for the day) --}}
                            @if(!empty($import_status['email_reports_list']))
                                @foreach($import_status['email_reports_list'] as $email)
                                    @php
                                        $exportUrl = route('notices.data.email-detail', ['id' => $email['id']]);
                                    @endphp
                                    <li>
                                        <a href="{{ $exportUrl }}" target="_blank" rel="noopener" class="flex items-center justify-between gap-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-white flex-shrink-0">
                                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                                </span>
                                                <span class="text-xs text-slate-900 dark:text-slate-100 break-all">
                                                    {{ $email['subject'] ?? 'Email Report' }}
                                                </span>
                                            </div>
                                            @if(!empty($email['received_at']))
                                                <span class="ml-4 text-[11px] text-slate-500 whitespace-nowrap">
                                                    {{ \Carbon\Carbon::parse($email['received_at'])->format('M j, g:i A') }}
                                                </span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">Import status information not available</p>
                    @endif
                </div>
                <div class="px-5 py-2 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-xs">
                    <a href="{{ route('notices.data.export-files') }}" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 font-medium">
                        View More
                        <span class="text-xs">›</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Delivery Confirmations by Source section removed per design update --}}

<!-- Failed Contact Edit/Resolve Modal -->
    <x-notices::failed-contact-modal />
    </div>
</div>

@push('scripts')
<script>
function globalSearch() {
    return {
        query: '',
        open: false,
        loading: false,
        results: [],
        async search() {
            const q = this.query.trim();
            if (!q) return;
            this.loading = true;
            this.open = true;
            try {
                const res = await fetch('{{ route('notices.global-search') }}?q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                this.results = data.results || [];
            } catch (e) {
                console.error(e);
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
        close() {
            this.open = false;
        },
        badgeClass(type) {
            switch (type) {
                case 'notification':
                    return 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
                case 'issue':
                    return 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
                case 'failed_contact':
                    return 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-200';
                default:
                    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
            }
        }
    };
}
</script>
@endpush

@push('head')
<style>
    /* Custom scrollbar for tables */
    .overflow-x-auto::-webkit-scrollbar {
        height: 8px;
    }
    .overflow-x-auto::-webkit-scrollbar-track {
        background: rgb(30 41 59);
        border-radius: 4px;
    }
    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: rgb(71 85 105);
        border-radius: 4px;
    }
    .overflow-x-auto::-webkit-scrollbar-thumb:hover {
        background: rgb(100 116 139);
    }
</style>
@endpush
@endsection
