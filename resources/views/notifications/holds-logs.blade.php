@extends('notices::layouts.app')

@section('title', 'Notices – Hold Logs')

@section('body')
    <x-notices::ftp-export-shell
        :title="'Hold Notification Logs'"
        :filename="$filename ?? null"
        :back-url="route('notices.data.export-files')"
    >
        <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                @php
                    $currentSort = $sort ?? request('sort', 'notification_creation_date');
                    $currentDir  = $dir ?? strtolower(request('dir', 'desc'));

                    $sortUrl = function (string $column) use ($currentSort, $currentDir): string {
                        $nextDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';

                        return request()->fullUrlWithQuery(array_merge(
                            request()->query(),
                            ['sort' => $column, 'dir' => $nextDir]
                        ));
                    };

                    $sortIcon = function (string $column) use ($currentSort, $currentDir): string {
                        if ($currentSort !== $column) {
                            return '';
                        }

                        return $currentDir === 'asc' ? '▲' : '▼';
                    };
                @endphp

                <table class="min-w-full text-xs" data-testid="holds-table">
                    <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'patron_id', 'label' => 'Patron ID'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'item_record_id', 'label' => 'Item'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'patron_name', 'label' => 'Patron'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'patron_barcode', 'label' => 'Barcode'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'title', 'label' => 'Title'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'delivery_option_id', 'label' => 'Delivery option'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'notification_queue_id', 'label' => 'Queue ID'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'notification_creation_date', 'label' => 'Created'])
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($holds as $hold)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/70">
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100" data-sort-value="{{ $hold->patron_id }}">{{ $hold->patron_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100" data-sort-value="{{ $hold->item_record_id }}">{{ $hold->item_record_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100" data-sort-value="{{ $hold->patron_last_name }}, {{ $hold->patron_first_name }}">{{ $hold->patron_last_name }}, {{ $hold->patron_first_name }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100" data-sort-value="{{ $hold->patron_barcode }}">{{ $hold->patron_barcode }}</td>
                                <td class="px-3 py-1.5 max-w-md truncate text-slate-800 dark:text-slate-100" title="{{ $hold->title }}" data-sort-value="{{ $hold->title }}">{{ $hold->title }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $hold->delivery_option_id }}">
                                    @php
                                        $optionId = (int) $hold->delivery_option_id;
                                        $deliveryOption = \Dcplibrary\Notices\Models\DeliveryOption::find($optionId);
                                        $optionTitle = $deliveryOption ? $deliveryOption->display_label : 'Delivery option ' . $optionId;
                                    @endphp
                                    @include('notices::partials.delivery-option-icon', ['optionId' => $optionId, 'label' => $optionTitle])
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $hold->notification_queue_id }}">{{ $hold->notification_queue_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $hold->notification_creation_date }}">{{ $hold->notification_creation_date }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-slate-500">No holds imported yet. Run <code class="font-mono text-xs bg-slate-800 px-1.5 py-0.5 rounded">php artisan notices:import 2025-12-18</code>.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/80 text-xs text-slate-600 dark:text-slate-400 flex items-center justify-between">
                <div>
                    @if($holds instanceof \Illuminate\Contracts\Pagination\Paginator)
                        <span>Showing
                            <span class="text-slate-900 dark:text-slate-100">{{ $holds->firstItem() ?? 0 }}</span>
                            –
                            <span class="text-slate-900 dark:text-slate-100">{{ $holds->lastItem() ?? 0 }}</span>
                            of
                            <span class="text-slate-900 dark:text-slate-100">{{ $holds->total() }}</span>
                            holds
                            @if(!empty($selectedDate))
                                on <span class="text-slate-900 dark:text-slate-100">{{ $selectedDate }}</span>
                            @endif
                        </span>
                    @endif
                </div>
                <div class="text-right">
                    {{ $holds->links() }}
                </div>
            </div>
        </section>
    </x-notices::ftp-export-shell>
@endsection
