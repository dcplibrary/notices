@extends('notices::layouts.app')

@section('title', 'Notice Verification')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Notice Verification</h2>
        <p class="mt-1 text-sm text-gray-600">
            Search and verify notice delivery across all systems
        </p>
    </div>

    <!-- Search Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Search Notices</h3>
        <form method="GET" action="{{ route('notices.verification.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Patron Barcode -->
                <div>
                    <label for="patron_barcode" class="block text-sm font-medium text-gray-700">
                        Patron Barcode
                    </label>
                    <input type="text"
                           name="patron_barcode"
                           id="patron_barcode"
                           value="{{ request('patron_barcode') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="23307013757366">
                </div>

                <!-- Phone Number -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">
                        Phone Number
                    </label>
                    <input type="text"
                           name="phone"
                           id="phone"
                           value="{{ request('phone') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="555-123-4567">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email Address
                    </label>
                    <input type="email"
                           name="email"
                           id="email"
                           value="{{ request('email') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="patron@example.com">
                </div>

                <!-- Item Barcode -->
                <div>
                    <label for="item_barcode" class="block text-sm font-medium text-gray-700">
                        Item Barcode
                    </label>
                    <input type="text"
                           name="item_barcode"
                           id="item_barcode"
                           value="{{ request('item_barcode') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="810045">
                </div>

                <!-- Date From -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">
                        Date From
                    </label>
                    <input type="date"
                           name="date_from"
                           id="date_from"
                           value="{{ request('date_from') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <!-- Date To -->
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">
                        Date To
                    </label>
                    <input type="date"
                           name="date_to"
                           id="date_to"
                           value="{{ request('date_to') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('notices.verification.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Clear
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Search
                </button>
            </div>
        </form>
    </div>

    @if(request()->hasAny(['patron_barcode', 'phone', 'email', 'item_barcode']))
        <!-- Summary Stats -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Notices</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ number_format($summary['total']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Verified</dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600">
                        {{ number_format($summary['verified']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Failed</dt>
                    <dd class="mt-1 text-3xl font-semibold text-red-600">
                        {{ number_format($summary['failed']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Pending</dt>
                    <dd class="mt-1 text-3xl font-semibold text-yellow-600">
                        {{ number_format($summary['pending']) }}
                    </dd>
                </div>
            </div>
        </div>

        <!-- Export Button -->
        <div class="mb-4 flex justify-end">
            <a href="{{ route('notices.verification.export', request()->all()) }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export to CSV
            </a>
        </div>

        <!-- Results Table -->
        @if($results->count() > 0)
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Patron
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Contact
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Verification Status
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
                            {{ $result['notice']->notification_date->format('M d, Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $result['notice']->patron_barcode }}
                            </div>
                            @if($result['notice']->patron_name)
                            <div class="text-sm text-gray-500">
                                {{ $result['notice']->patron_name }}
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ config('notices.notification_types')[$result['notice']->notification_type_id] ?? 'Unknown' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($result['notice']->phone)
                            <div class="text-sm text-gray-900">{{ $result['notice']->phone }}</div>
                            @endif
                            @if($result['notice']->email)
                            <div class="text-sm text-gray-500">{{ $result['notice']->email }}</div>
                            @endif
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
                            <div class="mt-1 text-xs text-gray-500">
                                @if($result['verification']->created) ✓ Created @endif
                                @if($result['verification']->submitted) → Submitted @endif
                                @if($result['verification']->verified) → Verified @endif
                                @if($result['verification']->delivered) → Delivered @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('notices.verification.timeline', $result['notice']->id) }}"
                               class="text-indigo-600 hover:text-indigo-900">
                                View Timeline
                            </a>
                            @if($result['notice']->patron_barcode)
                            <span class="text-gray-300">|</span>
                            <a href="{{ route('notices.verification.patron', $result['notice']->patron_barcode) }}"
                               class="text-indigo-600 hover:text-indigo-900">
                                Patron History
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="bg-white shadow rounded-lg p-6 text-center text-gray-500">
            No notices found matching your search criteria.
        </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="bg-white shadow rounded-lg p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No search performed</h3>
            <p class="mt-1 text-sm text-gray-500">
                Enter search criteria above to verify notice delivery.
            </p>
        </div>
    @endif
</div>
@endsection
