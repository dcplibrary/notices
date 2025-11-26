@php use Carbon\Carbon; @endphp
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
            <div class="mt-4 sm:mt-0 flex flex-wrap items-center gap-2">
                <!-- Active Filters -->
                @if(request('search'))
                    <a href="{{ '/notices/list?' . http_build_query(request()->except(['search', 'page'])) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <span class="text-gray-500 mr-1">Search:</span>
                        <span class="font-semibold">{{ Str::limit(request('search'), 20) }}</span>
                        <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif

                @if(request('type_id'))
                    <a href="{{ '/notices/list?' . http_build_query(request()->except(['type_id', 'page'])) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <span class="text-gray-500 mr-1">Type:</span>
                        <span class="font-semibold">{{ $notificationTypes[request('type_id')] ?? 'Unknown' }}</span>
                        <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif

                @if(request('delivery_id'))
                    <a href="{{ '/notices/list?' . http_build_query(request()->except(['delivery_id', 'page'])) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <span class="text-gray-500 mr-1">Delivery:</span>
                        <span class="font-semibold">{{ $deliveryOptions[request('delivery_id')] ?? 'Unknown' }}</span>
                        <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif

                @if(request('status'))
                    <a href="{{ '/notices/list?' . http_build_query(request()->except(['status', 'page'])) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <span class="text-gray-500 mr-1">Status:</span>
                        <span class="font-semibold">{{ ucfirst(request('status')) }}</span>
                        <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif

                @if(request('status_id'))
                    <a href="{{ '/notices/list?' . http_build_query(request()->except(['status_id', 'page'])) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <span class="text-gray-500 mr-1">Status:</span>
                        <span class="font-semibold">{{ $notificationStatuses[request('status_id')] ?? 'Unknown' }}</span>
                        <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif

                @php
                    $hasCustomDateRange = request('start_date') || request('end_date');
                    $defaultStart = now()->subDays(30)->format('Y-m-d');
                    $defaultEnd = now()->format('Y-m-d');
                    $isCustomDate = (request('start_date') && request('start_date') != $defaultStart) || (request('end_date') && request('end_date') != $defaultEnd);
                @endphp

                @if($isCustomDate)
                    <a href="{{ '/notices/list?' . http_build_query(request()->except(['start_date', 'end_date', 'page'])) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <span class="text-gray-500 mr-1">Date:</span>
                        <span class="font-semibold">
                        {{ request('start_date') ? Carbon::parse(request('start_date'))->format('M d') : 'Start' }}
                        -
                        {{ request('end_date') ? Carbon::parse(request('end_date'))->format('M d, Y') : 'End' }}
                    </span>
                        <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif

                <!-- Show Filters Button -->
                <button @click="showFilters = !showFilters"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/>
                    </svg>
                    <span x-text="showFilters ? 'Hide Filters' : 'Show Filters'">Show Filters</span>
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div x-show="showFilters" x-cloak class="bg-white shadow rounded-lg p-6 mb-6">
            <form method="GET" action="/notices/list" class="space-y-4">
                <!-- Search -->
                <div class="col-span-full">
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                 stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                            </svg>
                        </div>
                        <input type="text"
                               name="search"
                               id="search"
                               value="{{ request('search') }}"
                               class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md"
                               placeholder="Search by patron name, barcode, or delivery email/phone...">
                    </div>
                </div>

                <!-- Date Range Quick Filters -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quick Date Filters</label>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->startOfDay()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            Today
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->subDay()->startOfDay()->format('Y-m-d'), 'end_date' => now()->subDay()->endOfDay()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            Yesterday
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->startOfWeek()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            This Week
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->subWeek()->startOfWeek()->format('Y-m-d'), 'end_date' => now()->subWeek()->endOfWeek()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            Last Week
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->startOfMonth()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            This Month
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->subMonth()->startOfMonth()->format('Y-m-d'), 'end_date' => now()->subMonth()->endOfMonth()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            Last Month
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->startOfYear()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            This Year
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->subDays(7)->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            Last 7 Days
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->subDays(30)->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            Last 30 Days
                        </a>
                        <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['start_date', 'end_date', 'page']), ['start_date' => now()->subDays(90)->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')])) }}"
                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            Last 90 Days
                        </a>
                    </div>
                </div>

                <!-- Custom Date Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Custom Date Range</label>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="start_date" class="block text-xs text-gray-500 mb-1">Start Date</label>
                            <input type="date"
                                   name="start_date"
                                   id="start_date"
                                   value="{{ request('start_date', now()->subDays(30)->format('Y-m-d')) }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-xs text-gray-500 mb-1">End Date</label>
                            <input type="date"
                                   name="end_date"
                                   id="end_date"
                                   value="{{ request('end_date', now()->format('Y-m-d')) }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>
                </div>

                <!-- Filters Row -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="type_id" class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="type_id" name="type_id"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
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
                        <select id="delivery_id" name="delivery_id"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">All Methods</option>
                            @foreach($deliveryOptions as $id => $name)
                                <option value="{{ $id }}" {{ request('delivery_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">All Statuses</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>
                                Completed
                            </option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                            </option>
                            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>
                </div>

                <!-- Detailed Status Filter (shown only when a status is selected) -->
                @if(request('status'))
                    <div>
                        <label for="status_id" class="block text-sm font-medium text-gray-700">Detailed Status
                            ({{ ucfirst(request('status')) }} only)</label>
                        <select id="status_id" name="status_id"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">All {{ ucfirst(request('status')) }} Statuses</option>
                            @foreach($notificationStatuses as $id => $name)
                                <option value="{{ $id }}" {{ request('status_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex items-center space-x-3">
                    <button type="submit"
                            class="inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Apply Filters
                    </button>
                    <a href="/notices/list"
                       class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
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
                        <th scope="col" class="w-8 px-3 py-3"></th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['sort', 'direction', 'page']), ['sort' => 'notification_date', 'direction' => request('sort') == 'notification_date' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}"
                               class="inline-flex items-center hover:text-gray-700 group">
                                Date
                                @if(request('sort') == 'notification_date')
                                    <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         stroke-width="2">
                                        @if(request('direction') == 'asc')
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        @endif
                                    </svg>
                                @else
                                    <svg class="ml-1 w-4 h-4 opacity-0 group-hover:opacity-50" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['sort', 'direction', 'page']), ['sort' => 'patron_barcode', 'direction' => request('sort') == 'patron_barcode' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}"
                               class="inline-flex items-center hover:text-gray-700 group">
                                Patron
                                @if(request('sort') == 'patron_barcode')
                                    <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         stroke-width="2">
                                        @if(request('direction') == 'asc')
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        @endif
                                    </svg>
                                @else
                                    <svg class="ml-1 w-4 h-4 opacity-0 group-hover:opacity-50" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['sort', 'direction', 'page']), ['sort' => 'notification_type_id', 'direction' => request('sort') == 'notification_type_id' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}"
                               class="inline-flex items-center hover:text-gray-700 group">
                                Type
                                @if(request('sort') == 'notification_type_id')
                                    <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         stroke-width="2">
                                        @if(request('direction') == 'asc')
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        @endif
                                    </svg>
                                @else
                                    <svg class="ml-1 w-4 h-4 opacity-0 group-hover:opacity-50" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ '/notices/list?' . http_build_query(array_merge(request()->except(['sort', 'direction', 'page']), ['sort' => 'delivery_option_id', 'direction' => request('sort') == 'delivery_option_id' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}"
                               class="inline-flex items-center hover:text-gray-700 group">
                                Delivery
                                @if(request('sort') == 'delivery_option_id')
                                    <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         stroke-width="2">
                                        @if(request('direction') == 'asc')
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        @endif
                                    </svg>
                                @else
                                    <svg class="ml-1 w-4 h-4 opacity-0 group-hover:opacity-50" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($notifications as $notification)
                        <tr class="hover:bg-gray-50 cursor-pointer"
                            onclick="window.location='{{ '/notices/list/' . $notification->id }}'">
                            <td class="px-3 py-4 whitespace-nowrap">
                                @php
                                    // Status dot colors
                                    $statusDotColors = [
                                        1 => 'bg-green-500',   // Voice completed
                                        2 => 'bg-green-500',   // Answering machine
                                        12 => 'bg-green-500',  // Email completed
                                        15 => 'bg-green-500',  // Mail printed
                                        16 => 'bg-green-500',  // Sent
                                        3 => 'bg-yellow-500',  // Hang up
                                        4 => 'bg-yellow-500',  // Busy
                                        5 => 'bg-yellow-500',  // No answer
                                        6 => 'bg-yellow-500',  // No ring
                                        7 => 'bg-red-500',     // No dial tone
                                        8 => 'bg-red-500',     // Intercept
                                        9 => 'bg-red-500',     // Bad number
                                        10 => 'bg-red-500',    // Max retries
                                        11 => 'bg-red-500',    // Error
                                        13 => 'bg-red-500',    // Email failed invalid
                                        14 => 'bg-red-500',    // Email failed
                                    ];
                                    $dotColor = $statusDotColors[$notification->notification_status_id] ?? 'bg-gray-400';
                                @endphp
                                <span class="inline-block w-2.5 h-2.5 rounded-full {{ $dotColor }}"
                                      title="{{ $notification->notification_status_name }}"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>{{ $notification->notification_date->format('M d, Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $notification->notification_date->format('g:i A') }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $firstName = $notification->patron_first_name ?? '';
                                    $lastName = $notification->patron_last_name ?? '';
                                    $displayName = trim($lastName . ', ' . $firstName);
                                    if ($displayName === ',') {
                                        $displayName = $notification->patron_name;
                                    }
                                @endphp
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $displayName }}
                                </div>
                                <div class="text-xs text-gray-500 font-mono">
                                    {{ $notification->patron_barcode ?? 'ID: ' . $notification->patron_id }}
                                </div>
                                @if($notification->delivery_string)
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $notification->delivery_string }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    // Get items from phone notices for performance
                                    $phoneNotices = $notification->polaris_phone_notices;
                                    $itemCount = $phoneNotices->count();
                                    $firstNotice = $phoneNotices->first();
                                @endphp
                                @if($firstNotice && $firstNotice->title)
                                    <div class="text-sm">
                                        @if($firstNotice->item_record_id)
                                            <a href="https://catalog.dcplibrary.org/leapwebapp/staff/default#itemrecords/{{ $firstNotice->item_record_id }}"
                                               target="_blank"
                                               class="text-blue-600 hover:text-blue-800 hover:underline"
                                               onclick="event.stopPropagation();">
                                                {{ Str::limit($firstNotice->title, 60) }}
                                            </a>
                                        @else
                                            <span class="text-gray-900">{{ Str::limit($firstNotice->title, 60) }}</span>
                                        @endif
                                    </div>
                                    @if($firstNotice->item_barcode)
                                        <div class="text-xs text-gray-500 font-mono mt-0.5">
                                            {{ $firstNotice->item_barcode }}
                                        </div>
                                    @endif
                                    @if($itemCount > 1)
                                        <div class="text-xs text-gray-500 mt-1">
                                            +{{ $itemCount - 1 }} more
                                        </div>
                                    @endif
                                @else
                                    <div class="text-sm text-gray-500">No item details</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-xs font-medium text-gray-900">
                                    {{ $notification->notification_type_name }}
                                </div>
                                @if($notification->total_items > 0)
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $notification->total_items }}
                                        item{{ $notification->total_items != 1 ? 's' : '' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-1.5">
                                    @if(in_array($notification->delivery_option_id, [1]))
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z"/>
                                        </svg>
                                    @elseif(in_array($notification->delivery_option_id, [2]))
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                        </svg>
                                    @elseif(in_array($notification->delivery_option_id, [3, 4, 5]))
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                                        </svg>
                                    @elseif(in_array($notification->delivery_option_id, [8]))
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                                        </svg>
                                    @endif
                                    <div class="text-xs">
                                        <div class="text-gray-900">{{ explode(' ', $notification->delivery_method_name)[0] }}</div>
                                        <div class="text-gray-500 text-[10px]">{{ $notification->notification_status_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ '/notices/list/' . $notification->id }}"
                                   class="text-indigo-600 hover:text-indigo-900 text-xs">
                                    Details →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
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
            Showing {{ $notifications->firstItem() ?? 0 }} to {{ $notifications->lastItem() ?? 0 }}
            of {{ $notifications->total() }} notifications
        </div>
    </div>

    @push('styles')
        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>
    @endpush
@endsection
