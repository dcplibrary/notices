@extends('notices::layouts.app')

@section('title', 'Shoutbomb Statistics')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Shoutbomb Statistics</h2>
        <p class="mt-1 text-sm text-gray-600">
            SMS and Voice notification tracking and subscriber information
        </p>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <form method="GET" class="flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700">
                Date Range:
                <select name="days" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30" {{ $days == 30 ? 'selected' : '' }}>Last 30 days</option>
                    <option value="90" {{ $days == 90 ? 'selected' : '' }}>Last 90 days</option>
                </select>
            </label>
            <button type="submit" class="mt-5 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Update
            </button>
        </form>
    </div>

    @if($submissionStats && $submissionStats->total_submissions > 0)
    <!-- Submission Statistics -->
    <div class="mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Notices Sent to Shoutbomb (Last {{ $days }} days)</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Submissions</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ number_format($submissionStats->total_submissions) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ number_format($submissionStats->unique_patrons) }} unique patrons
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Hold Notices</dt>
                    <dd class="mt-1 text-3xl font-semibold text-blue-600">
                        {{ number_format($submissionStats->holds_count) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $submissionStats->total_submissions > 0 ? number_format(($submissionStats->holds_count / $submissionStats->total_submissions) * 100, 1) : 0 }}% of total
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Overdue Notices</dt>
                    <dd class="mt-1 text-3xl font-semibold text-red-600">
                        {{ number_format($submissionStats->overdue_count) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $submissionStats->total_submissions > 0 ? number_format(($submissionStats->overdue_count / $submissionStats->total_submissions) * 100, 1) : 0 }}% of total
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Renewal Notices</dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600">
                        {{ number_format($submissionStats->renew_count) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $submissionStats->total_submissions > 0 ? number_format(($submissionStats->renew_count / $submissionStats->total_submissions) * 100, 1) : 0 }}% of total
                    </p>
                </div>
            </div>
        </div>

        <!-- Delivery Method Breakdown -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Voice Notifications</dt>
                    <dd class="mt-1 text-3xl font-semibold text-purple-600">
                        {{ number_format($submissionStats->voice_count) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $submissionStats->total_submissions > 0 ? number_format(($submissionStats->voice_count / $submissionStats->total_submissions) * 100, 1) : 0 }}% of submissions
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Text (SMS) Notifications</dt>
                    <dd class="mt-1 text-3xl font-semibold text-indigo-600">
                        {{ number_format($submissionStats->text_count) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $submissionStats->total_submissions > 0 ? number_format(($submissionStats->text_count / $submissionStats->total_submissions) * 100, 1) : 0 }}% of submissions
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Submission Trend Chart -->
    @if($submissionTrend->isNotEmpty())
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Submission Trend</h3>
        <div style="height: 300px;">
            <canvas id="submissionTrendChart"></canvas>
        </div>
    </div>
    @endif

    <!-- Recent Submissions -->
    @if($recentSubmissions->isNotEmpty())
    <div class="bg-white shadow rounded-lg overflow-hidden mb-8">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Recent Submissions</h3>
            <p class="mt-1 text-sm text-gray-500">Last 10 notices sent to Shoutbomb</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patron</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source File</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentSubmissions as $submission)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $submission->submitted_at->format('M d, Y g:i A') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $submission->notification_type === 'holds' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $submission->notification_type === 'overdue' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $submission->notification_type === 'renew' ? 'bg-green-100 text-green-800' : '' }}">
                                {{ ucfirst($submission->notification_type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $submission->patron_barcode }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($submission->delivery_type)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $submission->delivery_type === 'voice' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $submission->delivery_type === 'text' ? 'bg-indigo-100 text-indigo-800' : '' }}">
                                {{ ucfirst($submission->delivery_type) }}
                            </span>
                            @else
                            <span class="text-sm text-gray-500">Unknown</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $submission->source_file }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @else
    <!-- Empty State for Submissions -->
    <div class="bg-white shadow rounded-lg p-12 text-center mb-8">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No submission data</h3>
        <p class="mt-1 text-sm text-gray-500">
            No Shoutbomb submission files have been imported for the selected date range.
        </p>
        <div class="mt-4">
            <p class="text-xs text-gray-500">
                Import submission files using:<br>
                <code class="bg-gray-100 px-2 py-1 rounded text-xs">php artisan notices:import-shoutbomb-submissions</code>
            </p>
        </div>
    </div>
    @endif

    @if($phoneNoticeStats && $phoneNoticeStats->total_notices > 0)
    <!-- Phone Notice Verification Stats -->
    <div class="mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">PhoneNotices.csv Verification (Last {{ $days }} days)</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Verified</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ number_format($phoneNoticeStats->total_notices) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ number_format($phoneNoticeStats->unique_patrons) }} unique patrons
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Voice Verified</dt>
                    <dd class="mt-1 text-3xl font-semibold text-purple-600">
                        {{ number_format($phoneNoticeStats->voice_count) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $phoneNoticeStats->total_notices > 0 ? number_format(($phoneNoticeStats->voice_count / $phoneNoticeStats->total_notices) * 100, 1) : 0 }}% of total
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Text Verified</dt>
                    <dd class="mt-1 text-3xl font-semibold text-indigo-600">
                        {{ number_format($phoneNoticeStats->text_count) }}
                    </dd>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $phoneNoticeStats->total_notices > 0 ? number_format(($phoneNoticeStats->text_count / $phoneNoticeStats->total_notices) * 100, 1) : 0 }}% of total
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($latestRegistration)
    <!-- Subscriber Statistics -->
    <div class="mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Subscriber Information</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
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
    </div>
    @endif

    @if($registrationHistory->isNotEmpty())
    <!-- Registration Trend -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Subscriber Growth</h3>
        <div style="height: 200px;">
            <canvas id="registrationChart"></canvas>
        </div>
    </div>
    @endif
</div>

@if($submissionTrend->isNotEmpty())
@push('scripts')
<script>
// Submission Trend Chart
const submissionCtx = document.getElementById('submissionTrendChart').getContext('2d');

// Prepare data
const dates = @json($submissionTrend->keys());
const trendData = @json($submissionTrend);

// Group by notification type
const holdsData = [];
const overdueData = [];
const renewData = [];

dates.forEach(date => {
    const dayData = trendData[date];

    let holds = 0, overdue = 0, renew = 0;

    dayData.forEach(item => {
        if (item.notification_type === 'holds') holds = item.count;
        if (item.notification_type === 'overdue') overdue = item.count;
        if (item.notification_type === 'renew') renew = item.count;
    });

    holdsData.push(holds);
    overdueData.push(overdue);
    renewData.push(renew);
});

new Chart(submissionCtx, {
    type: 'line',
    data: {
        labels: dates.map(d => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
        datasets: [{
            label: 'Holds',
            data: holdsData,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.3
        }, {
            label: 'Overdues',
            data: overdueData,
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.3
        }, {
            label: 'Renewals',
            data: renewData,
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
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
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>
@endpush
@endif

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
