{{-- 
    Reusable component for displaying items from any notification
    
    Usage:
    <x-notice-notification-items :notification="$notification" />
    <x-notice-notification-items :notification="$notification" :limit="5" />
    <x-notice-notification-items :items="$items" :showBarcode="true" :compact="true" />
--}}

@props([
    'notification' => null,
    'items' => null,
    'limit' => null,
    'showBarcode' => true,
    'showCallNumber' => true,
    'showRecordId' => true,
    'showStaffLink' => true,
    'compact' => false,
])

@php
    // Get items from notification or use provided items
    $itemsCollection = $items ?? ($notification?->items ?? collect());
    
    // Apply limit if specified
    if ($limit) {
        $itemsCollection = $itemsCollection->take($limit);
    }
    
    $totalCount = $notification?->items?->count() ?? $items?->count() ?? 0;
@endphp

@if($itemsCollection->isNotEmpty())
    @if($compact)
        {{-- Compact mode: inline list --}}
        <div class="flex flex-wrap gap-2">
            @foreach($itemsCollection as $item)
                @php
                    $title = $item->title ?? $item->Title ?? $item->bibliographic?->Title ?? 'Unknown';
                    $recordId = $item->ItemRecordID ?? null;
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ Str::limit($title, 30) }}
                    @if($showRecordId && $recordId)
                        <span class="ml-1 text-gray-600" title="Record ID: {{ $recordId }}">#{{ $recordId }}</span>
                    @endif
                </span>
            @endforeach
            @if($limit && $totalCount > $limit)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    +{{ $totalCount - $limit }} more
                </span>
            @endif
        </div>
    @else
        {{-- Full mode: detailed list --}}
        <div class="space-y-4">
            @foreach($itemsCollection as $item)
                @php
                    $title = $item->title ?? $item->Title ?? $item->bibliographic?->Title ?? 'Unknown';
                    $author = $item->Author ?? $item->bibliographic?->Author ?? null;
                    $barcode = $item->Barcode ?? $item->item_barcode ?? null;
                    $callNumber = $item->CallNumber ?? null;
                    $recordId = $item->ItemRecordID ?? null;
                    $staffLink = $item->staff_link ?? null;
                @endphp
                <div class="border-l-4 border-blue-200 pl-4 py-2">
                    {{-- Title --}}
                    <div class="font-medium text-gray-900">
                        @if($showStaffLink && $staffLink)
                            <a href="{{ $staffLink }}"
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800 inline-flex items-center">
                                {{ $title }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </a>
                        @else
                            {{ $title }}
                        @endif
                    </div>
                    
                    {{-- Author --}}
                    @if($author)
                        <div class="text-sm text-gray-600">by {{ $author }}</div>
                    @endif
                    
                    {{-- Details Grid --}}
                    <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                        @if($showCallNumber && $callNumber)
                            <div>
                                <span class="text-gray-500">Call Number:</span>
                                <span class="font-mono text-gray-900 block">{{ $callNumber }}</span>
                            </div>
                        @endif
                        
                        @if($showBarcode && $barcode)
                            <div>
                                <span class="text-gray-500">Item Barcode:</span>
                                <span class="font-mono text-gray-900 block">{{ $barcode }}</span>
                            </div>
                        @endif
                    </div>
                    
                    {{-- Record ID and Links --}}
                    @if($showRecordId && $recordId)
                        <div class="mt-2 flex items-center space-x-4 text-xs">
                            <div class="text-gray-500">
                                <span class="font-medium">Item ID:</span>
                                <span class="font-mono">{{ $recordId }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        
        {{-- Show count if limited --}}
        @if($limit && $totalCount > $limit)
            <div class="mt-4 p-3 bg-gray-50 rounded text-sm text-gray-600">
                Showing {{ $itemsCollection->count() }} of {{ $totalCount }} items
            </div>
        @endif
    @endif
@else
    <div class="text-sm text-gray-500 text-center py-4">
        <p>No items recorded for this notification</p>
    </div>
@endif
