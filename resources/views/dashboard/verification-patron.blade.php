@extends('notices::layouts.app')

@section('title', 'Patron Verification History')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Patron Verification History</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Barcode: <span class="font-mono font-semibold">{{ $barcode }}</span>
                    <span class="text-gray-400">|</span>
                    Last {{ $days }} days
                    ({{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }})
                </p>
            </div>
            <a href="/notices/verification"
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                ← Back to Search
            </a>
        </div>
    </div>

    <!-- Export Button -->
    <div class="mb-4 flex justify-end">
        <a href="/notices/verification/patron/{{ $barcode }}/export?days={{ $days }}"
           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export to CSV
        </a>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-6">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Notices</dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    {{ number_format($stats['total_notices']) }}
                </dd>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Successful</dt>
                <dd class="mt-1 text-3xl font-semibold text-green-600">
                    {{ number_format($stats['success_count']) }}
                </dd>
                <dd class="mt-1 text-xs text-gray-500">
                    {{ number_format($stats['success_rate'], 1) }}% success rate
                </dd>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Failed</dt>
                <dd class="mt-1 text-3xl font-semibold text-red-600">
                    {{ number_format($stats['failed_count']) }}
                </dd>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Pending</dt>
                <dd class="mt-1 text-3xl font-semibold text-yellow-600">
                    {{ number_format($stats['pending_count']) }}
                </dd>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-6">
        <!-- By Type -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Notices by Type</h3>
            @if(count($byType) > 0)
            <div class="space-y-3">
                @foreach($byType as $type => $count)
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">{{ $type }}</span>
                    <span class="text-sm text-gray-900 font-semibold">{{ number_format($count) }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ ($count / $stats['total_notices']) * 100 }}%"></div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-gray-500 text-center py-4">No data available</p>
            @endif
        </div>

        <!-- Success Rate Chart Placeholder -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Success Rate</h3>
            <div class="flex items-center justify-center" style="height: 200px;">
                <div class="text-center">
                    <div class="text-6xl font-bold {{ $stats['success_rate'] >= 90 ? 'text-green-600' : ($stats['success_rate'] >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($stats['success_rate'], 1) }}%
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        {{ $stats['success_count'] }} of {{ $stats['total_notices'] }} delivered successfully
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notice History Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Notice History</h3>
        </div>
        @if(count($results) > 0)
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Delivery Method
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Item
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Verification
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($results as $result)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $result['notice']->notification_date->format('M d, Y') }}
                        <div class="text-xs text-gray-500">
                            {{ $result['notice']->notification_date->format('H:i') }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ config('notices.notification_types')[$result['notice']->notification_type_id] ?? 'Unknown' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ config('notices.delivery_options')[$result['notice']->delivery_option_id] ?? 'Unknown' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($result['notice']->item_barcode)
                        <div class="text-sm text-gray-900 font-mono">{{ $result['notice']->item_barcode }}</div>
                        @endif
                        @if($result['notice']->title)
                        <div class="text-xs text-gray-500 max-w-xs truncate">{{ $result['notice']->title }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center space-x-1 text-xs">
                            <span class="{{ $result['verification']->created ? 'text-green-600' : 'text-gray-300' }}" title="Created">
                                {{ $result['verification']->created ? '●' : '○' }}
                            </span>
                            <span class="{{ $result['verification']->submitted ? 'text-green-600' : 'text-gray-300' }}" title="Submitted">
                                {{ $result['verification']->submitted ? '●' : '○' }}
                            </span>
                            <span class="{{ $result['verification']->verified ? 'text-green-600' : 'text-gray-300' }}" title="Verified">
                                {{ $result['verification']->verified ? '●' : '○' }}
                            </span>
                            <span class="{{ $result['verification']->delivered ? 'text-green-600' : 'text-gray-300' }}" title="Delivered">
                                {{ $result['verification']->delivered ? '●' : '○' }}
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php
                            $status = $result['verification']->overall_status;
                            $statusColors = [
                                'success' => 'bg-green-100 text-green-800',
                                'failed' => 'bg-red-100 text-red-800',
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'partial' => 'bg-blue-100 text-blue-800',
                            ];
                            $statusLabels = [
                                'success' => 'Delivered',
                                'failed' => 'Failed',
                                'pending' => 'Pending',
                                'partial' => 'In Progress',
                            ];
                        @endphp
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ $statusLabels[$status] ?? ucfirst($status) }}
                        </span>
                        @if($result['verification']->failure_reason)
                        <div class="text-xs text-red-600 mt-1">
                            {{ $result['verification']->failure_reason }}
                        </div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="/notices/verification/{{ $result['notice']->id }}"
                           class="text-indigo-600 hover:text-indigo-900">
                            View Timeline
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="p-6 text-center text-gray-500">
            No notices found for this patron in the selected date range.
        </div>
        @endif
    </div>

    <!-- Date Range Selector -->
    <div class="mt-6 flex justify-center">
        <div class="inline-flex rounded-md shadow-sm" role="group">
            <a href="/notices/verification/patron/{{ $barcode }}?days=30"
               class="px-4 py-2 text-sm font-medium {{ $days == 30 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border border-gray-300 rounded-l-lg">
                30 Days
            </a>
            <a href="/notices/verification/patron/{{ $barcode }}?days=90"
               class="px-4 py-2 text-sm font-medium {{ $days == 90 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border-t border-b border-gray-300">
                90 Days
            </a>
            <a href="/notices/verification/patron/{{ $barcode }}?days=180"
               class="px-4 py-2 text-sm font-medium {{ $days == 180 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border border-gray-300 rounded-r-lg">
                180 Days
            </a>
        </div>
    </div>
</div>
@endsection
