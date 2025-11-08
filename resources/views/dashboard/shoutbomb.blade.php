@extends('notifications::layouts.app')

@section('title', 'Shoutbomb Statistics')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Shoutbomb Statistics</h2>
        <p class="mt-1 text-sm text-gray-600">
            SMS and Voice notification subscriber information
        </p>
    </div>

    @if($latestRegistration)
    <!-- Current Stats -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Subscribers</dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    {{ number_format($latestRegistration->total_subscribers) }}
                </dd>
                <p class="mt-1 text-xs text-gray-500">
                    As of {{ $latestRegistration->snapshot_date->format('M d, Y') }}
                </p>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Text Subscribers</dt>
                <dd class="mt-1 text-3xl font-semibold text-indigo-600">
                    {{ number_format($latestRegistration->total_text_subscribers) }}
                </dd>
                <p class="mt-1 text-xs text-gray-500">
                    {{ number_format($latestRegistration->text_percentage, 1) }}% of total
                </p>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Voice Subscribers</dt>
                <dd class="mt-1 text-3xl font-semibold text-green-600">
                    {{ number_format($latestRegistration->total_voice_subscribers) }}
                </dd>
                <p class="mt-1 text-xs text-gray-500">
                    {{ number_format($latestRegistration->voice_percentage, 1) }}% of total
                </p>
            </div>
        </div>
    </div>
    @endif

    @if($registrationHistory->isNotEmpty())
    <!-- Registration Trend -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Subscriber Growth</h3>
        <canvas id="registrationChart" height="100"></canvas>
    </div>
    @endif

    @if($registrationHistory->isEmpty() && !$latestRegistration)
    <!-- Empty State -->
    <div class="bg-white shadow rounded-lg p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No Shoutbomb data</h3>
        <p class="mt-1 text-sm text-gray-500">
            No Shoutbomb registration data has been imported yet.
        </p>
    </div>
    @endif
</div>

@if($registrationHistory->isNotEmpty())
@push('scripts')
<script>
const registrationCtx = document.getElementById('registrationChart').getContext('2d');
new Chart(registrationCtx, {
    type: 'line',
    data: {
        labels: @json($registrationHistory->pluck('snapshot_date')->map(fn($d) => $d->format('M d'))),
        datasets: [{
            label: 'Total Subscribers',
            data: @json($registrationHistory->pluck('total_subscribers')),
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            tension: 0.3
        }, {
            label: 'Text Subscribers',
            data: @json($registrationHistory->pluck('total_text_subscribers')),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.3
        }, {
            label: 'Voice Subscribers',
            data: @json($registrationHistory->pluck('total_voice_subscribers')),
            borderColor: 'rgb(251, 191, 36)',
            backgroundColor: 'rgba(251, 191, 36, 0.1)',
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
</script>
@endpush
@endif
@endsection
