@extends('notices::layouts.app')

@section('title', 'Notifications List')

@section('content')
<div class="px-4 sm:px-0" x-data="{ showFilters: false }">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Notifications</h2>
            <p class="mt-1 text-sm text-gray-600">View and filter notification logs</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button @click="showFilters = !showFilters"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span x-text="showFilters ? 'Hide Filters' : 'Show Filters'">Show Filters</span>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div x-show="showFilters" x-cloak class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" class="space-y-4">
            <!-- Search -->
            <div class="col-span-full">
                <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text"
                           name="search"
                           id="search"
                           value="{{ request('search') }}"
                           class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md"
                           placeholder="Search by patron barcode, patron ID, or delivery email/phone...">
                </div>
            </div>

            <!-- Date Range -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                    <input type="date"
                           name="start_date"
                           id="start_date"
                           value="{{ request('start_date', now()->subDays(30)->format('Y-m-d')) }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                    <input type="date"
                           name="end_date"
                           id="end_date"
                           value="{{ request('end_date', now()->format('Y-m-d')) }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>

            <!-- Filters Row -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="type_id" class="block text-sm font-medium text-gray-700">Type</label>
                    <select id="type_id" name="type_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="">All Types</option>
                        @foreach($notificationTypes as $id => $name)
                            <option value="{{ $id }}" {{ request('type_id') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="delivery_id" class="block text-sm font-medium text-gray-700">Delivery Method</label>
                    <select id="delivery_id" name="delivery_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="">All Methods</option>
                        @foreach($deliveryOptions as $id => $name)
                            <option value="{{ $id }}" {{ request('delivery_id') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status_id" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="status_id" name="status_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="">All Statuses</option>
                        @foreach($notificationStatuses as $id => $name)
                            <option value="{{ $id }}" {{ request('status_id') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center space-x-3">
                <button type="submit" class="inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Apply Filters
                </button>
                <a href="{{ route('notices.list') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Notifications Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patron</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($notifications as $notification)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $notification->notification_date->format('M d, Y') }}
                            <div class="text-xs text-gray-500">{{ $notification->notification_date->format('g:i A') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $notification->patron_name }}
                            </div>
                            <div class="text-xs text-gray-500 font-mono">
                                {{ $notification->patron_barcode ?? 'ID: ' . $notification->patron_id }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $typeIcons = [
                                    1 => '📕', 7 => '⏰', 12 => '📕', 13 => '📕',  // Overdue types
                                    2 => '📦', 18 => '📦',                        // Hold types
                                    3 => '🚫',                                    // Cancel
                                    8 => '💵', 21 => '💵',                        // Fine types
                                ];
                                $typeIcon = $typeIcons[$notification->notification_type_id] ?? '📄';
                            @endphp
                            <span class="mr-1">{{ $typeIcon }}</span>
                            <span class="text-gray-900">{{ $notification->notification_type_name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $deliveryIcons = [
                                    1 => '📬',  // Mail
                                    2 => '📧',  // Email
                                    3 => '📞',  // Voice
                                    8 => '💬',  // SMS
                                ];
                                $deliveryIcon = $deliveryIcons[$notification->delivery_option_id] ?? '📤';
                            @endphp
                            <span class="mr-1">{{ $deliveryIcon }}</span>
                            <span class="text-gray-900">{{ $notification->delivery_method_name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            @if($notification->total_items > 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $notification->total_items }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusClasses = [
                                    1 => 'bg-green-100 text-green-800',
                                    2 => 'bg-green-100 text-green-800',
                                    12 => 'bg-green-100 text-green-800',
                                    15 => 'bg-green-100 text-green-800',
                                    16 => 'bg-green-100 text-green-800',
                                    3 => 'bg-yellow-100 text-yellow-800',
                                    4 => 'bg-yellow-100 text-yellow-800',
                                    5 => 'bg-yellow-100 text-yellow-800',
                                    6 => 'bg-yellow-100 text-yellow-800',
                                    13 => 'bg-red-100 text-red-800',
                                    14 => 'bg-red-100 text-red-800',
                                ];
                                $statusClass = $statusClasses[$notification->notification_status_id] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                {{ $notification->notification_status_name }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('notices.notification.detail', $notification->id) }}"
                               class="text-indigo-600 hover:text-indigo-900">
                                View Details
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">No notifications found</p>
                            <p class="mt-1 text-xs text-gray-400">Try adjusting your filters or search term</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($notifications->hasPages())
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            {{ $notifications->links() }}
        </div>
        @endif
    </div>

    <!-- Results Summary -->
    <div class="mt-4 text-sm text-gray-600">
        Showing {{ $notifications->firstItem() ?? 0 }} to {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications
    </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection
