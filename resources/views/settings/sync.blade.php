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

                <!-- Import FTP Files (PhoneNotices + Shoutbomb Submissions) -->
                <div class="pt-4 border-t">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Import FTP Files</h4>
                            <p class="text-xs text-gray-500">Import PhoneNotices and Shoutbomb submissions (holds, overdues, renewals)</p>
                            @if($lastFTPFiles ?? false)
                            <p class="text-xs text-gray-400 mt-1">
                                Last: {{ $lastFTPFiles->started_at->diffForHumans() }}
                                ({{ $lastFTPFiles->records_processed ?? 0 }} records)
                            </p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <!-- Calendar icon -->
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <input type="date"
                                   x-model="ftpStartDate"
                                   class="text-xs border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2">
                            <span class="text-gray-400">to</span>
                            <input type="date"
                                   x-model="ftpEndDate"
                                   class="text-xs border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2">
                            <span class="text-gray-400 ml-1">Default: today</span>
                        </div>
                        <button @click="importFTPFiles()"
                                :disabled="loading"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                            <svg x-show="!loading" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Import
                        </button>
                    </div>
                </div>

                <!-- Sync Shoutbomb Reports (Email/Graph) -->
                @if($integrationEnabled)
                <div class="flex items-center justify-between pt-4 border-t">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Import Shoutbomb Email Reports</h4>
                        <p class="text-xs text-gray-500">Ingest failure reports from Outlook via notices:import-shoutbomb-email</p>
                        @if($lastShoutbombReports)
                        <p class="text-xs text-gray-400 mt-1">
                            Last: {{ $lastShoutbombReports->started_at->diffForHumans() }}
                            ({{ $lastShoutbombReports->records_processed ?? 0 }} records)
                        </p>
                        @endif
                    </div>
                    <button @click="importShoutbombReports()" 
                            :disabled="loading"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                        <svg x-show="!loading" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Import Email Reports
                    </button>
                </div>
                @else
                <div class="flex items-start justify-between pt-4 border-t opacity-60">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Import Shoutbomb Email Reports</h4>
                        <p class="text-xs text-gray-500">Enable Shoutbomb Reports Integration on the Settings page to use this tool.</p>
                    </div>
                    <button disabled
                            class="inline-flex items-center px-3 py-2 border border-gray-200 shadow-sm text-sm font-medium rounded-md text-gray-400 bg-gray-50 cursor-not-allowed">
                        Import Email Reports
                    </button>
                </div>
                @endif

                <!-- Run Aggregation -->
                <div class="flex items-center justify-between pt-4 border-t">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Run Aggregation</h4>
                        <p class="text-xs text-gray-500">Build daily summary statistics</p>
                        @if($lastAggregate)
                        <p class="text-xs text-gray-400 mt-1">
                            Last: {{ $lastAggregate->started_at->diffForHumans() }}
                        </p>
                        @endif
                    </div>
                    <button @click="aggregate()" 
                            :disabled="loading"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                        <svg x-show="!loading" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Aggregate
                    </button>
                </div>
            </div>
        </div>

        <!-- Connection Tests -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Connection Tests</h3>
                <p class="mt-1 text-sm text-gray-500">Verify database and FTP connectivity</p>
            </div>
            <div class="px-6 py-4">
                <button @click="testConnections()" 
                        :disabled="loading"
                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 mb-4">
                    <svg x-show="!loading" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Test All Connections
                </button>

                <!-- Connection Test Results -->
                <div x-show="connectionResults" x-cloak class="space-y-3">
                    <!-- Polaris Connection -->
                    <div x-show="connectionResults?.polaris" 
                         class="flex items-center justify-between p-3 rounded-md"
                         :class="{
                             'bg-green-50': connectionResults?.polaris?.status === 'success',
                             'bg-red-50': connectionResults?.polaris?.status === 'error'
                         }">
                        <div class="flex items-center">
                            <svg x-show="connectionResults?.polaris?.status === 'success'" class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="connectionResults?.polaris?.status === 'error'" class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span class="ml-2 text-sm font-medium text-gray-900">Polaris Database</span>
                        </div>
                        <span class="text-xs" 
                              :class="{
                                  'text-green-700': connectionResults?.polaris?.status === 'success',
                                  'text-red-700': connectionResults?.polaris?.status === 'error'
                              }"
                              x-text="connectionResults?.polaris?.message"></span>
                    </div>

                    <!-- Shoutbomb FTP -->
                    <div x-show="connectionResults?.shoutbomb_ftp" 
                         class="flex items-center justify-between p-3 rounded-md"
                         :class="{
                             'bg-green-50': connectionResults?.shoutbomb_ftp?.status === 'success',
                             'bg-red-50': connectionResults?.shoutbomb_ftp?.status === 'error',
                             'bg-gray-50': connectionResults?.shoutbomb_ftp?.status === 'disabled'
                         }">
                        <div class="flex items-center">
                            <svg x-show="connectionResults?.shoutbomb_ftp?.status === 'success'" class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="connectionResults?.shoutbomb_ftp?.status === 'error'" class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="connectionResults?.shoutbomb_ftp?.status === 'disabled'" class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="ml-2 text-sm font-medium text-gray-900">Shoutbomb FTP</span>
                        </div>
                        <span class="text-xs"
                              :class="{
                                  'text-green-700': connectionResults?.shoutbomb_ftp?.status === 'success',
                                  'text-red-700': connectionResults?.shoutbomb_ftp?.status === 'error',
                                  'text-gray-500': connectionResults?.shoutbomb_ftp?.status === 'disabled'
                              }"
                              x-text="connectionResults?.shoutbomb_ftp?.message"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sync History -->
    <div class="mt-6 bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Recent Sync History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($recentSyncs as $sync)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ ucfirst(str_replace('_', ' ', $sync->operation_type)) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $sync->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $sync->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $sync->status === 'completed_with_errors' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $sync->status === 'running' ? 'bg-blue-100 text-blue-800' : '' }}">
                                {{ ucfirst(str_replace('_', ' ', $sync->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $sync->started_at->format('M d, Y g:i A') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $sync->duration_seconds ? $sync->duration_seconds . 's' : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $sync->records_processed ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <button @click="viewLogDetails({{ $sync->id }})"
                                    class="text-indigo-600 hover:text-indigo-900 font-medium">
                                View Details
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            No sync history yet. Click "Sync All Now" to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Log Details Modal -->
    <div x-show="showLogModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true"
         @keydown.escape.window="showLogModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                 aria-hidden="true"
                 @click="showLogModal = false"></div>

            <!-- Modal panel -->
            <div class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl">
                <!-- Modal header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900" id="modal-title">
                            Sync Log Details
                        </h3>
                        <button @click="showLogModal = false"
                                class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal body -->
                <div class="px-6 py-4 max-h-96 overflow-y-auto">
                    <div x-show="loadingLog" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 mx-auto text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">Loading log details...</p>
                    </div>

                    <div x-show="!loadingLog && currentLog" class="space-y-4">
                        <!-- Summary Section -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Summary</h4>
                            <dl class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="font-medium text-gray-500">Operation</dt>
                                    <dd class="mt-1 text-gray-900" x-text="currentLog?.operation_type?.replace(/_/g, ' ')"></dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                              :class="{
                                                  'bg-green-100 text-green-800': currentLog?.status === 'completed',
                                                  'bg-red-100 text-red-800': currentLog?.status === 'failed',
                                                  'bg-yellow-100 text-yellow-800': currentLog?.status === 'completed_with_errors',
                                                  'bg-blue-100 text-blue-800': currentLog?.status === 'running'
                                              }"
                                              x-text="currentLog?.status?.replace(/_/g, ' ')"></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500">Started</dt>
                                    <dd class="mt-1 text-gray-900" x-text="currentLog?.started_at"></dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500">Duration</dt>
                                    <dd class="mt-1 text-gray-900" x-text="currentLog?.duration_seconds ? currentLog.duration_seconds + 's' : '-'"></dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500">Records Processed</dt>
                                    <dd class="mt-1 text-gray-900" x-text="currentLog?.records_processed || '-'"></dd>
                                </div>
                                <div x-show="currentLog?.user_id">
                                    <dt class="font-medium text-gray-500">Triggered By</dt>
                                    <dd class="mt-1 text-gray-900" x-text="'User ID: ' + currentLog?.user_id"></dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Error Message (if any) -->
                        <div x-show="currentLog?.error_message" class="bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-semibold text-red-800">Error</h4>
                                    <p class="mt-1 text-sm text-red-700" x-text="currentLog?.error_message"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Results Section -->
                        <div x-show="currentLog?.results">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Detailed Results</h4>
                            <pre class="bg-gray-50 p-4 rounded-md text-xs overflow-x-auto border border-gray-200" x-text="JSON.stringify(currentLog?.results, null, 2)"></pre>
                        </div>
                    </div>
                </div>

                <!-- Modal footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <button @click="showLogModal = false"
                            class="w-full inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function syncManager() {
    return {
        loading: false,
        message: '',
        messageType: 'info',
        connectionResults: null,
        ftpStartDate: new Date().toISOString().split('T')[0],
        ftpEndDate: new Date().toISOString().split('T')[0],
        showLogModal: false,
        loadingLog: false,
        currentLog: null,

        async syncAll() {
            this.loading = true;
            this.message = 'Starting complete sync...';
            this.messageType = 'info';

            try {
                const response = await fetch('/notices/sync/all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Unexpected ${response.status} response from server`);
                }

                const data = await response.json();

                if (data.success) {
                    this.message = `Sync completed successfully! Processed ${data.results.polaris?.records || 0} Polaris + ${data.results.shoutbomb?.records || 0} Shoutbomb records.`;
                    this.messageType = 'success';
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    this.message = 'Sync completed with errors. Check the sync history for details.';
                    this.messageType = 'error';
                }
            } catch (error) {
                this.message = 'Sync failed: ' + error.message;
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        },

        async importPolaris() {
            await this.runOperation('polaris', 'Import Polaris');
        },

        async importShoutbombReports() {
            await this.runOperation('shoutbomb-reports', 'Sync Shoutbomb Report Emails');
        },

        async importShoutbombSubmissions() {
            await this.runOperation('shoutbomb-submissions', 'Import Shoutbomb Submissions');
        },

        async importFTPFiles() {
            this.loading = true;
            this.message = `Importing FTP files from ${this.ftpStartDate} to ${this.ftpEndDate}... This may take several minutes.`;
            this.messageType = 'info';

            try {
                const response = await fetch('/notices/sync/ftp-files', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        start_date: this.ftpStartDate,
                        end_date: this.ftpEndDate
                    })
                });

                // Handle HTTP error statuses with specific messages
                if (!response.ok) {
                    // Special handling for 524 timeout errors
                    if (response.status === 524) {
                        throw new Error('Import timed out after ~100 seconds (Cloudflare/proxy timeout). The import may still be running on the server. Please check the sync history in a few minutes to see if it completed. For large date ranges, consider importing smaller ranges (e.g., one week at a time).');
                    }

                    const contentType = response.headers.get('content-type') || '';
                    let errorDetail = '';

                    if (contentType.includes('application/json')) {
                        try {
                            const errorData = await response.json();
                            errorDetail = errorData.message || errorData.error || '';
                        } catch (e) {
                            // JSON parsing failed
                        }
                    }

                    // Don't include HTML error pages in the error message
                    if (!errorDetail || errorDetail.includes('<!DOCTYPE') || errorDetail.includes('<html')) {
                        errorDetail = '';
                    }

                    const errorMsg = errorDetail
                        ? `Server returned ${response.status} ${response.statusText}. ${errorDetail}`
                        : `Server returned ${response.status} ${response.statusText}`;

                    throw new Error(errorMsg);
                }

                const data = await response.json();

                if (data.status === 'success') {
                    // Extract file information from message if available
                    let filesInfo = '';
                    if (data.message && data.message.includes('Files:')) {
                        const filesMatch = data.message.match(/Files: ([^\n]+)/);
                        if (filesMatch) {
                            filesInfo = ` Files imported: ${filesMatch[1]}`;
                        }
                    }

                    this.message = `Import FTP Files completed successfully! ${data.records ? 'Processed ' + data.records + ' records.' : ''}${filesInfo}`;
                    this.messageType = 'success';
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    // Provide detailed error information
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

        async aggregate() {
            await this.runOperation('aggregate', 'Run Aggregation');
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

                // Handle HTTP error statuses with specific messages
                if (!response.ok) {
                    const contentType = response.headers.get('content-type') || '';
                    let errorDetail = '';

                    if (contentType.includes('application/json')) {
                        try {
                            const errorData = await response.json();
                            errorDetail = errorData.message || errorData.error || '';
                        } catch (e) {
                            // JSON parsing failed
                        }
                    }

                    if (!errorDetail) {
                        const text = await response.text();
                        errorDetail = text ? ` Details: ${text.substring(0, 200)}` : '';
                    }

                    throw new Error(`Server returned ${response.status} ${response.statusText}.${errorDetail}`);
                }

                const data = await response.json();

                if (data.status === 'success') {
                    this.message = `${label} completed successfully! ${data.records ? 'Processed ' + data.records + ' records.' : ''}`;
                    this.messageType = 'success';
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    // Provide detailed error information
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
        },

        async testConnections() {
            this.loading = true;
            this.message = 'Testing connections...';
            this.messageType = 'info';

            try {
                const response = await fetch('/notices/sync/test-connections', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Unexpected ${response.status} response from server`);
                }

                const data = await response.json();

                this.connectionResults = data;

                const allSuccess = Object.values(data).every(r => r.status === 'success' || r.status === 'disabled');

                if (allSuccess) {
                    this.message = 'All connections tested successfully!';
                    this.messageType = 'success';
                } else {
                    this.message = 'Some connection tests failed. Check results below.';
                    this.messageType = 'error';
                }
            } catch (error) {
                this.message = 'Connection test failed: ' + error.message;
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        },

        async viewLogDetails(logId) {
            this.showLogModal = true;
            this.loadingLog = true;
            this.currentLog = null;

            try {
                const response = await fetch(`/notices/sync/log/${logId}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Failed to load log details: ${response.status} ${response.statusText}`);
                }

                this.currentLog = await response.json();
            } catch (error) {
                this.message = 'Failed to load log details: ' + error.message;
                this.messageType = 'error';
                this.showLogModal = false;
            } finally {
                this.loadingLog = false;
            }
        }
    }
}
</script>
@endpush
@endsection
