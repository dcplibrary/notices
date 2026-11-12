@extends('notices::layouts.app')

@section('body')
<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <x-dcpl::page-heading
                title="Delivery Issues"
                description="Track and resolve notification delivery problems"
                class="min-w-0" />
            <div class="text-right shrink-0">
                <p class="text-4xl font-bold text-orange-600">{{ number_format($orphaned_data['total_orphaned']) }}</p>
                <p class="text-xs text-gray-500">Polaris → LX Starter failures</p>
                <div class="mt-2">
                    <form method="GET" action="{{ route('notices.issues.orphaned') }}">
                        <select name="orphaned_days" onchange="this.form.submit()" class="text-xs px-2 py-1 border border-gray-300 rounded bg-white text-gray-700">
                            <option value="1" {{ ($orphaned_days ?? 30) == 1 ? 'selected' : '' }}>last 24 hours</option>
                            <option value="3" {{ ($orphaned_days ?? 30) == 3 ? 'selected' : '' }}>last 3 days</option>
                            <option value="7" {{ ($orphaned_days ?? 30) == 7 ? 'selected' : '' }}>last 7 days</option>
                            <option value="14" {{ ($orphaned_days ?? 30) == 14 ? 'selected' : '' }}>last 14 days</option>
                            <option value="30" {{ ($orphaned_days ?? 30) == 30 ? 'selected' : '' }}>last 30 days</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <x-dcpl::sub-tab-nav label="Issue type">
        <x-dcpl::sub-tab label="Overview"
                         href="{{ route('notices.issues') }}"
                         :active="false" />
        <x-dcpl::sub-tab label="Invalid Contact Information"
                         href="{{ route('notices.issues', ['type' => 'failed_contacts']) }}"
                         :active="false" />
        <x-dcpl::sub-tab label="LX Starter Failures"
                         href="{{ route('notices.issues.orphaned', ['orphaned_days' => $orphaned_days ?? 30]) }}"
                         :active="true" />
    </x-dcpl::sub-tab-nav>

    <!-- Pattern Analysis -->
    @if($orphaned_data['pattern_analysis']['has_pattern'])
    <div class="mb-6 p-6 bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 rounded-lg border-2 border-orange-300 dark:border-orange-700 shadow-md">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 p-3 bg-orange-100 dark:bg-orange-900/40 rounded-lg">
                <svg class="w-8 h-8 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Failure Pattern Detected</h3>
                <p class="text-base text-slate-700 dark:text-slate-300 mb-4">{{ $orphaned_data['pattern_analysis']['description'] }}</p>

                <div class="flex flex-wrap gap-3">
                    @if($orphaned_data['pattern_analysis']['primary_time'])
                    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg border border-orange-200 dark:border-orange-800">
                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-500">Peak Time</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $orphaned_data['pattern_analysis']['primary_time'] }}</p>
                        </div>
                    </div>
                    @endif

                    @if($orphaned_data['pattern_analysis']['primary_day'])
                    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg border border-orange-200 dark:border-orange-800">
                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-500">Peak Day</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $orphaned_data['pattern_analysis']['primary_day'] }}</p>
                        </div>
                    </div>
                    @endif

                    @if(!empty($orphaned_data['pattern_analysis']['affected_types']))
                    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg border border-orange-200 dark:border-orange-800">
                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                        </svg>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-500">Most Affected Type</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ array_key_first($orphaned_data['pattern_analysis']['affected_types']) }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Failure Clusters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Failure Clusters (Last {{ $orphaned_days ?? 30 }} Days)</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Click any cluster to view affected notifications</p>
        </div>

        <div class="p-6">
            @if($orphaned_data['clusters']->isEmpty())
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto text-green-500 dark:text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-slate-600 dark:text-slate-400">No orphaned notifications detected!</p>
            </div>
            @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($orphaned_data['clusters'] as $cluster)
                <a href="{{ route('notices.index', [
                    'orphaned' => 1,
                    'date_from' => $cluster['date'],
                    'date_to' => $cluster['date'],
                    'hour' => $cluster['hour']
                ]) }}"
                   class="block p-4 bg-slate-50 dark:bg-slate-900 rounded-lg border-2 border-slate-200 dark:border-slate-700 hover:border-orange-400 dark:hover:border-orange-500 hover:shadow-lg transition-all group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 bg-orange-500 rounded-full animate-pulse"></div>
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($cluster['date'])->format('M j, Y') }}</span>
                        </div>
                        <span class="text-xl font-bold text-orange-600 dark:text-orange-400 group-hover:text-orange-700 dark:group-hover:text-orange-300">{{ $cluster['count'] }}</span>
                    </div>

                    <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-500 mb-3 pb-3 border-b border-slate-200 dark:border-slate-700">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-medium">{{ $cluster['time_range'] }}</span>
                    </div>

                    <div class="space-y-1.5">
                        @foreach($cluster['notification_types'] as $type => $count)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-400">{{ $type }}</span>
                            <span class="font-semibold text-slate-900 dark:text-white px-2 py-0.5 bg-slate-200 dark:bg-slate-700 rounded">{{ $count }}</span>
                        </div>
                        @endforeach

                        @if($cluster['partial_failure'])
                        <div class="pt-2 mt-2 border-t border-slate-200 dark:border-slate-700">
                            <div class="flex items-center gap-1.5 text-xs">
                                <svg class="w-3 h-3 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-green-600 dark:text-green-400 font-medium">{{ $cluster['successful_at_same_time'] }} sent successfully</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-500 mt-0.5">Partial failure - not all emails affected</p>
                        </div>
                        @else
                        <div class="pt-2 mt-2 border-t border-red-200 dark:border-red-800">
                            <div class="flex items-center gap-1.5 text-xs">
                                <svg class="w-3 h-3 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span class="text-red-600 dark:text-red-400 font-medium">Complete failure</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-500 mt-0.5">No emails sent successfully at this time</p>
                        </div>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    <!-- Recommended Actions -->
    <div class="mt-6 p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Recommended Actions
        </h3>
        <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-2">
            <li class="flex items-start gap-2">
                <span class="text-dcpl-blue dark:text-sky-300 mt-0.5">•</span>
                <span>Check LX Starter logs around <strong>{{ $orphaned_data['pattern_analysis']['primary_time'] ?? '2:00 PM' }}</strong> for errors</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-dcpl-blue dark:text-sky-300 mt-0.5">•</span>
                <span>Verify Polaris export job timing vs LX Starter polling schedule</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-dcpl-blue dark:text-sky-300 mt-0.5">•</span>
                <span>Check for file lock/timing conflicts during peak failure times</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-dcpl-blue dark:text-sky-300 mt-0.5">•</span>
                <span>Review the ~4 PM confirmation cutoff for next morning's export</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-dcpl-blue dark:text-sky-300 mt-0.5">•</span>
                <span>Monitor orphaned notifications >24 hours old for manual intervention</span>
            </li>
        </ul>
    </div>
</div>
@endsection
