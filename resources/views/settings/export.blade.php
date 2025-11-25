@extends('notices::layouts.app')

@section('title', 'Export & Backup')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Export & Backup</h1>
            <p class="mt-1 text-sm text-gray-600">
                Export configuration data and create database backups for disaster recovery
            </p>
        </div>

        <!-- Reference Data Configuration Export -->
        <div class="bg-white shadow-sm rounded-lg mb-6">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Reference Data Configuration</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Export notification types, delivery methods, and status configurations
                </p>
            </div>
            <div class="px-6 py-5">
                <div class="flex flex-wrap gap-3">
                    <a href="/notices/export/reference-data"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                        <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export as JSON
                    </a>
                    <a href="/notices/export/reference-data-sql"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                        <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export as SQL
                    </a>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    Use these exports to migrate settings between environments or restore after changes
                </p>
            </div>
        </div>

        <!-- Notification Data Export -->
        <div class="bg-white shadow-sm rounded-lg mb-6" x-data="{ 
            format: 'csv', 
            startDate: '{{ now()->subDays(30)->format('Y-m-d') }}', 
            endDate: '{{ now()->format('Y-m-d') }}',
            loading: false,
            exportData() {
                this.loading = true;
                const form = this.$refs.exportForm;
                form.submit();
                setTimeout(() => { this.loading = false; }, 2000);
            }
        }">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Notification Data Export</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Export notification logs by date range for reporting or archival
                </p>
            </div>
            <div class="px-6 py-5">
                <form x-ref="exportForm" method="POST" action="/notices/export/notification-data">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" 
                                   name="start_date" 
                                   id="start_date" 
                                   x-model="startDate"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" 
                                   name="end_date" 
                                   id="end_date" 
                                   x-model="endDate"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="format" class="block text-sm font-medium text-gray-700">Format</label>
                            <select name="format" 
                                    id="format" 
                                    x-model="format"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="button"
                                @click="exportData()"
                                :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50">
                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span x-text="loading ? 'Exporting...' : 'Export Notification Data'"></span>
                        </button>
                    </div>
                </form>
                <p class="mt-3 text-xs text-gray-500">
                    Large date ranges may take longer to export. CSV format is recommended for spreadsheet analysis.
                </p>
            </div>
        </div>

        <!-- Database Backup -->
        <div class="bg-white shadow-sm rounded-lg" x-data="{ 
            tables: {
                notification_logs: true,
                daily_notification_summaries: true,
                sync_logs: true,
                notification_types: true,
                delivery_methods: true,
                notification_statuses: true
            },
            loading: false,
            backupDatabase() {
                const selectedTables = Object.keys(this.tables).filter(table => this.tables[table]);
                if (selectedTables.length === 0) {
                    alert('Please select at least one table to backup');
                    return;
                }
                this.loading = true;
                const form = this.$refs.backupForm;
                form.submit();
                setTimeout(() => { this.loading = false; }, 2000);
            }
        }">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Database Backup</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Create SQL backup dumps of selected tables for disaster recovery
                </p>
            </div>
            <div class="px-6 py-5">
                <form x-ref="backupForm" method="POST" action="/notices/export/database-backup">
                    @csrf
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-900 mb-3">Select Tables</legend>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="tables[]" 
                                       value="notification_logs" 
                                       id="table_notification_logs"
                                       x-model="tables.notification_logs"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="table_notification_logs" class="ml-2 text-sm text-gray-700">
                                    Notification Logs <span class="text-gray-500">(primary notification data)</span>
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="tables[]" 
                                       value="daily_notification_summaries" 
                                       id="table_daily_summaries"
                                       x-model="tables.daily_notification_summaries"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="table_daily_summaries" class="ml-2 text-sm text-gray-700">
                                    Daily Summaries <span class="text-gray-500">(aggregated stats)</span>
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="tables[]" 
                                       value="sync_logs" 
                                       id="table_sync_logs"
                                       x-model="tables.sync_logs"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="table_sync_logs" class="ml-2 text-sm text-gray-700">
                                    Sync Logs <span class="text-gray-500">(import history)</span>
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="tables[]" 
                                       value="notification_types" 
                                       id="table_notification_types"
                                       x-model="tables.notification_types"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="table_notification_types" class="ml-2 text-sm text-gray-700">
                                    Notification Types <span class="text-gray-500">(reference data)</span>
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="tables[]" 
                                       value="delivery_methods" 
                                       id="table_delivery_methods"
                                       x-model="tables.delivery_methods"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="table_delivery_methods" class="ml-2 text-sm text-gray-700">
                                    Delivery Methods <span class="text-gray-500">(reference data)</span>
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="tables[]" 
                                       value="notification_statuses" 
                                       id="table_notification_statuses"
                                       x-model="tables.notification_statuses"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="table_notification_statuses" class="ml-2 text-sm text-gray-700">
                                    Notification Statuses <span class="text-gray-500">(reference data)</span>
                                </label>
                            </div>
                        </div>
                    </fieldset>
                    <div class="mt-4">
                        <button type="button"
                                @click="backupDatabase()"
                                :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50">
                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            <span x-text="loading ? 'Creating Backup...' : 'Create Database Backup'"></span>
                        </button>
                    </div>
                </form>
                <p class="mt-3 text-xs text-gray-500">
                    Backups include complete INSERT statements for easy restoration. Large tables may generate very large files.
                </p>
            </div>
        </div>

        <!-- Help Text -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Backup Best Practices</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Export reference data configuration before making changes to types, methods, or statuses</li>
                            <li>Create database backups before major system updates or migrations</li>
                            <li>Store backups in a secure location separate from the application server</li>
                            <li>Test backup restoration procedures periodically to ensure data integrity</li>
                            <li>For large datasets, consider exporting notification data in smaller date ranges</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
