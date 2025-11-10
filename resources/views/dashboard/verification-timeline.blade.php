@extends('notices::layouts.app')

@section('title', 'Notice Timeline')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Notice Timeline</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Tracking notice #{{ $notice->id }} through the delivery lifecycle
                </p>
            </div>
            <a href="{{ route('notices.verification.index') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                ← Back to Search
            </a>
        </div>
    </div>

    <!-- Notice Details Card -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Notice Details</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-sm font-medium text-gray-500">Patron Barcode</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $notice->patron_barcode }}
                    <a href="{{ route('notices.verification.patron', $notice->patron_barcode) }}"
                       class="ml-2 text-indigo-600 hover:text-indigo-900 text-xs">
                        View History →
                    </a>
                </dd>
            </div>

            @if($notice->patron_name)
            <div>
                <dt class="text-sm font-medium text-gray-500">Patron Name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $notice->patron_name }}</dd>
            </div>
            @endif

            <div>
                <dt class="text-sm font-medium text-gray-500">Notice Date</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $notice->notification_date->format('M d, Y H:i:s') }}
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Notice Type</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ config('notices.notification_types')[$notice->notification_type_id] ?? 'Unknown' }}
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Delivery Method</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ config('notices.delivery_options')[$notice->delivery_option_id] ?? 'Unknown' }}
                </dd>
            </div>

            @if($notice->phone)
            <div>
                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $notice->phone }}</dd>
            </div>
            @endif

            @if($notice->email)
            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $notice->email }}</dd>
            </div>
            @endif

            @if($notice->item_barcode)
            <div>
                <dt class="text-sm font-medium text-gray-500">Item Barcode</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $notice->item_barcode }}</dd>
            </div>
            @endif

            @if($notice->title)
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Title</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $notice->title }}</dd>
            </div>
            @endif
        </div>
    </div>

    <!-- Verification Status Card -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Verification Status</h3>

        @php
            $status = $verification->overall_status;
            $statusConfig = [
                'success' => ['color' => 'green', 'icon' => '✅', 'label' => 'Successfully Delivered'],
                'failed' => ['color' => 'red', 'icon' => '❌', 'label' => 'Delivery Failed'],
                'pending' => ['color' => 'yellow', 'icon' => '⏳', 'label' => 'Pending Submission'],
                'partial' => ['color' => 'blue', 'icon' => '🔄', 'label' => 'In Progress'],
            ];
            $config = $statusConfig[$status] ?? ['color' => 'gray', 'icon' => '❓', 'label' => 'Unknown Status'];
        @endphp

        <div class="bg-{{ $config['color'] }}-50 border border-{{ $config['color'] }}-200 rounded-lg p-4 mb-4">
            <div class="flex items-center">
                <span class="text-2xl mr-3">{{ $config['icon'] }}</span>
                <div>
                    <h4 class="text-lg font-semibold text-{{ $config['color'] }}-900">
                        {{ $config['label'] }}
                    </h4>
                    <p class="text-sm text-{{ $config['color'] }}-700">
                        {{ $verification->getStatusMessage() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Verification Steps -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="text-center p-4 rounded-lg {{ $verification->created ? 'bg-green-50 border-2 border-green-500' : 'bg-gray-50 border-2 border-gray-300' }}">
                <div class="text-2xl mb-2">{{ $verification->created ? '✅' : '⭕' }}</div>
                <div class="text-sm font-medium text-gray-900">Created</div>
                @if($verification->created_at)
                <div class="text-xs text-gray-500 mt-1">
                    {{ $verification->created_at->format('M d, H:i') }}
                </div>
                @endif
            </div>

            <div class="text-center p-4 rounded-lg {{ $verification->submitted ? 'bg-green-50 border-2 border-green-500' : 'bg-gray-50 border-2 border-gray-300' }}">
                <div class="text-2xl mb-2">{{ $verification->submitted ? '✅' : '⭕' }}</div>
                <div class="text-sm font-medium text-gray-900">Submitted</div>
                @if($verification->submitted_at)
                <div class="text-xs text-gray-500 mt-1">
                    {{ $verification->submitted_at->format('M d, H:i') }}
                </div>
                @endif
            </div>

            <div class="text-center p-4 rounded-lg {{ $verification->verified ? 'bg-green-50 border-2 border-green-500' : 'bg-gray-50 border-2 border-gray-300' }}">
                <div class="text-2xl mb-2">{{ $verification->verified ? '✅' : '⭕' }}</div>
                <div class="text-sm font-medium text-gray-900">Verified</div>
                @if($verification->verified_at)
                <div class="text-xs text-gray-500 mt-1">
                    {{ $verification->verified_at->format('M d, H:i') }}
                </div>
                @endif
            </div>

            <div class="text-center p-4 rounded-lg {{ $verification->delivered ? 'bg-green-50 border-2 border-green-500' : 'bg-gray-50 border-2 border-gray-300' }}">
                <div class="text-2xl mb-2">{{ $verification->delivered ? '✅' : '⭕' }}</div>
                <div class="text-sm font-medium text-gray-900">Delivered</div>
                @if($verification->delivered_at)
                <div class="text-xs text-gray-500 mt-1">
                    {{ $verification->delivered_at->format('M d, H:i') }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Timeline Card -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Detailed Timeline</h3>

        @if(count($verification->timeline) > 0)
        <div class="flow-root">
            <ul role="list" class="-mb-8">
                @foreach($verification->timeline as $index => $event)
                <li>
                    <div class="relative pb-8">
                        @if($index < count($verification->timeline) - 1)
                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                        @endif
                        <div class="relative flex space-x-3">
                            <div>
                                <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white">
                                    <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                <div>
                                    <p class="text-sm text-gray-900 font-medium">
                                        {{ ucfirst($event['step']) }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        Source: {{ $event['source'] }}
                                    </p>
                                    @if(!empty($event['details']))
                                    <div class="mt-2 text-xs">
                                        @foreach($event['details'] as $key => $value)
                                        @if($value)
                                        <div class="text-gray-600">
                                            <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                            {{ $value }}
                                        </div>
                                        @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                <div class="whitespace-nowrap text-right text-sm text-gray-500">
                                    @if($event['timestamp'])
                                    {{ \Carbon\Carbon::parse($event['timestamp'])->format('M d, Y H:i:s') }}
                                    @else
                                    <span class="text-gray-400">No timestamp</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
        @else
        <p class="text-gray-500 text-center py-4">No timeline events available.</p>
        @endif
    </div>

    <!-- Source Files -->
    @if($verification->submission_file || $verification->verification_file)
    <div class="bg-white shadow rounded-lg p-6 mt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Source Files</h3>
        <div class="space-y-2">
            @if($verification->submission_file)
            <div class="flex items-center text-sm">
                <span class="font-medium text-gray-700 w-32">Submission File:</span>
                <span class="text-gray-900">{{ $verification->submission_file }}</span>
            </div>
            @endif
            @if($verification->verification_file)
            <div class="flex items-center text-sm">
                <span class="font-medium text-gray-700 w-32">Verification File:</span>
                <span class="text-gray-900">{{ $verification->verification_file }}</span>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
