@extends('notices::layouts.app')

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
                <a href="/notices/list?type_id={{ $type->notification_type_id }}"
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
            <h3 class="text-lg font-medium text-gray-900 mb-4">Delivery Method Distribution</h3>
            <div style="height: 300px;">
                <canvas id="deliveryDistChart"></canvas>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($deliveryDistribution as $delivery)
                <a href="/notices/list?delivery_id={{ $delivery->delivery_option_id }}"
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

    <!-- Top Items by Notification Count -->
    <div class="mt-8 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Top Items by Notification Count
            </h3>
        </div>
        <div class="px-6 py-4">
            @if($topItems->isNotEmpty())
                <div class="space-y-3">
                    @foreach($topItems as $index => $item)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $index + 1 }}. {{ Str::limit($item->title, 60) }}
                                </div>
                                @if($item->item_record_id)
                                    <div class="text-xs text-gray-500 mt-1">
                                        Item ID: <span class="font-mono">{{ $item->item_record_id }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-4 text-right">
                                <div class="text-lg font-bold text-blue-600">{{ $item->notification_count }}</div>
                                <div class="text-xs text-gray-500">notification{{ $item->notification_count != 1 ? 's' : '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-sm text-gray-500">No items found in this period</p>
                </div>
            @endif
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
const typeIds = @json($typeDistribution->pluck('notification_type_id'));
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
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const typeId = typeIds[index];
                window.location.href = '/notices/list?type_id=' + typeId;
            }
        },
        onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    }
});

// Delivery Distribution
const deliveryDistCtx = document.getElementById('deliveryDistChart').getContext('2d');
const deliveryIds = @json($deliveryDistribution->pluck('delivery_option_id'));
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
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const deliveryId = deliveryIds[index];
                window.location.href = '/notices/list?delivery_id=' + deliveryId;
            }
        },
        onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    }
});
</script>
@endpush
@endsection
