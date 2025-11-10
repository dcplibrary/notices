@extends(notices::layouts.app')

@section('title', 'Analytics')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Analytics</h2>
        <p class="mt-1 text-sm text-gray-600">
            Success rates and trends for the last {{ $days }} days
        </p>
    </div>

    <!-- Success Rate Trend -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Success Rate Trend</h3>
        <div style="height: 200px;">
            <canvas id="successRateChart"></canvas>
        </div>
    </div>

    <!-- Distribution Charts -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Type Distribution -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Notification Type Distribution</h3>
            <div style="height: 300px;">
                <canvas id="typeDistChart"></canvas>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($typeDistribution as $type)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">
                        {{ config('notices.notification_types')[$type->notification_type_id] ?? 'Unknown' }}
                    </span>
                    <span class="font-semibold text-gray-900">
                        {{ number_format($type->total_sent) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Delivery Distribution -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Delivery Method Distribution</h3>
            <div style="height: 300px;">
                <canvas id="deliveryDistChart"></canvas>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($deliveryDistribution as $delivery)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">
                        {{ config('notices.delivery_options')[$delivery->delivery_option_id] ?? 'Unknown' }}
                    </span>
                    <span class="font-semibold text-gray-900">
                        {{ number_format($delivery->total_sent) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Success Rate Trend
const successCtx = document.getElementById('successRateChart').getContext('2d');
new Chart(successCtx, {
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
        }
    }
});

// Type Distribution
const typeDistCtx = document.getElementById('typeDistChart').getContext('2d');
new Chart(typeDistCtx, {
    type: 'pie',
    data: {
        labels: @json($typeDistribution->map(function($item) {
            return config('notices.notification_types')[$item->notification_type_id] ?? 'Unknown';
        })),
        datasets: [{
            data: @json($typeDistribution->pluck('total_sent')),
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

// Delivery Distribution
const deliveryDistCtx = document.getElementById('deliveryDistChart').getContext('2d');
new Chart(deliveryDistCtx, {
    type: 'pie',
    data: {
        labels: @json($deliveryDistribution->map(function($item) {
            return config('notices.delivery_options')[$item->delivery_option_id] ?? 'Unknown';
        })),
        datasets: [{
            data: @json($deliveryDistribution->pluck('total_sent')),
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
                position: 'bottom'
            }
        }
    }
});
</script>
@endpush
@endsection
