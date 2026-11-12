@extends('notices::layouts.app')

@section('title', 'Notices – Notification Log')

@section('body')
    <x-notices::ftp-export-shell
        :title="'Notification Log Entries'"
        :filename="$filename ?? null"
        :back-url="route('notices.data.export-files')"
    >
        <!-- Channel filter pills -->
        <div class="mb-2 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <span class="mr-2">Channel:</span>
            @php
                $channelParam = $channel ?? null;
                $typeParam    = $type ?? null;

                $linkWithChannel = function (?string $ch) use ($selectedDate, $typeParam) {
                    return route('notices.data.notification-log', array_filter([
                        'date'    => $selectedDate,
                        'channel' => $ch,
                        'type'    => $typeParam,
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

        <!-- Type filter pills -->
        <div class="mb-3 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <span class="mr-2">Type:</span>
            @php
                $typeParam = $type ?? null;

                $linkWithType = function (?string $t) use ($selectedDate, $channelParam) {
                    return route('notices.data.notification-log', array_filter([
                        'date'    => $selectedDate,
                        'channel' => $channelParam,
                        'type'    => $t,
                    ]));
                };
            @endphp

            <a href="{{ $linkWithType(null) }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === null ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                All
            </a>
            <a href="{{ $linkWithType('hold') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'hold' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Holds
            </a>
            <a href="{{ $linkWithType('overdue') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'overdue' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Overdues (All)
            </a>
            <a href="{{ $linkWithType('overdue1') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'overdue1' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                1st Overdue
            </a>
            <a href="{{ $linkWithType('overdue2') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'overdue2' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                2nd Overdue
            </a>
            <a href="{{ $linkWithType('overdue3') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'overdue3' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                3rd Overdue
            </a>
            <a href="{{ $linkWithType('bill') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'bill' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Bills
            </a>
            <a href="{{ $linkWithType('collection') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'collection' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Collections
            </a>
            <a href="{{ $linkWithType('card') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $typeParam === 'card' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300 bg-blue-50 dark:bg-blue-900/40' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Card Exp.
            </a>
        </div>

        <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                <table class="min-w-full text-xs">
                    <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Patron ID</th>
                            <th class="px-3 py-2 text-left">Barcode</th>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-left">Delivery</th>
                            <th class="px-3 py-2 text-left">Notice Date</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($logs as $row)
                            @php
                                $optionId       = (int) ($row->delivery_option_id ?? 0);
                                $deliveryString = (string) ($row->delivery_string ?? '');
                                $detailsDisplay = $row->details;

                                if (!$detailsDisplay) {
                                    if (in_array($optionId, [2, 3, 8], true) && $deliveryString !== '') {
                                        if ($optionId === 8 && str_contains($deliveryString, '@')) {
                                            // SMS: show the 10-digit phone number before the @, if available
                                            $phonePart = explode('@', $deliveryString)[0] ?? '';
                                            $digits    = preg_replace('/\D+/', '', $phonePart);
                                            $detailsDisplay = strlen($digits) === 10 ? $digits : ($phonePart ?: $deliveryString);
                                        } else {
                                            // Email or Voice: show the delivery string as-is
                                            $detailsDisplay = $deliveryString;
                                        }
                                    }
                                }
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/70">
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_barcode }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">@notificationTypeLabel($row->notification_type_id)</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100 text-center">
                                    @php
                                        $label = app(\Dcplibrary\Notices\Services\LookupLabelService::class)->getDeliveryLabel($optionId);
                                    @endphp
                                    @include('notices::partials.delivery-option-icon', ['optionId' => $optionId, 'label' => $label])
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ optional($row->notice_date)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">@notificationStatusLabel($row->notification_status_id)</td>
                                <td class="px-3 py-1.5 whitespace-pre-line text-slate-800 dark:text-slate-100">{{ $detailsDisplay ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">
                                    No Notification Log entries found for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/80 text-xs text-slate-600 dark:text-slate-400 flex items-center justify-between">
                <div>
                    @if($logs instanceof \Illuminate\Contracts\Pagination\Paginator)
                        <span>Showing
                            <span class="text-slate-900 dark:text-slate-100">{{ $logs->firstItem() ?? 0 }}</span>
                            –
                            <span class="text-slate-900 dark:text-slate-100">{{ $logs->lastItem() ?? 0 }}</span>
                            of
                            <span class="text-slate-900 dark:text-slate-100">{{ $logs->total() }}</span>
                            entries
                        </span>
                    @endif
                </div>
                <div class="text-right">
                    {{ $logs->links() }}
                </div>
            </div>
        </section>
    </x-notices::ftp-export-shell>
@endsection
