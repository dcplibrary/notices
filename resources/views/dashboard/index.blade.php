@extends('notices::layouts.app')

@section('title', 'Dashboard Overview')

@section('content')
<div x-data="{
    showDatePicker: false,
    showCustomDateModal: false,
    customStartDate: '',
    customEndDate: '',
    syncing: false,
    syncMessage: '',
    syncStatus: '',
    syncStep: '',
    reloadCountdown: 0
}">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Dashboard Overview</h2>
            <p class="mt-1 text-sm text-gray-600">
                {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            <!-- Sync Button -->
            @if(Auth::check() && Auth::user()->inGroup('Computer Services'))
            <button @click="syncNow()"
                    :disabled="syncing"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                    :class="{ 'scale-95': syncing, 'hover:scale-105': !syncing }">
                <svg x-show="!syncing" class="h-5 w-5 mr-2 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <svg x-show="syncing" class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="syncing ? syncStep || 'Syncing...' : 'Sync Now'"></span>
            </button>
            @endif
            
            <div class="relative">
            <button @click="showDatePicker = !showDatePicker"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                {{ $days }} Days
                <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            
            <!-- Dropdown Menu -->
            <div x-show="showDatePicker" 
                 @click.away="showDatePicker = false"
                 x-cloak
                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                <div class="py-1">
                    <a href="{{ route('notices.dashboard', ['days' => 7]) }}" 
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $days == 7 ? 'bg-gray-50 font-semibold' : '' }}">
                        Last 7 Days
                    </a>
                    <a href="{{ route('notices.dashboard', ['days' => 30]) }}" 
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $days == 30 ? 'bg-gray-50 font-semibold' : '' }}">
                        Last 30 Days
                    </a>
                    <a href="{{ route('notices.dashboard', ['days' => 90]) }}" 
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $days == 90 ? 'bg-gray-50 font-semibold' : '' }}">
                        Last 90 Days
                    </a>
                    <a href="{{ route('notices.dashboard', ['days' => 180]) }}" 
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $days == 180 ? 'bg-gray-50 font-semibold' : '' }}">
                        Last 180 Days
                    </a>
                    <a href="{{ route('notices.dashboard', ['days' => 365]) }}" 
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $days == 365 ? 'bg-gray-50 font-semibold' : '' }}">
                        Last 365 Days
                    </a>
                    <div class="border-t border-gray-200 my-1"></div>
                    <button @click="showCustomDateModal = true; showDatePicker = false"
                            class="w-full text-left block px-4 py-2 text-sm text-indigo-600 hover:bg-gray-100 font-medium">
                        Custom Date Range...
                    </button>
                </div>
            </div>
        </div>
        </div>

        <!-- Custom Date Range Modal -->
        <div x-show="showCustomDateModal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             @keydown.escape.window="showCustomDateModal = false">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                 @click="showCustomDateModal = false"></div>
            
            <!-- Modal -->
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6"
                     @click.away="showCustomDateModal = false">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Custom Date Range</h3>
                        <button @click="showCustomDateModal = false"
                                class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <form method="GET" action="{{ route('notices.dashboard') }}">
                        <div class="space-y-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Start Date
                                </label>
                                <input type="date" 
                                       id="start_date" 
                                       name="start_date"
                                       x-model="customStartDate"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date
                                </label>
                                <input type="date" 
                                       id="end_date" 
                                       name="end_date"
                                       x-model="customEndDate"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button"
                                    @click="showCustomDateModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700">
                                Apply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Status Toast -->
    <div x-show="syncMessage"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed top-4 right-4 z-50 max-w-md w-full">
        <div :class="{
            'bg-green-50 border-green-500': syncStatus === 'success',
            'bg-red-50 border-red-500': syncStatus === 'error',
            'bg-blue-50 border-blue-500': syncStatus === 'info'
        }" class="border-l-4 p-4 rounded-lg shadow-xl">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <!-- Success icon with checkmark animation -->
                    <template x-if="syncStatus === 'success'">
                        <div class="relative">
                            <svg class="h-6 w-6 text-green-500 animate-checkmark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </template>
                    <!-- Error icon -->
                    <template x-if="syncStatus === 'error'">
                        <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </template>
                    <!-- Loading spinner -->
                    <template x-if="syncStatus === 'info'">
                        <svg class="animate-spin h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </template>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-semibold"
                       :class="{
                           'text-green-800': syncStatus === 'success',
                           'text-red-800': syncStatus === 'error',
                           'text-blue-800': syncStatus === 'info'
                       }"
                       x-text="syncMessage"></p>
                    <!-- Countdown indicator for success -->
                    <template x-if="syncStatus === 'success' && reloadCountdown > 0">
                        <div class="mt-2">
                            <p class="text-xs text-green-600 mb-1">Refreshing in <span x-text="reloadCountdown"></span>s...</p>
                            <div class="w-full bg-green-200 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-green-500 h-1.5 rounded-full transition-all duration-1000 ease-linear"
                                     :style="'width: ' + ((3 - reloadCountdown) / 3 * 100) + '%'"></div>
                            </div>
                        </div>
                    </template>
                    <!-- Progress steps during sync -->
                    <template x-if="syncStatus === 'info' && syncStep">
                        <div class="mt-2 flex items-center space-x-1.5">
                            <span class="inline-flex h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                            <span class="inline-flex h-2 w-2 rounded-full bg-blue-400 animate-pulse animate-pulse-delay-1"></span>
                            <span class="inline-flex h-2 w-2 rounded-full bg-blue-300 animate-pulse animate-pulse-delay-2"></span>
                        </div>
                    </template>
                </div>
                <button @click="syncMessage = ''; reloadCountdown = 0"
                        class="ml-auto flex-shrink-0 rounded-full p-1 hover:bg-gray-200 transition-colors"
                        x-show="syncStatus !== 'info'">
                    <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Total Sent -->
        <a href="{{ route('notices.list') }}" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Sent</dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    {{ number_format($totals['total_sent'] ?? 0) }}
                </dd>
            </div>
        </a>

        <!-- Successful -->
        <a href="{{ route('notices.list', ['status' => 'completed']) }}" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Successful</dt>
                <dd class="mt-1 text-3xl font-semibold text-green-600">
                    {{ number_format($totals['total_success'] ?? 0) }}
                </dd>
                <dd class="mt-1 text-xs text-gray-500">
                    {{ number_format($totals['avg_success_rate'] ?? 0, 1) }}% success rate
                </dd>
            </div>
        </a>

        <!-- Failed -->
        <a href="{{ route('notices.list', ['status' => 'failed']) }}" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Failed</dt>
                <dd class="mt-1 text-3xl font-semibold text-red-600">
                    {{ number_format($totals['total_failed'] ?? 0) }}
                </dd>
            </div>
        </a>

        <!-- Unique Patrons -->
        <a href="{{ route('notices.list', ['type_id' => 2]) }}" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Holds</dt>
                <dd class="mt-1 text-3xl font-semibold text-indigo-600">
                    {{ number_format($totals['total_holds'] ?? 0) }}
                </dd>
            </div>
        </a>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-8">
        <!-- Trend Chart -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Notification Trend</h3>
            <div style="height: 250px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- Success Rate Trend -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Success Rate Trend</h3>
            <div style="height: 250px;">
                <canvas id="successRateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Distribution Sections -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-8">
        <!-- Type Distribution -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">By Notification Type</h3>
            <div style="height: 300px;">
                <canvas id="typeChart"></canvas>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($typeDistribution as $type)
                <a href="{{ route('notices.list', ['type_id' => $type->notification_type_id]) }}"
                   class="flex justify-between text-sm p-2 rounded hover:bg-gray-50 transition-colors group">
                    <span class="text-gray-600 group-hover:text-indigo-600">
                        {{ config('notices.notification_types')[$type->notification_type_id] ?? 'Unknown' }}
                    </span>
                    <span class="font-semibold text-gray-900 group-hover:text-indigo-600">
                        {{ number_format($type->total_sent) }}
                    </span>
                </a>
                @endforeach
            </div>
        </div>

        <!-- Delivery Distribution -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">By Delivery Method</h3>
            <div style="height: 300px;">
                <canvas id="deliveryChart"></canvas>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($deliveryDistribution as $delivery)
                <a href="{{ route('notices.list', ['delivery_id' => $delivery->delivery_option_id]) }}"
                   class="flex justify-between text-sm p-2 rounded hover:bg-gray-50 transition-colors group">
                    <span class="text-gray-600 group-hover:text-indigo-600">
                        {{ config('notices.delivery_options')[$delivery->delivery_option_id] ?? 'Unknown' }}
                    </span>
                    <span class="font-semibold text-gray-900 group-hover:text-indigo-600">
                        {{ number_format($delivery->total_sent) }}
                    </span>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Patron Delivery Breakdown -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Unique Patrons by Delivery Method</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($patronsByDelivery as $delivery)
            <a href="{{ route('notices.list', ['delivery_id' => $delivery->delivery_option_id]) }}"
               class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                <dt class="text-sm font-medium text-gray-500">
                    {{ config('notices.delivery_options')[$delivery->delivery_option_id] ?? 'Unknown' }}
                </dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format($delivery->unique_patrons) }}
                </dd>
                <dd class="text-xs text-gray-500 mt-1">patrons</dd>
            </a>
            @endforeach
        </div>
    </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }

    /* Sync button pulse effect when syncing */
    @keyframes sync-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
        50% { box-shadow: 0 0 0 8px rgba(99, 102, 241, 0); }
    }
    button[disabled].bg-indigo-600 {
        animation: sync-pulse 2s infinite;
    }

    /* Success toast entrance animation */
    @keyframes toast-success {
        0% { transform: translateY(-10px) scale(0.95); opacity: 0; }
        50% { transform: translateY(5px) scale(1.02); }
        100% { transform: translateY(0) scale(1); opacity: 1; }
    }

    /* Checkmark draw animation */
    @keyframes checkmark-draw {
        0% { stroke-dashoffset: 50; }
        100% { stroke-dashoffset: 0; }
    }
    .animate-checkmark path {
        stroke-dasharray: 50;
        animation: checkmark-draw 0.5s ease-out forwards;
    }

    /* Staggered dot pulse */
    .animate-pulse-delay-1 { animation-delay: 0.15s; }
    .animate-pulse-delay-2 { animation-delay: 0.3s; }
</style>
@endpush

@push('scripts')
<script>
// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendDates = @json($trendData->pluck('summary_date')->map(fn($d) => $d->format('Y-m-d')));
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: @json($trendData->pluck('summary_date')->map(fn($d) => $d->format('M d'))),
        datasets: [{
            label: 'Sent',
            data: @json($trendData->pluck('total_sent')),
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            tension: 0.3
        }, {
            label: 'Success',
            data: @json($trendData->pluck('total_success')),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.3
        }, {
            label: 'Failed',
            data: @json($trendData->pluck('total_failed')),
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const element = elements[0];
                const index = element.index;
                const datasetIndex = element.datasetIndex;
                const date = trendDates[index];
                
                let url = '{{ route('notices.list') }}?start_date=' + date + '&end_date=' + date;
                
                // Add status filter based on which line was clicked
                // Dataset 0 = Sent (no filter)
                // Dataset 1 = Success (filter to completed status)
                // Dataset 2 = Failed (filter to failed status)
                if (datasetIndex === 1) {
                    url += '&status=completed';
                } else if (datasetIndex === 2) {
                    url += '&status=failed';
                }
                
                window.location.href = url;
            }
        },
        onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    }
});

// Success Rate Trend Chart
const successRateCtx = document.getElementById('successRateChart').getContext('2d');
const successRateDates = @json($successRateTrend->pluck('summary_date')->map(fn($d) => $d->format('Y-m-d')));
new Chart(successRateCtx, {
    type: 'line',
    data: {
        labels: @json($successRateTrend->pluck('summary_date')->map(fn($d) => $d->format('M d'))),
        datasets: [{
            label: 'Success Rate (%)',
            data: @json($successRateTrend->pluck('success_rate')),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const date = successRateDates[index];
                window.location.href = '{{ route('notices.list') }}?start_date=' + date + '&end_date=' + date;
            }
        },
        onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    }
});

// Type Distribution Chart
const typeCtx = document.getElementById('typeChart').getContext('2d');
const typeLabels = @json(collect($byType)->map(function($item) {
    return config('notices.notification_types')[$item['notification_type_id']] ?? 'Unknown';
}));
const typeData = @json(collect($byType)->pluck('total_sent'));
const typeIdsOverview = @json(collect($byType)->pluck('notification_type_id'));

new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeData,
            backgroundColor: [
                'rgb(99, 102, 241)',
                'rgb(34, 197, 94)',
                'rgb(251, 191, 36)',
                'rgb(239, 68, 68)',
                'rgb(168, 85, 247)',
                'rgb(236, 72, 153)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const typeId = typeIdsOverview[index];
                window.location.href = '{{ route('notices.list') }}?type_id=' + typeId;
            }
        },
        onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    }
});

// Delivery Method Chart
const deliveryCtx = document.getElementById('deliveryChart').getContext('2d');
const deliveryLabels = @json(collect($byDelivery)->map(function($item) {
    return config('notices.delivery_options')[$item['delivery_option_id']] ?? 'Unknown';
}));
const deliveryData = @json(collect($byDelivery)->pluck('total_sent'));
const deliveryIdsOverview = @json(collect($byDelivery)->pluck('delivery_option_id'));

new Chart(deliveryCtx, {
    type: 'bar',
    data: {
        labels: deliveryLabels,
        datasets: [{
            label: 'Notifications Sent',
            data: deliveryData,
            backgroundColor: [
                'rgb(99, 102, 241)',
                'rgb(34, 197, 94)',
                'rgb(251, 191, 36)',
                'rgb(239, 68, 68)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const deliveryId = deliveryIdsOverview[index];
                window.location.href = '{{ route('notices.list') }}?delivery_id=' + deliveryId;
            }
        },
        onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    }
});

// Sync Now function added to window for Alpine to access
window.syncNow = async function() {
    const component = Alpine.$data(document.querySelector('[x-data]'));
    component.syncing = true;
    component.syncStep = 'Starting...';
    component.syncMessage = 'Initializing sync process...';
    component.syncStatus = 'info';
    component.reloadCountdown = 0;

    // Progress step simulation for better UX
    const updateStep = (step, message) => {
        component.syncStep = step;
        component.syncMessage = message;
    };

    try {
        // Show initial progress
        updateStep('Connecting...', 'Connecting to data sources...');

        const response = await fetch('/notices/sync/all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        // Show processing state while parsing response
        updateStep('Processing...', 'Processing imported data...');

        const data = await response.json();

        if (data.success) {
            const polarisRecords = data.results?.polaris?.records || 0;
            const shoutbombRecords = data.results?.shoutbomb?.records || 0;
            const totalRecords = polarisRecords + shoutbombRecords;

            component.syncing = false;
            component.syncStep = '';
            component.syncMessage = `Sync complete! Imported ${totalRecords.toLocaleString()} records.`;
            component.syncStatus = 'success';

            // Start countdown with visual feedback
            component.reloadCountdown = 3;

            const countdownInterval = setInterval(() => {
                component.reloadCountdown--;
                if (component.reloadCountdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.reload();
                }
            }, 1000);
        } else {
            component.syncing = false;
            component.syncStep = '';
            component.syncMessage = 'Sync completed with errors. Check Settings for details.';
            component.syncStatus = 'error';
            setTimeout(() => {
                component.syncMessage = '';
            }, 6000);
        }
    } catch (error) {
        component.syncing = false;
        component.syncStep = '';
        component.syncMessage = 'Sync failed: ' + error.message;
        component.syncStatus = 'error';
        setTimeout(() => {
            component.syncMessage = '';
        }, 6000);
    }
};
</script>
@endpush
@endsection
