@extends('notices::layouts.app')

@section('body')
<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Patron Notification History</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Viewing notifications for patron: <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ $barcode }}</span>
                </p>
            </div>
            <a href="{{ route('notices.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to All Notifications
            </a>
        </div>
    </div>

    @if($notifications->isEmpty())
        <!-- Empty State -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-slate-400 dark:text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">No Notifications Found</h3>
            <p class="text-slate-600 dark:text-slate-400">
                This patron has no notification history.
            </p>
        </div>
    @else
        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Total Notifications -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Total Notifications</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $notifications->total() }}</p>
                    </div>
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-dcpl-blue dark:text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Delivered -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Delivered</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $notifications->where('status', 'delivered')->count() }}</p>
                    </div>
                    <div class="p-3 bg-green-50 dark:bg-green-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Failed -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Failed</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $notifications->where('status', 'failed')->count() }}</p>
                    </div>
                    <div class="p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Pending</p>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $notifications->where('status', 'pending')->count() }}</p>
                    </div>
                    <div class="p-3 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Count -->
        <div class="mb-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Showing {{ $notifications->firstItem() }} to {{ $notifications->lastItem() }} of {{ $notifications->total() }} notifications
            </p>
        </div>

        <!-- Notifications Table -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Method</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Exported</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Delivered</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($notifications as $notification)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <!-- ID -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono text-slate-900 dark:text-white">{{ $notification->id }}</span>
                                </td>

                                <!-- Type -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-slate-900 dark:text-white">{{ $notification->notificationType->description ?? 'N/A' }}</span>
                                </td>

                                <!-- Method -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if($notification->deliveryOption)
                                            @if(stripos($notification->deliveryOption->delivery_option, 'mail') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                            @elseif(stripos($notification->deliveryOption->delivery_option, 'email') !== false || stripos($notification->deliveryOption->delivery_option, 'e-mail') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                                </svg>
                                            @elseif(stripos($notification->deliveryOption->delivery_option, 'voice') !== false || stripos($notification->deliveryOption->delivery_option, 'phone') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                            @elseif(stripos($notification->deliveryOption->delivery_option, 'sms') !== false || stripos($notification->deliveryOption->delivery_option, 'text') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                                </svg>
                                            @endif
                                            <span class="text-sm text-slate-900 dark:text-white">{{ $notification->deliveryOption->delivery_option }}</span>
                                        @else
                                            <span class="text-sm text-slate-500 dark:text-slate-400">N/A</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $uiFailureLabel = null;
                                        if ($notification->status === 'failed') {
                                            $uiFailureLabel = \Dcplibrary\Notices\Support\NotificationFailureLabelResolver::getUiFailureLabel(
                                                (int) ($notification->delivery_option_id ?? 0),
                                                $notification->failure_reason
                                            );
                                        }
                                    @endphp

                                    @if($notification->status === 'delivered')
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-700/50">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Delivered
                                        </span>
                                    @elseif($notification->status === 'failed')
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-700/50">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            {{ $uiFailureLabel ?? 'Failed' }}
                                        </span>
                                    @elseif($notification->status === 'pending')
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-700/50">
                                            <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Pending
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-50 dark:bg-slate-900/30 text-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-700/50">
                                            {{ ucfirst($notification->status) }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Exported -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-slate-900 dark:text-white">
                                        @if($notification->first_exported_at)
                                            {{ \Carbon\Carbon::parse($notification->first_exported_at)->format('M j, Y g:i A') }}
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">—</span>
                                        @endif
                                    </span>
                                </td>

                                <!-- Delivered -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-slate-900 dark:text-white">
                                        @if($notification->delivered_at)
                                            {{ \Carbon\Carbon::parse($notification->delivered_at)->format('M j, Y g:i A') }}
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">—</span>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
