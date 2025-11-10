@extends('notices::layouts.app')

@section('title', 'Notification Details')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header with Back Button -->
    <div class="mb-6">
        <a href="{{ route('notices.list') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Notifications List
        </a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Notification Details</h1>
    </div>

    <!-- Status Banner -->
    @php
        $statusColors = [
            1 => 'bg-green-50 border-green-200 text-green-800',  // Voice completed
            2 => 'bg-green-50 border-green-200 text-green-800',  // Answering machine
            12 => 'bg-green-50 border-green-200 text-green-800', // Email completed
            15 => 'bg-green-50 border-green-200 text-green-800', // Mail printed
            16 => 'bg-green-50 border-green-200 text-green-800', // Sent
            13 => 'bg-red-50 border-red-200 text-red-800',       // Email failed - invalid
            14 => 'bg-red-50 border-red-200 text-red-800',       // Email failed
        ];
        $statusColor = $statusColors[$notification->notification_status_id] ?? 'bg-yellow-50 border-yellow-200 text-yellow-800';

        $statusIcons = [
            1 => '✓', 2 => '✓', 12 => '✓', 15 => '✓', 16 => '✓',  // Success statuses
            13 => '✗', 14 => '✗',  // Failure statuses
        ];
        $statusIcon = $statusIcons[$notification->notification_status_id] ?? '⚠';
    @endphp

    <div class="border-l-4 {{ $statusColor }} p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <span class="text-2xl">{{ $statusIcon }}</span>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-medium">{{ $notification->notification_status_name }}</h3>
                <p class="text-sm mt-1">
                    {{ $notification->notification_type_name }} sent via {{ $notification->delivery_method_name }}
                    on {{ $notification->notification_date->format('F j, Y \a\t g:i A') }}
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Patron Information -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Patron Information
                </h2>
            </div>
            <div class="px-6 py-4">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm">
                            @if($notification->patron)
                                <a href="{{ $notification->patron_staff_link }}"
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-800 font-medium inline-flex items-center">
                                    {{ $notification->patron->FormattedName }}
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            @else
                                <span class="text-gray-900">{{ $notification->patron_name }}</span>
                                <span class="text-xs text-gray-400 ml-2">(Polaris data not available)</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Patron ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $notification->patron_id }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Barcode</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $notification->patron_barcode ?? 'N/A' }}</dd>
                    </div>

                    @if($notification->patron && $notification->patron->EmailAddress)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $notification->patron->EmailAddress }}</dd>
                    </div>
                    @endif

                    @if($notification->patron && $notification->patron->PhoneVoice1)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $notification->patron->PhoneVoice1 }}</dd>
                    </div>
                    @endif

                    @if($notification->delivery_string && in_array($notification->delivery_option_id, [2, 3, 8]))
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Delivery Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $notification->delivery_string }}</dd>
                    </div>
                    @endif

                    @if($notification->patron && $notification->patron->ExpirationDate)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Card Expires</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $notification->patron->ExpirationDate->format('F j, Y') }}
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        <!-- Notification Details -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    Notification Details
                </h2>
            </div>
            <div class="px-6 py-4">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Notification Type</dt>
                        <dd class="mt-1">
                            @php
                                $typeIcons = [
                                    1 => '📕', 7 => '⏰', 12 => '📕', 13 => '📕',  // Overdue types
                                    2 => '📦', 18 => '📦',                        // Hold types
                                    3 => '🚫',                                    // Cancel
                                    8 => '💵', 21 => '💵',                        // Fine types
                                ];
                                $typeIcon = $typeIcons[$notification->notification_type_id] ?? '📄';
                            @endphp
                            <span class="text-lg mr-2">{{ $typeIcon }}</span>
                            <span class="text-sm text-gray-900">{{ $notification->notification_type_name }}</span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Delivery Method</dt>
                        <dd class="mt-1">
                            @php
                                $deliveryIcons = [
                                    1 => '📬',  // Mail
                                    2 => '📧',  // Email
                                    3 => '📞',  // Voice
                                    8 => '💬',  // SMS
                                ];
                                $deliveryIcon = $deliveryIcons[$notification->delivery_option_id] ?? '📤';
                            @endphp
                            <span class="text-lg mr-2">{{ $deliveryIcon }}</span>
                            <span class="text-sm text-gray-900">{{ $notification->delivery_method_name }}</span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Delivery To</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">
                            {{ $notification->delivery_string ?? 'N/A' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Notification Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $notification->notification_date->format('l, F j, Y') }}
                            <span class="text-gray-500">at</span>
                            {{ $notification->notification_date->format('g:i A') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 text-sm">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ $statusIcon }} {{ $notification->notification_status_name }}
                            </span>
                        </dd>
                    </div>

                    @if($notification->carrier_name)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Carrier</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $notification->carrier_name }}</dd>
                    </div>
                    @endif

                    @if($notification->language_id)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Language ID</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $notification->language_id }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <!-- Item Counts -->
    <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Item Summary
            </h2>
        </div>
        <div class="px-6 py-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                @if($notification->holds_count > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $notification->holds_count }}</div>
                    <div class="text-xs text-gray-500">Holds</div>
                </div>
                @endif

                @if($notification->overdues_count > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $notification->overdues_count }}</div>
                    <div class="text-xs text-gray-500">1st Overdue</div>
                </div>
                @endif

                @if($notification->overdues_2nd_count > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $notification->overdues_2nd_count }}</div>
                    <div class="text-xs text-gray-500">2nd Overdue</div>
                </div>
                @endif

                @if($notification->overdues_3rd_count > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-800">{{ $notification->overdues_3rd_count }}</div>
                    <div class="text-xs text-gray-500">3rd Overdue</div>
                </div>
                @endif

                @if($notification->cancels_count > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600">{{ $notification->cancels_count }}</div>
                    <div class="text-xs text-gray-500">Cancels</div>
                </div>
                @endif

                @if($notification->bills_count > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $notification->bills_count }}</div>
                    <div class="text-xs text-gray-500">Bills</div>
                </div>
                @endif
            </div>

            @if($notification->total_items == 0)
                <p class="text-sm text-gray-500 text-center py-4">No items recorded for this notification</p>
            @else
                <div class="mt-4 text-center">
                    <span class="text-sm font-medium text-gray-700">Total Items: {{ $notification->total_items }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Items Associated with this Notification (from Polaris) -->
    @if($notification->items->count() > 0)
    <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Items
            </h2>
        </div>
        <div class="px-6 py-4">
            <div class="space-y-4">
                @foreach($notification->items as $item)
                <div class="border-l-4 border-blue-200 pl-4 py-2">
                    @if($item->bibliographic)
                    <div class="font-medium text-gray-900">
                        {{ $item->bibliographic->Title ?? 'Unknown Title' }}
                    </div>
                    @if($item->bibliographic->Author)
                    <div class="text-sm text-gray-600">by {{ $item->bibliographic->Author }}</div>
                    @endif
                    @endif

                    <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                        @if($item->CallNumber)
                        <div>
                            <span class="text-gray-500">Call Number:</span>
                            <span class="font-mono text-gray-900">{{ $item->CallNumber }}</span>
                        </div>
                        @endif

                        @if($item->Barcode)
                        <div>
                            <span class="text-gray-500">Item Barcode:</span>
                            <span class="font-mono text-gray-900">{{ $item->Barcode }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="mt-2 flex items-center space-x-4 text-xs">
                        @if($item->ItemRecordID)
                        <div class="text-gray-500">
                            <span class="font-medium">Item ID:</span>
                            <span class="font-mono">{{ $item->ItemRecordID }}</span>
                        </div>
                        @endif
                        <a href="{{ $item->staff_link }}"
                           target="_blank"
                           class="text-blue-600 hover:text-blue-800 inline-flex items-center">
                            View in Polaris
                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Related Shoutbomb Records -->
    @if($notification->shoutbomb_phone_notices->count() > 0 || $notification->shoutbomb_submissions->count() > 0 || $notification->shoutbomb_deliveries->count() > 0)
    <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                Related Shoutbomb Records
            </h2>
        </div>
        <div class="px-6 py-4 space-y-6">
            <!-- Phone Notices -->
            @if($notification->shoutbomb_phone_notices->count() > 0)
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">PhoneNotices.csv Records ({{ $notification->shoutbomb_phone_notices->count() }})</h3>
                <div class="space-y-2">
                    @foreach($notification->shoutbomb_phone_notices as $phoneNotice)
                    <div class="bg-gray-50 rounded p-3 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div><span class="font-medium text-gray-600">Type:</span> {{ ucfirst($phoneNotice->delivery_type) }}</div>
                            <div><span class="font-medium text-gray-600">Date:</span> {{ $phoneNotice->notice_date->format('M d, Y') }}</div>
                            <div><span class="font-medium text-gray-600">Phone:</span> <span class="font-mono">{{ $phoneNotice->phone_number }}</span></div>
                            <div><span class="font-medium text-gray-600">Library:</span> {{ $phoneNotice->library_name }}</div>
                            @if($phoneNotice->title)
                            <div class="col-span-2"><span class="font-medium text-gray-600">Title:</span> {{ Str::limit($phoneNotice->title, 80) }}</div>
                            @endif
                            @if($phoneNotice->source_file)
                            <div class="col-span-2"><span class="font-medium text-gray-600">Source:</span> <span class="font-mono">{{ $phoneNotice->source_file }}</span></div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Submissions -->
            @if($notification->shoutbomb_submissions->count() > 0)
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Submission Records ({{ $notification->shoutbomb_submissions->count() }})</h3>
                <div class="space-y-2">
                    @foreach($notification->shoutbomb_submissions as $submission)
                    <div class="bg-gray-50 rounded p-3 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div><span class="font-medium text-gray-600">Type:</span> {{ ucfirst($submission->notification_type) }}</div>
                            <div><span class="font-medium text-gray-600">Delivery:</span> {{ ucfirst($submission->delivery_type) }}</div>
                            <div><span class="font-medium text-gray-600">Submitted:</span> {{ $submission->submitted_at->format('M d, Y g:i A') }}</div>
                            @if($submission->phone)
                            <div><span class="font-medium text-gray-600">Phone:</span> <span class="font-mono">{{ $submission->phone }}</span></div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Deliveries -->
            @if($notification->shoutbomb_deliveries->count() > 0)
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Delivery Reports ({{ $notification->shoutbomb_deliveries->count() }})</h3>
                <div class="space-y-2">
                    @foreach($notification->shoutbomb_deliveries as $delivery)
                    <div class="bg-gray-50 rounded p-3 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div><span class="font-medium text-gray-600">Type:</span> {{ ucfirst($delivery->delivery_type) }}</div>
                            <div><span class="font-medium text-gray-600">Status:</span> <span class="@if($delivery->status == 'delivered') text-green-700 @else text-red-700 @endif font-medium">{{ ucfirst($delivery->status) }}</span></div>
                            <div><span class="font-medium text-gray-600">Delivered:</span> {{ $delivery->delivered_at?->format('M d, Y g:i A') ?? 'N/A' }}</div>
                            <div><span class="font-medium text-gray-600">Phone:</span> <span class="font-mono">{{ $delivery->phone }}</span></div>
                            @if($delivery->message)
                            <div class="col-span-2"><span class="font-medium text-gray-600">Message:</span> {{ $delivery->message }}</div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Additional Details -->
    @if($notification->details)
    <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">Additional Details</h2>
        </div>
        <div class="px-6 py-4">
            <pre class="text-xs text-gray-700 whitespace-pre-wrap">{{ $notification->details }}</pre>
        </div>
    </div>
    @endif

    <!-- Metadata -->
    <div class="mt-6 bg-gray-50 rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-700 mb-3">Record Information</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div>
                <dt class="text-gray-500">Notification ID</dt>
                <dd class="font-mono text-gray-900">{{ $notification->id }}</dd>
            </div>
            @if($notification->polaris_log_id)
            <div>
                <dt class="text-gray-500">Polaris Log ID</dt>
                <dd class="font-mono text-gray-900">{{ $notification->polaris_log_id }}</dd>
            </div>
            @endif
            @if($notification->reporting_org_id)
            <div>
                <dt class="text-gray-500">Reporting Org ID</dt>
                <dd class="font-mono text-gray-900">{{ $notification->reporting_org_id }}</dd>
            </div>
            @endif
            <div>
                <dt class="text-gray-500">Imported At</dt>
                <dd class="text-gray-900">{{ $notification->imported_at->format('Y-m-d H:i:s') }}</dd>
            </div>
        </dl>
    </div>
</div>
@endsection
