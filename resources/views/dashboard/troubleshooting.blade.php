@php use Carbon\Carbon; @endphp
@extends('notices::layouts.app')

@section('title', 'Troubleshooting Dashboard')

@section('content')
    <div class="px-4 sm:px-0">
        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Troubleshooting Dashboard</h2>
            <p class="mt-1 text-sm text-gray-600">
                Analyze failures and detect verification mismatches
                <span class="text-gray-400">|</span>
                Last {{ $days }} days
                ({{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }})
            </p>
        </div>

        <!-- Export Button -->
        <div class="mb-4 flex justify-end">
            <a href="/notices/troubleshooting/export?days={{ $days }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export to CSV
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Notices</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ number_format($summary['total_notices']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Actually Failed</dt>
                    <dd class="mt-1 text-3xl font-semibold text-red-600">
                        {{ number_format($summary['failed_count']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Pending (&lt;24h)</dt>
                    <dd class="mt-1 text-3xl font-semibold text-blue-600">
                        {{ number_format($summary['pending_count'] ?? 0) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Assumed Successful</dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600">
                        {{ number_format($summary['assumed_successful'] ?? 0) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Success Rate</dt>
                    <dd class="mt-1 text-3xl font-semibold {{ $summary['success_rate'] >= 95 ? 'text-green-600' : ($summary['success_rate'] >= 85 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($summary['success_rate'], 1) }}%
                    </dd>
                    <p class="text-xs text-gray-500 mt-1">Verified only (&gt;24h)</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Submitted Not Verified</dt>
                    <dd class="mt-1 text-3xl font-semibold text-yellow-600">
                        {{ number_format($summary['submitted_not_verified']) }}
                    </dd>
                </div>
            </div>
        </div>

        <!-- Failure Analysis -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-6">
            <!-- Failures by Reason -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Failures by Reason</h3>
                @if(count($failuresByReason) > 0)
                    <div class="space-y-4">
                        @foreach($failuresByReason as $failure)
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">{{ $failure['reason'] }}</span>
                                    <span class="text-sm text-gray-900 font-semibold">
                            {{ number_format($failure['count']) }} ({{ $failure['percentage'] }}%)
                        </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-red-600 h-2 rounded-full"
                                         style="width: {{ $failure['percentage'] }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No failures recorded in this period</p>
                @endif
            </div>

            <!-- Failures by Type -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Failures by Notification Type</h3>
                @if(count($failuresByType) > 0)
                    <div class="space-y-4">
                        @foreach($failuresByType as $failure)
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">{{ $failure['type'] }}</span>
                                    <span class="text-sm text-gray-900 font-semibold">
                            {{ number_format($failure['count']) }} ({{ $failure['percentage'] }}%)
                        </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-red-600 h-2 rounded-full"
                                         style="width: {{ $failure['percentage'] }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No failures recorded in this period</p>
                @endif
            </div>
        </div>

        <!-- Verification Mismatches -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3 mb-6">
            <!-- Submitted but Not Verified -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-yellow-50">
                    <h3 class="text-lg font-medium text-gray-900">
                        ⚠️ Submitted but Not Verified
                    </h3>
                    <p class="text-sm text-gray-600">
                        Submitted to Shoutbomb but missing from PhoneNotices.csv (>24h old, allows for timing offset)
                    </p>
                </div>
                <div class="px-6 py-4">
                    @if(count($mismatches['submitted_not_verified']) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase">
                                    <th class="pb-2">Patron</th>
                                    <th class="pb-2">Type</th>
                                    <th class="pb-2">Submitted</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                @foreach(array_slice($mismatches['submitted_not_verified'], 0, 10) as $item)
                                    <tr class="text-sm">
                                        <td class="py-2 font-mono">{{ $item['patron_barcode'] }}</td>
                                        <td class="py-2">{{ ucfirst($item['type']) }}</td>
                                        <td class="py-2 text-gray-500">{{ Carbon::parse($item['submitted_at'])->format('M d, H:i') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @if(count($mismatches['submitted_not_verified']) > 10)
                                <p class="mt-2 text-xs text-gray-500 text-center">
                                    Showing 10 of {{ count($mismatches['submitted_not_verified']) }} mismatches
                                </p>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No mismatches detected</p>
                    @endif
                </div>
            </div>

            <!-- Pending Verification -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-blue-50">
                    <h3 class="text-lg font-medium text-gray-900">
                        🕒 Pending Verification
                    </h3>
                    <p class="text-sm text-gray-600">
                        Notices less than 24 hours old, no failure report yet (normal - waiting for verification window)
                    </p>
                </div>
                <div class="px-6 py-4">
                    @if(count($mismatches['pending_verification'] ?? []) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase">
                                    <th class="pb-2">Patron</th>
                                    <th class="pb-2">Item</th>
                                    <th class="pb-2">Notice Date</th>
                                    <th class="pb-2">Age (hrs)</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                @foreach(array_slice($mismatches['pending_verification'] ?? [], 0, 10) as $item)
                                    <tr class="text-sm">
                                        <td class="py-2 font-mono">{{ $item['patron_barcode'] }}</td>
                                        <td class="py-2 font-mono">{{ $item['item_barcode'] ?? 'N/A' }}</td>
                                        <td class="py-2 text-gray-500">{{ Carbon::parse($item['notice_date'])->format('M d, H:i') }}</td>
                                        <td class="py-2 text-gray-500">{{ $item['hours_since_notice'] ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @if(count($mismatches['pending_verification'] ?? []) > 10)
                                <p class="mt-2 text-xs text-gray-500 text-center">
                                    Showing 10 of {{ count($mismatches['pending_verification']) }} pending
                                </p>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No pending verification</p>
                    @endif
                </div>
            </div>

            <!-- Actually Failed -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-red-50">
                    <h3 class="text-lg font-medium text-gray-900">
                        ❌ Actually Failed
                    </h3>
                    <p class="text-sm text-gray-600">
                        Notices with failure reports from Shoutbomb (confirmed delivery failure)
                    </p>
                </div>
                <div class="px-6 py-4">
                    @if(count($mismatches['actually_failed'] ?? []) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase">
                                    <th class="pb-2">Patron</th>
                                    <th class="pb-2">Item</th>
                                    <th class="pb-2">Reason</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                @foreach(array_slice($mismatches['actually_failed'] ?? [], 0, 10) as $item)
                                    <tr class="text-sm">
                                        <td class="py-2 font-mono">{{ $item['patron_barcode'] }}</td>
                                        <td class="py-2 font-mono">{{ $item['item_barcode'] ?? 'N/A' }}</td>
                                        <td class="py-2 text-red-600 text-xs">{{ $item['failure_reason'] ?? 'Unknown' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @if(count($mismatches['actually_failed'] ?? []) > 10)
                                <p class="mt-2 text-xs text-gray-500 text-center">
                                    Showing 10 of {{ count($mismatches['actually_failed']) }} failures
                                </p>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No failures detected</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Failures -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Failures</h3>
            </div>
            @if($recentFailures->count() > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Patron
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Phone
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Failure Reason
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentFailures as $failure)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ Carbon::parse($failure['sent_date'])->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                {{ $failure['patron_barcode'] ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $failure['phone_number'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $failure['failure_reason'] ?? 'Unknown' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                            {{ $failure['status'] }}
                        </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-6 text-center text-gray-500">
                    No failures recorded in this period
                </div>
            @endif
        </div>

        <!-- Date Range Selector -->
        <div class="flex justify-center">
            <div class="inline-flex rounded-md shadow-sm" role="group">
                <a href="/notices/troubleshooting?days=7"
                   class="px-4 py-2 text-sm font-medium {{ $days == 7 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border border-gray-300 rounded-l-lg">
                    7 Days
                </a>
                <a href="/notices/troubleshooting?days=14"
                   class="px-4 py-2 text-sm font-medium {{ $days == 14 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border-t border-b border-gray-300">
                    14 Days
                </a>
                <a href="/notices/troubleshooting?days=30"
                   class="px-4 py-2 text-sm font-medium {{ $days == 30 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border border-gray-300 rounded-r-lg">
                    30 Days
                </a>
            </div>
        </div>
    </div>
@endsection
