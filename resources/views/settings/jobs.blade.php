@extends('notices::layouts.app')

@section('title', 'Jobs')

@section('body')
<div class="notices-staff-page pb-10"
     x-data="jobRunsPage({
        initialRuns: @json($runs),
        initialActiveCount: {{ (int) ($active_count ?? 0) }},
        initialCounts: @json($status_counts ?? []),
        routes: {
            list: '{{ route('notices.settings.jobs.job-runs') }}',
            show: '{{ route('notices.settings.jobs.job-runs.show', ['id' => 0]) }}',
            events: '{{ route('notices.settings.jobs.job-runs.events', ['id' => 0]) }}',
            cancel: '{{ route('notices.settings.jobs.job-runs.cancel', ['id' => 0]) }}',
            actionImport: '{{ route('notices.settings.jobs.actions.import') }}',
            actionImportConfirmations: '{{ route('notices.settings.jobs.actions.import-confirmations') }}',
            actionProcessOrphaned: '{{ route('notices.settings.jobs.actions.process-orphaned') }}',
            actionIntegrityCheck: '{{ route('notices.settings.jobs.actions.integrity-check') }}',
            actionCleanupImportLogs: '{{ route('notices.settings.jobs.actions.cleanup-import-logs') }}',
        }
     })"
     x-init="init()"
>
    <div class="max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div class="border-l-4 border-dcpl-blue pl-4">
                <h1 class="font-outfit text-3xl font-semibold tracking-tight text-dcpl-text mb-1">Jobs</h1>
                <p class="text-sm text-gray-600">
                    Queue imports and maintenance actions, monitor progress, and cancel when needed.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200">
                    <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium"><span x-text="activeCount"></span> active</span>
                </div>

                <button type="button"
                        @click="refreshRuns()"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-900/40 border border-blue-100 dark:border-blue-900/60 text-blue-700 dark:text-blue-200 hover:bg-blue-100 dark:hover:bg-blue-900/60 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 00-14.5-3.5M4 16a8 8 0 0014.5 3.5" />
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Stats Row (dashboard-style mini cards) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
            <!-- TOTAL -->
            <button type="button"
                    @click="setFilter('all')"
                    class="text-left block rounded-2xl px-6 py-5 bg-blue-50 border border-blue-100 shadow-sm hover:shadow-md transition-all"
                    :class="filterStatus === 'all' ? 'ring-2 ring-blue-500' : ''"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                        <svg class="h-3.5 w-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v4H4zM4 12h16v8H4z" />
                        </svg>
                        <span>Total (loaded)</span>
                    </div>
                    <div class="text-4xl md:text-5xl font-semibold text-blue-900 leading-tight" x-text="counts.total"></div>
                    <div class="text-sm font-medium text-slate-600 leading-tight">All statuses</div>
                </div>
            </button>

            <!-- QUEUED -->
            <button type="button"
                    @click="setFilter('queued')"
                    class="text-left block rounded-2xl px-6 py-5 bg-slate-50 border border-slate-200 shadow-sm hover:shadow-md transition-all"
                    :class="filterStatus === 'queued' ? 'ring-2 ring-slate-500' : ''"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                        <svg class="h-3.5 w-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2" />
                        </svg>
                        <span>Queued</span>
                    </div>
                    <div class="text-4xl md:text-5xl font-semibold text-slate-800 leading-tight" x-text="counts.queued"></div>
                    <div class="text-sm font-medium text-slate-600 leading-tight">Waiting for worker</div>
                </div>
            </button>

            <!-- RUNNING -->
            <button type="button"
                    @click="setFilter('running')"
                    class="text-left block rounded-2xl px-6 py-5 bg-indigo-50 border border-indigo-100 shadow-sm hover:shadow-md transition-all"
                    :class="filterStatus === 'running' ? 'ring-2 ring-indigo-500' : ''"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                        <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Running</span>
                    </div>
                    <div class="text-4xl md:text-5xl font-semibold text-indigo-800 leading-tight" x-text="counts.running"></div>
                    <div class="text-sm font-medium text-slate-600 leading-tight">In progress</div>
                </div>
            </button>

            <!-- FAILED -->
            <button type="button"
                    @click="setFilter('failed')"
                    class="text-left block rounded-2xl px-6 py-5 bg-rose-50 border border-rose-100 shadow-sm hover:shadow-md transition-all"
                    :class="filterStatus === 'failed' ? 'ring-2 ring-rose-500' : ''"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                        <svg class="h-3.5 w-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span>Failed</span>
                    </div>
                    <div class="text-4xl md:text-5xl font-semibold text-rose-700 leading-tight" x-text="counts.failed"></div>
                    <div class="text-sm font-medium text-slate-600 leading-tight">Needs attention</div>
                </div>
            </button>

            <!-- SUCCESS -->
            <button type="button"
                    @click="setFilter('success')"
                    class="text-left block rounded-2xl px-6 py-5 bg-emerald-50 border border-emerald-100 shadow-sm hover:shadow-md transition-all"
                    :class="filterStatus === 'success' ? 'ring-2 ring-emerald-500' : ''"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Success</span>
                    </div>
                    <div class="text-4xl md:text-5xl font-semibold text-emerald-700 leading-tight" x-text="counts.success"></div>
                    <div class="text-sm font-medium text-slate-600 leading-tight">Completed</div>
                </div>
            </button>

            <!-- CANCELLED -->
            <button type="button"
                    @click="setFilter('cancelled')"
                    class="text-left block rounded-2xl px-6 py-5 bg-amber-50 border border-amber-100 shadow-sm hover:shadow-md transition-all"
                    :class="filterStatus === 'cancelled' ? 'ring-2 ring-amber-500' : ''"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                        <svg class="h-3.5 w-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 4h.01" />
                        </svg>
                        <span>Cancelled</span>
                    </div>
                    <div class="text-4xl md:text-5xl font-semibold text-amber-700 leading-tight" x-text="counts.cancelled"></div>
                    <div class="text-sm font-medium text-slate-600 leading-tight">Stopped</div>
                </div>
            </button>
        </div>

        <!-- Action Bar (dashboard card style) -->
        <div class="mb-6">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between lg:gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 w-full">
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wide text-slate-500 dark:text-slate-400 uppercase mb-1">Import date</label>
                            <input type="date" x-model="importDate"
                                   class="w-full h-11 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wide text-slate-500 dark:text-slate-400 uppercase mb-1">Orphaned days</label>
                            <input type="number" min="1" max="365" x-model.number="orphanedDays"
                                   class="w-full h-11 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wide text-slate-500 dark:text-slate-400 uppercase mb-1">Integrity days</label>
                            <input type="number" min="1" max="365" x-model.number="integrityDays"
                                   class="w-full h-11 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue">
                        </div>
                        <div class="flex items-center gap-2 pt-6">
                            <input id="integrityFix" type="checkbox" x-model="integrityFix"
                                   class="h-4 w-4 rounded border-slate-300 dark:border-slate-600">
                            <label for="integrityFix" class="text-sm text-slate-700 dark:text-slate-300">Auto-fix</label>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                @click="queueImport()"
                                class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-dcpl-blue hover:brightness-95 shadow-sm">
                            Queue Import
                        </button>
                        <button type="button"
                                @click="queueProcessOrphaned()"
                                class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">
                            Process Orphaned
                        </button>
                        <button type="button"
                                @click="queueIntegrityCheck()"
                                class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm">
                            Integrity Check
                        </button>
                        <button type="button"
                                @click="queueCleanup()"
                                class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-slate-700 hover:bg-slate-600 shadow-sm">
                            Cleanup Logs
                        </button>
                    </div>
                </div>

                <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    These actions are queued. Make sure a worker is running: <code class="font-mono">php artisan queue:work</code>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Runs List -->
            <div class="lg:col-span-1">
                <div class="h-full rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent Job Runs</h3>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400" x-show="loadingRuns">Loading…</div>
                    </div>

                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        <template x-for="run in filteredRuns()" :key="run.id">
                            <button type="button"
                                    @click="selectRun(run.id)"
                                    class="w-full text-left px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors"
                                    :class="selectedRunId === run.id ? 'bg-slate-50 dark:bg-slate-800/40' : ''"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-slate-900 dark:text-white truncate" x-text="run.job_name || '(unknown)'"></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            <span x-text="run.started_at ? formatIso(run.started_at) : 'Not started'"></span>
                                        </div>
                                    </div>
                                    <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full"
                                          :class="statusBadgeClass(run.status)"
                                          x-text="run.status || 'unknown'"></span>
                                </div>
                                <template x-if="run.error_message">
                                    <div class="mt-2 text-xs text-rose-600 dark:text-rose-400 line-clamp-2" x-text="run.error_message"></div>
                                </template>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Run Details -->
            <div class="lg:col-span-2">
                <div class="h-full rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Run Details</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <template x-if="selectedRun && (selectedRun.status === 'queued' || selectedRun.status === 'running')">
                                <button type="button"
                                        @click="cancelSelected()"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-sm">
                                    Cancel
                                </button>
                            </template>
                            <button type="button"
                                    @click="refreshSelected()"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                Refresh
                            </button>
                        </div>
                    </div>

                    <div class="p-5" x-show="!selectedRunId">
                        <div class="text-sm text-slate-600 dark:text-slate-300">Select a job run from the left to view details and logs.</div>
                    </div>

                    <div class="p-5 space-y-4" x-show="selectedRunId">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Status</div>
                        <div class="mt-1 font-semibold text-slate-900 dark:text-white" x-text="selectedRun?.status || '—'"></div>
                    </div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Started</div>
                        <div class="mt-1 font-semibold text-slate-900 dark:text-white" x-text="selectedRun?.started_at ? formatIso(selectedRun.started_at) : '—'"></div>
                    </div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Finished</div>
                        <div class="mt-1 font-semibold text-slate-900 dark:text-white" x-text="selectedRun?.finished_at ? formatIso(selectedRun.finished_at) : '—'"></div>
                    </div>
                </div>

                        <template x-if="selectedRun?.error_message">
                            <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-900/50 text-sm text-rose-900 dark:text-rose-200"
                                 x-text="selectedRun.error_message"></div>
                        </template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Activity Logs
                        </div>
                                <div class="max-h-[360px] overflow-auto p-3 text-xs font-mono bg-white dark:bg-slate-900">
                            <template x-if="activityLogs.length === 0">
                                <div class="text-slate-500 dark:text-slate-400">No activity logs yet.</div>
                            </template>
                            <template x-for="log in activityLogs" :key="'a'+log.id">
                                        <div class="py-1.5 border-b border-slate-100 dark:border-slate-800">
                                    <div class="text-slate-600 dark:text-slate-400" x-text="log.created_at ? formatIso(log.created_at) : ''"></div>
                                    <div class="text-slate-900 dark:text-slate-100" x-text="log.message || ''"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            User Activity Logs
                        </div>
                                <div class="max-h-[360px] overflow-auto p-3 text-xs font-mono bg-white dark:bg-slate-900">
                            <template x-if="userActivityLogs.length === 0">
                                <div class="text-slate-500 dark:text-slate-400">No user activity logs yet.</div>
                            </template>
                            <template x-for="log in userActivityLogs" :key="'u'+log.id">
                                        <div class="py-1.5 border-b border-slate-100 dark:border-slate-800">
                                    <div class="text-slate-600 dark:text-slate-400" x-text="log.created_at ? formatIso(log.created_at) : ''"></div>
                                    <div class="text-slate-900 dark:text-slate-100">
                                        <span class="text-slate-500 dark:text-slate-400" x-text="log.action ? '['+log.action+'] ' : ''"></span>
                                        <span x-text="log.message || ''"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                        <div class="text-xs text-slate-500 dark:text-slate-400" x-show="polling">
                            Polling for updates…
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function jobRunsPage({ initialRuns, initialActiveCount, initialCounts, routes }) {
            return {
                runs: initialRuns || [],
                activeCount: initialActiveCount || 0,
                routes,
                filterStatus: 'all',
                counts: {
                    total: 0, queued: 0, running: 0, failed: 0, success: 0, cancelled: 0
                },

                // selection
                selectedRunId: null,
                selectedRun: null,

                // logs
                activityLogs: [],
                userActivityLogs: [],
                sinceActivityLogId: 0,
                sinceUserActivityLogId: 0,
                polling: false,
                pollHandle: null,

                // action inputs
                importDate: '',
                orphanedDays: 7,
                integrityDays: 30,
                integrityFix: true,

                // loading flags
                loadingRuns: false,

                init() {
                    this.applyCounts(initialCounts || null);
                    // auto-select the newest run if there is an active one
                    const active = (this.runs || []).find(r => ['queued', 'running'].includes(r.status));
                    if (active) this.selectRun(active.id);
                },

                formatIso(iso) {
                    try {
                        return new Date(iso).toLocaleString();
                    } catch (e) {
                        return iso;
                    }
                },

                statusBadgeClass(status) {
                    switch (status) {
                        case 'queued': return 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200';
                        case 'running': return 'bg-blue-200 text-blue-900 dark:bg-blue-900/40 dark:text-blue-200';
                        case 'success': return 'bg-emerald-200 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200';
                        case 'failed': return 'bg-red-200 text-red-900 dark:bg-red-900/40 dark:text-red-200';
                        case 'cancelled': return 'bg-amber-200 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200';
                        default: return 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200';
                    }
                },

                setFilter(status) {
                    this.filterStatus = status;
                },

                filteredRuns() {
                    if (this.filterStatus === 'all') return this.runs;
                    return (this.runs || []).filter(r => r.status === this.filterStatus);
                },

                applyCounts(serverCounts) {
                    // Prefer DB-level counts from the server; fall back to loaded runs.
                    if (serverCounts && typeof serverCounts === 'object') {
                        const total = Object.values(serverCounts).reduce((sum, v) => sum + (parseInt(v, 10) || 0), 0);
                        this.counts = {
                            total,
                            queued: parseInt(serverCounts.queued || 0, 10) || 0,
                            running: parseInt(serverCounts.running || 0, 10) || 0,
                            failed: parseInt(serverCounts.failed || 0, 10) || 0,
                            success: parseInt(serverCounts.success || 0, 10) || 0,
                            cancelled: parseInt(serverCounts.cancelled || 0, 10) || 0,
                        };
                        this.activeCount = this.counts.queued + this.counts.running;
                        return;
                    }

                    const runs = this.runs || [];
                    this.counts = {
                        total: runs.length,
                        queued: runs.filter(r => r.status === 'queued').length,
                        running: runs.filter(r => r.status === 'running').length,
                        failed: runs.filter(r => r.status === 'failed').length,
                        success: runs.filter(r => r.status === 'success').length,
                        cancelled: runs.filter(r => r.status === 'cancelled').length,
                    };
                    this.activeCount = this.counts.queued + this.counts.running;
                },

                urlFor(template, id) {
                    return template.replace('/0', '/' + id);
                },

                async refreshRuns() {
                    this.loadingRuns = true;
                    try {
                        const res = await fetch(this.routes.list + '?limit=50', { headers: { 'Accept': 'application/json' }});
                        const json = await res.json();
                        this.runs = json.data || [];
                        this.applyCounts(json.meta?.counts || null);
                    } finally {
                        this.loadingRuns = false;
                    }
                },

                async selectRun(id) {
                    this.selectedRunId = id;
                    await this.loadSelected();
                    this.resetPollingState();
                    await this.pollOnce(); // get initial events
                    this.startPolling();
                },

                resetPollingState() {
                    this.activityLogs = [];
                    this.userActivityLogs = [];
                    this.sinceActivityLogId = 0;
                    this.sinceUserActivityLogId = 0;
                },

                async loadSelected() {
                    const url = this.urlFor(this.routes.show, this.selectedRunId);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
                    const json = await res.json();
                    this.selectedRun = json;
                },

                async refreshSelected() {
                    if (!this.selectedRunId) return;
                    await this.loadSelected();
                },

                startPolling() {
                    this.stopPolling();
                    this.polling = true;
                    this.pollHandle = setInterval(async () => {
                        await this.pollOnce();
                    }, 2000);
                },

                stopPolling() {
                    if (this.pollHandle) clearInterval(this.pollHandle);
                    this.pollHandle = null;
                    this.polling = false;
                },

                async pollOnce() {
                    if (!this.selectedRunId) return;

                    const eventsUrl = this.urlFor(this.routes.events, this.selectedRunId)
                        + `?since_activity_log_id=${this.sinceActivityLogId}&since_user_activity_log_id=${this.sinceUserActivityLogId}&limit=200`;

                    const res = await fetch(eventsUrl, { headers: { 'Accept': 'application/json' }});
                    const json = await res.json();

                    // update run meta/status
                    if (json.job_run) {
                        this.selectedRun = { ...(this.selectedRun || {}), ...(json.job_run || {}) };
                    }

                    // append logs
                    if (Array.isArray(json.activity_logs) && json.activity_logs.length) {
                        this.activityLogs = this.activityLogs.concat(json.activity_logs);
                    }
                    if (Array.isArray(json.user_activity_logs) && json.user_activity_logs.length) {
                        this.userActivityLogs = this.userActivityLogs.concat(json.user_activity_logs);
                    }

                    // advance cursors
                    if (json.next) {
                        this.sinceActivityLogId = json.next.since_activity_log_id || this.sinceActivityLogId;
                        this.sinceUserActivityLogId = json.next.since_user_activity_log_id || this.sinceUserActivityLogId;
                    }

                    // stop polling when done
                    if (this.selectedRun && ['success','failed','cancelled'].includes(this.selectedRun.status)) {
                        this.stopPolling();
                        await this.refreshRuns();
                    }
                },

                async cancelSelected() {
                    if (!this.selectedRunId) return;
                    if (!confirm('Request cancellation for this job?')) return;

                    const url = this.urlFor(this.routes.cancel, this.selectedRunId);
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                        body: JSON.stringify({}),
                    });
                    await res.json();
                    await this.refreshSelected();
                },

                async postAction(url, payload) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                        body: JSON.stringify(payload || {}),
                    });
                    const json = await res.json();
                    if (json.job_run_id) {
                        await this.refreshRuns();
                        await this.selectRun(json.job_run_id);
                    } else {
                        alert('Failed to queue job.');
                    }
                },

                queueImport() {
                    const payload = {
                        date: this.importDate || null,
                        orphaned_days: this.orphanedDays,
                        integrity_days: this.integrityDays,
                        integrity_fix: this.integrityFix,
                    };
                    this.postAction(this.routes.actionImport, payload);
                },

                queueProcessOrphaned() {
                    this.postAction(this.routes.actionProcessOrphaned, { days: this.orphanedDays });
                },

                queueIntegrityCheck() {
                    this.postAction(this.routes.actionIntegrityCheck, { days: this.integrityDays, fix: this.integrityFix });
                },

                queueCleanup() {
                    this.postAction(this.routes.actionCleanupImportLogs, { days: 30 });
                },
            };
        }
        </script>
    </div>
</div>
@endsection

