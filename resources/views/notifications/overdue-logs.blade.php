@extends('notices::layouts.app')

@section('title', 'Notices – Overdue Logs')

@section('body')
    <x-notices::ftp-export-shell
        :title="'Overdue Notification Logs'"
        :filename="$filename ?? null"
        :back-url="route('notices.data.export-files')"
    >
        <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                <table class="min-w-full text-xs">
                    <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Patron ID</th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-left">Patron</th>
                            <th class="px-3 py-2 text-left">Barcode</th>
                            <th class="px-3 py-2 text-left">Title</th>
                            <th class="px-3 py-2 text-left">Delivery option</th>
                            <th class="px-3 py-2 text-left">Queue ID</th>
                            <th class="px-3 py-2 text-left">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($overdues as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/70">
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->item_record_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_last_name }}, {{ $row->patron_first_name }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_barcode }}</td>
                                <td class="px-3 py-1.5 max-w-md truncate text-slate-800 dark:text-slate-100" title="{{ $row->title }}">{{ $row->title }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap">
                                    @php
                                        $optionId = (int) $row->delivery_option_id;
                                        $deliveryOption = \Dcplibrary\Notices\Models\DeliveryOption::find($optionId);
                                        $optionTitle = $deliveryOption ? $deliveryOption->display_label : 'Delivery option ' . $optionId;
                                    @endphp
                                    @include('notices::partials.delivery-option-icon', ['optionId' => $optionId, 'label' => $optionTitle])
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap">{{ $row->notification_queue_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap">{{ $row->notification_creation_date }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">
                                    No overdues imported for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/80 text-xs text-slate-600 dark:text-slate-400 flex items-center justify-between">
                <div>
                    @if($overdues instanceof \Illuminate\Contracts\Pagination\Paginator)
                        <span>Showing
                            <span class="text-slate-900 dark:text-slate-100">{{ $overdues->firstItem() ?? 0 }}</span>
                            –
                            <span class="text-slate-900 dark:text-slate-100">{{ $overdues->lastItem() ?? 0 }}</span>
                            of
                            <span class="text-slate-900 dark:text-slate-100">{{ $overdues->total() }}</span>
                            overdues
                        </span>
                    @endif
                </div>
                <div class="text-right">
                    {{ $overdues->links() }}
                </div>
            </div>
        </section>
    </x-notices::ftp-export-shell>
@endsection
