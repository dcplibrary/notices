@extends('notifications::layouts.app')

@section('title', 'Dashboard Overview')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Dashboard Overview</h2>
        <p class="mt-1 text-sm text-gray-600">
            Showing data for the last {{ $days }} days
            ({{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }})
        </p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Total Sent -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Sent</dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    {{ number_format($totals['total_sent'] ?? 0) }}
                </dd>
            </div>
        </div>

        <!-- Successful -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Successful</dt>
                <dd class="mt-1 text-3xl font-semibold text-green-600">
                    {{ number_format($totals['total_success'] ?? 0) }}
                </dd>
                <dd class="mt-1 text-xs text-gray-500">
                    {{ number_format($totals['avg_success_rate'] ?? 0, 1) }}% success rate
                </dd>
            </div>
        </div>

        <!-- Failed -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Failed</dt>
                <dd class="mt-1 text-3xl font-semibold text-red-600">
                    {{ number_format($totals['total_failed'] ?? 0) }}
                </dd>
            </div>
        </div>

        <!-- Unique Patrons -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Holds</dt>
                <dd class="mt-1 text-3xl font-semibold text-indigo-600">
                    {{ number_format($totals['total_holds'] ?? 0) }}
                </dd>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-8">
        <!-- Trend Chart -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Notification Trend</h3>
            <canvas id="trendChart" height="200"></canvas>
        </div>

        <!-- Type Distribution -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">By Notification Type</h3>
            <canvas id="typeChart" height="200"></canvas>
        </div>
    </div>

    <!-- Delivery Methods -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-8">
        <!-- Delivery Distribution -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">By Delivery Method</h3>
            <canvas id="deliveryChart" height="200"></canvas>
        </div>

        @if($latestRegistration)
        <!-- Shoutbomb Stats -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Shoutbomb Subscribers</h3>
            <div class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Total Subscribers</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ number_format($latestRegistration->total_subscribers) }}
                    </dd>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Text</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900">
                            {{ number_format($latestRegistration->total_text_subscribers) }}
                            <span class="text-sm text-gray-500">({{ $latestRegistration->text_percentage }}%)</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Voice</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900">
                            {{ number_format($latestRegistration->total_voice_subscribers) }}
                            <span class="text-sm text-gray-500">({{ $latestRegistration->voice_percentage }}%)</span>
                        </dd>
                    </div>
                </div>
                <p class="text-xs text-gray-500">
                    Last updated: {{ $latestRegistration->snapshot_date->format('M d, Y') }}
                </p>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
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
        }
    }
});

// Type Distribution Chart
const typeCtx = document.getElementById('typeChart').getContext('2d');
const typeLabels = @json(collect($byType)->map(function($item) {
    return config('notifications.notification_types')[$item['notification_type_id']] ?? 'Unknown';
}));
const typeData = @json(collect($byType)->pluck('total_sent'));

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
        }
    }
});

// Delivery Method Chart
const deliveryCtx = document.getElementById('deliveryChart').getContext('2d');
const deliveryLabels = @json(collect($byDelivery)->map(function($item) {
    return config('notifications.delivery_options')[$item['delivery_option_id']] ?? 'Unknown';
}));
const deliveryData = @json(collect($byDelivery)->pluck('total_sent'));

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
        }
    }
});
</script>
@endpush
@endsection
