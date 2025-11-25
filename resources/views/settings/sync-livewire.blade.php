@extends('notices::layouts.app')

@section('title', 'Sync & Import')

@section('content')
<div class="px-4 sm:px-0">
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Sync & Import</h2>
            <p class="mt-1 text-sm text-gray-600">
                Import PhoneNotices, Shoutbomb submissions, and patron delivery preferences with live progress.
            </p>
        </div>
        <a href="/notices/settings"
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Settings
        </a>
    </div>

    {{-- High-level last run summaries reused from legacy sync page --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @if($lastSyncAll)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Complete Sync</div>
                <div class="mt-1 text-sm text-gray-700">
                    Last run: {{ $lastSyncAll->started_at->diffForHumans() }}
                </div>
                <div class="mt-1 text-xs text-gray-500 flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[0.65rem] font-medium
                        {{ $lastSyncAll->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $lastSyncAll->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                        {{ $lastSyncAll->status === 'completed_with_errors' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                        {{ ucfirst(str_replace('_', ' ', $lastSyncAll->status)) }}
                    </span>
                    @if($lastSyncAll->records_processed)
                        <span>{{ $lastSyncAll->records_processed }} records</span>
                    @endif
                </div>
            </div>
        @endif

        @if($lastFTPFiles)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">FTP Files Import</div>
                <div class="mt-1 text-sm text-gray-700">
                    Last run: {{ $lastFTPFiles->started_at->diffForHumans() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $lastFTPFiles->records_processed ?? 0 }} records
                </div>
            </div>
        @endif

        @if($lastPolaris)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Polaris Import</div>
                <div class="mt-1 text-sm text-gray-700">
                    Last run: {{ $lastPolaris->started_at->diffForHumans() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $lastPolaris->records_processed ?? 0 }} records
                </div>
            </div>
        @endif

        @if($lastShoutbombSubmissions)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Shoutbomb Submissions</div>
                <div class="mt-1 text-sm text-gray-700">
                    Last run: {{ $lastShoutbombSubmissions->started_at->diffForHumans() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $lastShoutbombSubmissions->records_processed ?? 0 }} records
                </div>
            </div>
        @endif

        @if($lastShoutbombReports)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Shoutbomb Reports</div>
                <div class="mt-1 text-sm text-gray-700">
                    Last run: {{ $lastShoutbombReports->started_at->diffForHumans() }}
                </div>
            </div>
        @endif

        @if($lastAggregate)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Aggregation</div>
                <div class="mt-1 text-sm text-gray-700">
                    Last run: {{ $lastAggregate->started_at->diffForHumans() }}
                </div>
            </div>
        @endif
    </div>

    {{-- Recent Syncs panel with modal details --}}
    <div x-data="syncHistoryManager()" class="mb-8">
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Recent Syncs</h3>
                    <p class="mt-1 text-xs text-gray-500">History of recent sync and import operations.</p>
                </div>
            </div>
            <div class="px-4 py-4 sm:px-6">
                @if($recentSyncs->isEmpty())
                    <p class="text-sm text-gray-500">No sync history recorded yet.</p>
                @else
                    @php
                        $operationLabels = [
                            'sync_all' => 'Complete Sync',
                            'import_polaris' => 'Import Polaris',
                            'import_ftp_files' => 'Import FTP Files',
                            'import_shoutbomb_submissions' => 'Shoutbomb Submissions',
                            'import_shoutbomb_reports' => 'Shoutbomb Reports',
                            'aggregate' => 'Aggregation',
                            'sync_shoutbomb_to_logs' => 'Sync Shoutbomb to Logs',
                        ];
                    @endphp
                    <div class="overflow-x-auto -mx-4 sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full align-middle px-4 sm:px-6 lg:px-8">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operation</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                                        <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentSyncs as $log)
                                        <tr>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                {{ $operationLabels[$log->operation_type] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $log->operation_type)) }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-xs">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[0.65rem] font-medium
                                                    {{ $log->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $log->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                                    {{ $log->status === 'completed_with_errors' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                                {{ $log->started_at?->format('M d, Y g:i A') ?? '—' }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                                {{ $log->records_processed ?? 0 }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm">
                                                <button type="button"
                                                        @click="openLog({{ $log->id }})"
                                                        class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Log details modal --}}
        <div x-show="showLogModal" x-cloak class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showLogModal = false"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                <span x-text="currentLog?.operation_type_label || 'Sync Details'"></span>
                            </h3>
                            <div class="mt-2">
                                <template x-if="loadingLog">
                                    <p class="text-sm text-gray-500">Loading log details...</p>
                                </template>

                                <template x-if="!loadingLog && currentLog">
                                    <div class="space-y-3">
                                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700">
                                            <span class="font-semibold">Status:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  :class="{
                                                      'bg-green-100 text-green-800': currentLog.status === 'completed',
                                                      'bg-red-100 text-red-800': currentLog.status === 'failed',
                                                      'bg-yellow-100 text-yellow-800': currentLog.status === 'completed_with_errors'
                                                  }">
                                                <span x-text="currentLog.status_label"></span>
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-gray-700">
                                            <div>
                                                <span class="font-semibold">Started:</span>
                                                <span class="ml-1" x-text="currentLog.started_at"></span>
                                            </div>
                                            <div>
                                                <span class="font-semibold">Completed:</span>
                                                <span class="ml-1" x-text="currentLog.completed_at || '—'"></span>
                                            </div>
                                            <div>
                                                <span class="font-semibold">Duration:</span>
                                                <span class="ml-1" x-text="currentLog.duration_seconds ? currentLog.duration_seconds + 's' : '—'"></span>
                                            </div>
                                            <div>
                                                <span class="font-semibold">Records processed:</span>
                                                <span class="ml-1" x-text="currentLog.records_processed ?? 0"></span>
                                            </div>
                                        </div>

                                        <template x-if="currentLog.error_message">
                                            <div class="mt-2">
                                                <h4 class="text-sm font-semibold text-red-700">Error</h4>
                                                <p class="mt-1 text-sm text-red-600 whitespace-pre-line" x-text="currentLog.error_message"></p>
                                            </div>
                                        </template>

                                        <template x-if="currentLog.results">
                                            <div class="mt-2">
                                                <h4 class="text-sm font-semibold text-gray-800">Raw Results</h4>
                                                <pre class="mt-1 bg-gray-50 rounded-md p-3 text-xs text-gray-700 overflow-x-auto"><code x-text="JSON.stringify(currentLog.results, null, 2)"></code></pre>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" @click="showLogModal = false" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <livewire:sync-and-import />
</div>
@endsection

@push('scripts')
<script>
    function syncHistoryManager() {
        // Use relative URL to avoid SSL/port issues with proxies
        const logUrlTemplate = '/notices/sync/log/__ID__';

        const operationLabels = {
            'sync_all': 'Complete Sync',
            'import_polaris': 'Import Polaris',
            'import_ftp_files': 'Import FTP Files',
            'import_shoutbomb_submissions': 'Shoutbomb Submissions',
            'import_shoutbomb_reports': 'Shoutbomb Reports',
            'aggregate': 'Aggregation',
            'sync_shoutbomb_to_logs': 'Sync Shoutbomb to Logs',
        };

        const statusLabels = {
            'completed': 'Completed',
            'failed': 'Failed',
            'completed_with_errors': 'Completed with errors',
            'running': 'Running',
        };

        return {
            showLogModal: false,
            loadingLog: false,
            currentLog: null,

            async openLog(id) {
                this.showLogModal = true;
                this.loadingLog = true;
                this.currentLog = null;

                try {
                    const url = logUrlTemplate.replace('__ID__', id);
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(`Failed to load log (${response.status})`);
                    }

                    const data = await response.json();

                    this.currentLog = {
                        ...data,
                        operation_type_label: operationLabels[data.operation_type] || data.operation_type,
                        status_label: statusLabels[data.status] || data.status,
                    };
                } catch (e) {
                    this.currentLog = {
                        operation_type_label: 'Error loading log',
                        status: 'failed',
                        status_label: 'Failed',
                        started_at: '',
                        completed_at: '',
                        duration_seconds: null,
                        records_processed: 0,
                        error_message: e.message,
                        results: null,
                    };
                } finally {
                    this.loadingLog = false;
                }
            },
        };
    }
</script>
@endpush
