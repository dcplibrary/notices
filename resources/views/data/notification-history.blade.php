@extends('notices::layouts.app')

@section('title', 'Notices – Notification History')

@section('body')
    <x-notices::ftp-export-shell
        :title="'Notification History (Polaris)'"
        :filename="$filename ?? ($selectedDate ? 'notification_history_' . $selectedDate . '.txt' : null)"
        :back-url="route('notices.data.export-files')"
    >
        <!-- Channel filter pills -->
        <div class="mb-3 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <span class="mr-2">Channel:</span>
            @php
                $channelParam = $channel ?? null;

                $linkWithChannel = function (?string $ch) use ($selectedDate) {
                    return route('notices.data.notification-history', array_filter([
                        'date'    => $selectedDate,
                        'channel' => $ch,
                    ]));
                };
            @endphp

            <a href="{{ $linkWithChannel(null) }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $channelParam === null ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                All
            </a>
            <a href="{{ $linkWithChannel('mail') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $channelParam === 'mail' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Mail (1)
            </a>
            <a href="{{ $linkWithChannel('email') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $channelParam === 'email' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Email (2)
            </a>
        </div>

        <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                <table class="min-w-full text-xs">
                    <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Patron ID</th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-left">Delivery</th>
                            <th class="px-3 py-2 text-left">Notice Date</th>
                            <th class="px-3 py-2 text-left">Title</th>
                            <th class="px-3 py-2 text-left">Amount</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/70">
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->item_record_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">@notificationTypeLabel($row->notification_type_id)</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100 text-center">
                                    @php
                                        $optionId = (int) ($row->delivery_option_id ?? 0);
                                        $label = app(\Dcplibrary\Notices\Services\LookupLabelService::class)->getDeliveryLabel($optionId);
                                    @endphp
                                    @include('notices::partials.delivery-option-icon', ['optionId' => $optionId, 'label' => $label])
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ optional($row->notice_date)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-1.5 max-w-md truncate text-slate-800 dark:text-slate-100" title="{{ $row->title }}">{{ $row->title }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->amount }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">@notificationStatusLabel($row->notification_status_id)</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">
                                    No NotificationHistory records found for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/80 text-xs text-slate-600 dark:text-slate-400 flex items-center justify-between">
                <div>
                    @if($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
                        <span>Showing
                            <span class="text-slate-900 dark:text-slate-100">{{ $rows->firstItem() ?? 0 }}</span>
                            –
                            <span class="text-slate-900 dark:text-slate-100">{{ $rows->lastItem() ?? 0 }}</span>
                            of
                            <span class="text-slate-900 dark:text-slate-100">{{ $rows->total() }}</span>
                            rows
                        </span>
                    @endif
                </div>
                <div class="text-right">
                    {{ $rows->links() }}
                </div>
            </div>
        </section>
    </x-notices::ftp-export-shell>
@endsection
