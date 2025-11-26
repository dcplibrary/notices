@extends('notices::layouts.app')

@section('title', 'Notification Details')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header with Back Button -->
    <div class="mb-6">
        <a href="/notices/list" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
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
                            @if($notification->patron_staff_link)
                                <a href="{{ $notification->patron_staff_link }}"
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-800 font-medium inline-flex items-center">
                                    @if($notification->patron_first_name && $notification->patron_last_name)
                                        {{ $notification->patron_last_name }}, {{ $notification->patron_first_name }}
                                    @else
                                        {{ $notification->patron_name }}
                                    @endif
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            @else
                                <span class="text-gray-900">
                                    @if($notification->patron_first_name && $notification->patron_last_name)
                                        {{ $notification->patron_last_name }}, {{ $notification->patron_first_name }}
                                    @else
                                        {{ $notification->patron_name }}
                                    @endif
                                </span>
                            @endif
                        </dd>
                    </div>

                    @if($notification->patron_id)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Patron ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $notification->patron_id }}</dd>
                    </div>
                    @endif

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Barcode</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $notification->patron_barcode ?? 'N/A' }}</dd>
                    </div>

                    @if($notification->patron_email)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $notification->patron_email }}</dd>
                    </div>
                    @endif

                    @if($notification->patron_phone)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $notification->patron_phone }}</dd>
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

    <!-- Items Associated with this Notification -->
    @php
        // Get unique phone notices by item_record_id to avoid duplicates
        $phoneNotices = $notification->polaris_phone_notices->unique('item_record_id');
        $items = $notification->items;
    @endphp
    @if($phoneNotices->count() > 0 || $items->count() > 0)
    <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Items ({{ max($phoneNotices->count(), $items->count()) }})
            </h2>
        </div>
        <div class="px-6 py-4">
            <div class="space-y-4">
                @if($phoneNotices->count() > 0)
                    @foreach($phoneNotices as $index => $notice)
                    <div class="border-l-4 border-blue-200 pl-4 py-3 {{ $index > 0 ? 'border-t border-gray-100 pt-4' : '' }}">
                        @if($notice->title)
                            <div class="font-medium text-lg">
                                @if($notice->item_record_id)
                                    <a href="https://catalog.dcplibrary.org/leapwebapp/staff/default#itemrecords/{{ $notice->item_record_id }}"
                                       target="_blank"
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $notice->title }}
                                    </a>
                                @else
                                    <span class="text-gray-900">{{ $notice->title }}</span>
                                @endif
                            </div>
                        @else
                            <div class="font-medium text-lg text-gray-500 italic">
                                Unknown Title
                            </div>
                        @endif

                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                            @if($notice->item_barcode)
                            <div>
                                <span class="text-gray-500">Item Barcode:</span>
                                <span class="font-mono text-gray-900 ml-1">{{ $notice->item_barcode }}</span>
                            </div>
                            @endif

                            @if($notice->library_name)
                            <div>
                                <span class="text-gray-500">Library:</span>
                                <span class="text-gray-900 ml-1">{{ $notice->library_name }}</span>
                            </div>
                            @endif

                            @if($notice->notice_date)
                            <div>
                                <span class="text-gray-500">Notice Date:</span>
                                <span class="text-gray-900 ml-1">{{ $notice->notice_date->format('M d, Y') }}</span>
                            </div>
                            @endif

                            @if($notice->item_record_id)
                            <div>
                                <span class="text-gray-500">Item ID:</span>
                                <span class="font-mono text-gray-900 ml-1">{{ $notice->item_record_id }}</span>
                            </div>
                            @endif

                            @if($notice->delivery_type)
                            <div>
                                <span class="text-gray-500">Delivery Type:</span>
                                <span class="text-gray-900 ml-1">{{ ucfirst($notice->delivery_type) }}</span>
                            </div>
                            @endif
                        </div>

                        @if($notice->item_record_id)
                        <div class="mt-2">
                            <a href="https://catalog.dcplibrary.org/leapwebapp/staff/default#itemrecords/{{ $notice->item_record_id }}"
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800 inline-flex items-center text-xs">
                                View in Polaris
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </a>
                        </div>
                        @endif
                    </div>
                    @endforeach
                @elseif($items->count() > 0)
                    @foreach($items as $index => $item)
                    <div class="border-l-4 border-blue-200 pl-4 py-3 {{ $index > 0 ? 'border-t border-gray-100 pt-4' : '' }}">
                        @if(isset($item->bibliographic) && isset($item->bibliographic->Title))
                            <div class="font-medium text-lg text-gray-900">
                                {{ $item->bibliographic->Title }}
                            </div>
                            @if(isset($item->bibliographic->Author) && $item->bibliographic->Author)
                            <div class="text-sm text-gray-600 mt-1">by {{ $item->bibliographic->Author }}</div>
                            @endif
                        @elseif(isset($item->title))
                            <div class="font-medium text-lg text-gray-900">
                                {{ $item->title }}
                            </div>
                        @else
                            <div class="font-medium text-lg text-gray-500 italic">
                                Unknown Title
                            </div>
                        @endif

                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                            @if(isset($item->Barcode) || isset($item->item_barcode))
                            <div>
                                <span class="text-gray-500">Item Barcode:</span>
                                <span class="font-mono text-gray-900 ml-1">{{ $item->Barcode ?? $item->item_barcode }}</span>
                            </div>
                            @endif

                            @if(isset($item->CallNumber) && $item->CallNumber)
                            <div>
                                <span class="text-gray-500">Call Number:</span>
                                <span class="font-mono text-gray-900 ml-1">{{ $item->CallNumber }}</span>
                            </div>
                            @endif

                            @if(isset($item->ItemRecordID) && $item->ItemRecordID)
                            <div>
                                <span class="text-gray-500">Item ID:</span>
                                <span class="font-mono text-gray-900 ml-1">{{ $item->ItemRecordID }}</span>
                            </div>
                            @endif
                        </div>

                        @if(isset($item->staff_link) && $item->staff_link)
                        <div class="mt-2">
                            <a href="{{ $item->staff_link }}"
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800 inline-flex items-center text-xs">
                                View in Polaris
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </a>
                        </div>
                        @endif
                    </div>
                    @endforeach
                @endif
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
                @php
                    // Group by unique key to avoid duplicates
                    $uniquePhoneNotices = $notification->shoutbomb_phone_notices->groupBy(function($notice) {
                        return $notice->delivery_type . '|' . $notice->item_barcode . '|' . $notice->title;
                    });
                @endphp
                <h3 class="text-sm font-semibold text-gray-700 mb-3">PhoneNotices.csv Records ({{ $uniquePhoneNotices->count() }} unique)</h3>
                <div class="space-y-2">
                    @foreach($uniquePhoneNotices as $group)
                    @php $phoneNotice = $group->first(); @endphp
                    <div class="bg-gray-50 rounded p-3 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div><span class="font-medium text-gray-600">Type:</span> {{ ucfirst($phoneNotice->delivery_type) }}</div>
                            <div><span class="font-medium text-gray-600">Date:</span> {{ $phoneNotice->notice_date->format('M d, Y') }}</div>
                            <div><span class="font-medium text-gray-600">Phone:</span> <span class="font-mono">{{ $phoneNotice->phone_number }}</span></div>
                            <div><span class="font-medium text-gray-600">Library:</span> {{ $phoneNotice->library_name }}</div>
                            @if($phoneNotice->title)
                            <div class="col-span-2"><span class="font-medium text-gray-600">Title:</span> {{ Str::limit($phoneNotice->title, 80) }}</div>
                            @endif
                        </div>
                        @if($group->count() > 1 || $phoneNotice->source_file)
                        <div class="mt-2 pt-2 border-t border-gray-200">
                            <div class="font-medium text-gray-600 mb-1">Import History ({{ $group->count() }} file{{ $group->count() > 1 ? 's' : '' }}):</div>
                            <ul class="space-y-1">
                                @foreach($group as $record)
                                <li class="flex justify-between">
                                    <span class="font-mono text-gray-700">{{ $record->source_file ?? 'Unknown file' }}</span>
                                    <span class="text-gray-500">{{ $record->imported_at->format('M d, Y g:i A') }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Submissions -->
            @if($notification->shoutbomb_submissions->count() > 0)
            <div>
                @php
                    // Group by unique key to avoid duplicates
                    $uniqueSubmissions = $notification->shoutbomb_submissions->groupBy(function($sub) {
                        return $sub->notification_type . '|' . $sub->delivery_type . '|' . $sub->phone;
                    });
                @endphp
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Submission Records ({{ $uniqueSubmissions->count() }} unique)</h3>
                <div class="space-y-2">
                    @foreach($uniqueSubmissions as $group)
                    @php $submission = $group->first(); @endphp
                    <div class="bg-gray-50 rounded p-3 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div><span class="font-medium text-gray-600">Type:</span> {{ ucfirst($submission->notification_type) }}</div>
                            <div><span class="font-medium text-gray-600">Delivery:</span> {{ ucfirst($submission->delivery_type) }}</div>
                            @if($submission->phone)
                            <div class="col-span-2"><span class="font-medium text-gray-600">Phone:</span> <span class="font-mono">{{ $submission->phone }}</span></div>
                            @endif
                        </div>
                        @if($group->count() > 1)
                        <div class="mt-2 pt-2 border-t border-gray-200">
                            <div class="font-medium text-gray-600 mb-1">Submission History ({{ $group->count() }} submission{{ $group->count() > 1 ? 's' : '' }}):</div>
                            <ul class="space-y-1">
                                @foreach($group as $record)
                                <li class="flex justify-between">
                                    <span class="text-gray-700">{{ $record->source_file ?? 'Unknown file' }}</span>
                                    <span class="text-gray-500">{{ $record->submitted_at->format('M d, Y g:i A') }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @else
                        <div class="mt-2">
                            <span class="font-medium text-gray-600">Submitted:</span> {{ $submission->submitted_at->format('M d, Y g:i A') }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Deliveries -->
            @if($notification->shoutbomb_deliveries->count() > 0)
            <div>
                @php
                    // Group by unique key to avoid duplicates
                    $uniqueDeliveries = $notification->shoutbomb_deliveries->groupBy(function($del) {
                        return $del->delivery_type . '|' . $del->phone_number . '|' . $del->status;
                    });
                @endphp
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Delivery Reports ({{ $uniqueDeliveries->count() }} unique)</h3>
                <div class="space-y-2">
                    @foreach($uniqueDeliveries as $group)
                    @php $delivery = $group->first(); @endphp
                    <div class="bg-gray-50 rounded p-3 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div><span class="font-medium text-gray-600">Type:</span> {{ ucfirst($delivery->delivery_type) }}</div>
                            <div><span class="font-medium text-gray-600">Status:</span> <span class="@if($delivery->status === 'Delivered') text-green-700 @else text-red-700 @endif font-medium">{{ $delivery->status }}</span></div>
                            <div><span class="font-medium text-gray-600">Phone:</span> <span class="font-mono">{{ $delivery->phone_number }}</span></div>
                            @if($group->count() == 1)
                            <div><span class="font-medium text-gray-600">Sent:</span> {{ $delivery->sent_date?->format('M d, Y g:i A') ?? 'N/A' }}</div>
                            @endif
                            @if($delivery->message)
                            <div class="col-span-2"><span class="font-medium text-gray-600">Message:</span> {{ $delivery->message }}</div>
                            @endif
                        </div>
                        @if($group->count() > 1)
                        <div class="mt-2 pt-2 border-t border-gray-200">
                            <div class="font-medium text-gray-600 mb-1">Delivery History ({{ $group->count() }} report{{ $group->count() > 1 ? 's' : '' }}):</div>
                            <ul class="space-y-1">
                                @foreach($group as $record)
                                <li class="flex justify-between">
                                    <span class="text-gray-700">{{ $record->source_file ?? 'Report' }}</span>
                                    <span class="text-gray-500">{{ $record->sent_date?->format('M d, Y g:i A') ?? 'N/A' }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
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
