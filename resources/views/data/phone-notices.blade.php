@extends('notices::layouts.app')

@section('title', 'Notices – Phone Notices')

@section('body')
    <x-notices::ftp-export-shell
        :title="'Phone Notices (Voice/SMS)'"
        :filename="$filename ?? ($selectedDate ? 'PhoneNotices_' . $selectedDate . '.csv' : null)"
        :back-url="route('notices.data.export-files')"
    >
        <!-- Channel filter pills -->
        <div class="mb-3 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <span class="mr-2">Channel:</span>
            @php
                $channelParam = $channel ?? null;

                $linkWithChannel = function (?string $ch) use ($selectedDate) {
                    return route('notices.data.phone-notices', array_filter([
                        'date'    => $selectedDate,
                        'channel' => $ch,
                    ]));
                };
            @endphp

            <a href="{{ $linkWithChannel(null) }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $channelParam === null ? 'border-dcpl-blue text-dcpl-blue dark:text-sky-300 bg-dcpl-blue/10 dark:bg-dcpl-blue/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                All
            </a>
            <a href="{{ $linkWithChannel('voice') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $channelParam === 'voice' ? 'border-dcpl-blue text-dcpl-blue dark:text-sky-300 bg-dcpl-blue/10 dark:bg-dcpl-blue/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Voice (3)
            </a>
            <a href="{{ $linkWithChannel('sms') }}"
               class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] {{ $channelParam === 'sms' ? 'border-dcpl-blue text-dcpl-blue dark:text-sky-300 bg-dcpl-blue/10 dark:bg-dcpl-blue/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                SMS (8)
            </a>
        </div>

        <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                @php
                    $currentSort = $sort ?? 'export_datetime';
                    $currentDir  = $dir  ?? 'desc';

                    $sortUrl = function ($column) use ($currentSort, $currentDir, $selectedDate, $channel) {
                        $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
                        $params = array_merge(request()->query(), ['sort' => $column, 'dir' => $newDir]);
                        return route('notices.data.phone-notices', $params);
                    };

                    $sortIcon = function ($column) use ($currentSort, $currentDir) {
                        if ($currentSort !== $column) {
                            return '';
                        }
                        return $currentDir === 'asc' ? '▲' : '▼';
                    };
                @endphp

                <table class="min-w-full text-xs">
                    <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'patron_last_name', 'label' => 'Patron'])
                            </th>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'patron_barcode', 'label' => 'Barcode'])
                            </th>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'patron_phone', 'label' => 'Phone'])
                            </th>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'patron_email', 'label' => 'Email'])
                            </th>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'site_name', 'label' => 'Site'])
                            </th>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'item_title', 'label' => 'Title'])
                            </th>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'delivery_option_id', 'label' => 'Channel'])
                            </th>
                            <th class="px-3 py-2 text-left">
                                @include('notices::partials.sortable-header', ['column' => 'export_datetime', 'label' => 'Exported At'])
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/70">
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">
                                    {{ $row->patron_last_name }}, {{ $row->patron_first_name }}
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_barcode }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_phone }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->patron_email }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $row->site_name }}</td>
                                <td class="px-3 py-1.5 max-w-md truncate text-slate-800 dark:text-slate-100" title="{{ $row->item_title }}">{{ $row->item_title }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100 text-center">
                                    @php
                                        $optionId = (int) ($row->delivery_option_id ?? 0);
                                        $label = app(\Dcplibrary\Notices\Services\LookupLabelService::class)->getDeliveryLabel($optionId);
                                    @endphp
                                    @include('notices::partials.delivery-option-icon', ['optionId' => $optionId, 'label' => $label])
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">
                                    {{ optional($row->export_datetime)->format('Y-m-d H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">
                                    No PhoneNotices records found for this date.
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
