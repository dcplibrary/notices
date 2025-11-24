@extends('notices::layouts.app')

@section('title', 'Sync & Import')

@section('content')
<div class="px-4 sm:px-0" x-data="syncManager()">
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Sync & Import</h2>
            <p class="mt-1 text-sm text-gray-600">
                Import data from Polaris and Shoutbomb
            </p>
        </div>
        <a href="{{ route('notices.settings.index') }}" 
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Settings
        </a>
    </div>

    <!-- Status Messages -->
    <div x-show="message" x-cloak class="mb-6">
        <div :class="{
            'bg-green-50 border-green-200 text-green-800': messageType === 'success',
            'bg-red-50 border-red-200 text-red-800': messageType === 'error',
            'bg-blue-50 border-blue-200 text-blue-800': messageType === 'info'
        }" class="border-l-4 p-4 rounded">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg x-show="messageType === 'success'" class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <svg x-show="messageType === 'error'" class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium" x-text="message"></p>
                </div>
                <div class="ml-auto pl-3">
                    <button @click="message = ''" class="inline-flex text-gray-400 hover:text-gray-500">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Primary Action: Sync All -->
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="text-white">
                <h3 class="text-lg font-semibold">Complete Sync</h3>
                <p class="mt-1 text-sm text-indigo-100">
                    Import from Polaris & Shoutbomb, then run aggregation
                </p>
                @if($lastSyncAll)
                <p class="mt-2 text-xs text-indigo-200">
                    Last run: {{ $lastSyncAll->started_at->diffForHumans() }}
                    <span class="ml-2 px-2 py-0.5 rounded text-xs font-medium
                        {{ $lastSyncAll->status === 'completed' ? 'bg-green-500' : '' }}
                        {{ $lastSyncAll->status === 'failed' ? 'bg-red-500' : '' }}
                        {{ $lastSyncAll->status === 'completed_with_errors' ? 'bg-yellow-500' : '' }}">
                        {{ ucfirst(str_replace('_', ' ', $lastSyncAll->status)) }}
                    </span>
                </p>
                @endif
            </div>
            <button @click="syncAll()" 
                    :disabled="loading"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg x-show="!loading" class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <svg x-show="loading" class="animate-spin mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="loading ? 'Syncing...' : 'Sync All Now'"></span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Individual Import Actions -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Manual Imports</h3>
                <p class="mt-1 text-sm text-gray-500">Run individual import operations</p>
            </div>
            <div class="px-6 py-4 space-y-4">
                <!-- Import Polaris -->
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Import from Polaris</h4>
                        <p class="text-xs text-gray-500">Import notification logs from Polaris database</p>
                        @if($lastPolaris)
                        <p class="text-xs text-gray-400 mt-1">
                            Last: {{ $lastPolaris->started_at->diffForHumans() }}
                            ({{ $lastPolaris->records_processed ?? 0 }} records)
                        </p>
                        @endif
                    </div>
                    <button @click="importPolaris()" 
                            :disabled="loading"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                        <svg x-show="!loading" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Import
                    </button>
                </div>

                <!-- Import FTP Files (PhoneNotices + Shoutbomb Submissions + Patrons) -->
                <div class="pt-4 border-t">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Import FTP Files</h4>
                            <p class="text-xs text-gray-500">PhoneNotices, Shoutbomb submissions, and patron delivery preferences</p>
                            @if($lastFTPFiles ?? false)
                            <p class="text-xs text-gray-400 mt-1">
                                Last: {{ $lastFTPFiles->started_at->diffForHumans() }}
                                ({{ $lastFTPFiles->records_processed ?? 0 }} records)
                            </p>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Date Range -->
                    <div class="mt-3 flex items-center gap-3">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <input type="date"
                                   x-model="ftpFromDate"
                                   class="text-xs border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2">
                            <span class="text-gray-400">to</span>
                            <input type="date"
                                   x-model="ftpToDate"
                                   class="text-xs border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2">
                        </div>
                    </div>

                    <!-- Import Patron Preferences Checkbox -->
                    <div class="mt-3 p-3 bg-gray-50 rounded-md">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" x-model="importPatrons" class="form-checkbox h-4 w-4 text-indigo-600 rounded">
                            <span class="ml-2 text-sm font-medium text-gray-700">Import Patron Delivery Preferences</span>
                        </label>
                        <p class="ml-6 mt-1 text-xs text-gray-500">
                            Import voice_patrons and text_patrons files to track patron delivery method changes.
                            Previously processed files will be automatically skipped.
                        </p>
                    </div>

                    <!-- Import Button -->
                    <div class="mt-3">
                        <button @click="importFTPFiles()"
                                :disabled="loading"
                                class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                            <svg x-show="!loading" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span x-text="loading ? 'Importing...' : 'Import FTP Files'"></span>
                        </button>
                    </div>
                </div>

                <!-- Other imports truncated for brevity - keep your existing structure -->
            </div>
        </div>

        <!-- Recent Sync History - keep your existing panel -->
    </div>
</div>

@push('scripts')
<script>
function syncManager() {
    return {
        loading: false,
        message: '',
        messageType: 'info',
        ftpFromDate: '{{ now()->format("Y-m-d") }}',
        ftpToDate: '{{ now()->format("Y-m-d") }}',
        importPatrons: false,
        connectionResults: null,
        showLogModal: false,
        loadingLog: false,
        currentLog: null,

        async syncAll() {
            await this.runOperation('sync-all', 'Complete Sync');
        },

        async importPolaris() {
            await this.runOperation('import-polaris', 'Import Polaris');
        },

        async importFTPFiles() {
            this.loading = true;
            this.message = 'Import FTP Files in progress...';
            this.messageType = 'info';

            try {
                const response = await fetch('/notices/sync/import-ftp-files', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        from: this.ftpFromDate,
                        to: this.ftpToDate,
                        import_patrons: this.importPatrons
                    })
                });

                if (!response.ok) {
                    const contentType = response.headers.get('content-type') || '';
                    let errorDetail = '';
                    if (contentType.includes('application/json')) {
                        try {
                            const errorData = await response.json();
                            errorDetail = errorData.message || errorData.error || '';
                        } catch (e) {}
                    }
                    if (!errorDetail) {
                        const text = await response.text();
                        errorDetail = text ? ` Details: ${text.substring(0, 200)}` : '';
                    }
                    throw new Error(`Server returned ${response.status} ${response.statusText}.${errorDetail}`);
                }

                const data = await response.json();

                if (data.status === 'success') {
                    let filesInfo = '';
                    if (data.files_processed && data.files_processed.length > 0) {
                        filesInfo = ` Files: ${data.files_processed.join(', ')}`;
                    }
                    this.message = `Import FTP Files completed successfully! ${data.records ? 'Processed ' + data.records + ' records.' : ''}${filesInfo}`;
                    this.messageType = 'success';
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    let errorMsg = data.message || 'Unknown error';
                    if (data.error) {
                        errorMsg += ` (${data.error})`;
                    }
                    this.message = `Import FTP Files failed: ${errorMsg}`;
                    this.messageType = 'error';
                }
            } catch (error) {
                this.message = 'Import FTP Files failed: ' + error.message;
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        },

        async runOperation(operation, label) {
            this.loading = true;
            this.message = `${label} in progress...`;
            this.messageType = 'info';

            try {
                const response = await fetch(`/notices/sync/${operation}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    const contentType = response.headers.get('content-type') || '';
                    let errorDetail = '';
                    if (contentType.includes('application/json')) {
                        try {
                            const errorData = await response.json();
                            errorDetail = errorData.message || errorData.error || '';
                        } catch (e) {}
                    }
                    if (!errorDetail) {
                        const text = await response.text();
                        errorDetail = text ? ` Details: ${text.substring(0, 200)}` : '';
                    }
                    throw new Error(`Server returned ${response.status} ${response.statusText}.${errorDetail}`);
                }

                const data = await response.json();

                if (data.status === 'success' || data.success) {
                    this.message = `${label} completed successfully! ${data.records ? 'Processed ' + data.records + ' records.' : ''}`;
                    this.messageType = 'success';
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    let errorMsg = data.message || 'Unknown error';
                    if (data.error) {
                        errorMsg += ` (${data.error})`;
                    }
                    this.message = `${label} failed: ${errorMsg}`;
                    this.messageType = 'error';
                }
            } catch (error) {
                this.message = `${label} failed: ` + error.message;
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endpush
@endsection
