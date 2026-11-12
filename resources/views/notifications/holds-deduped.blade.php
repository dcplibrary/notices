@extends('notices::layouts.app')

@section('title', 'Notices – Holds')

@section('body')
    <div class="max-w-7xl mx-auto py-8 px-4" x-data="{ date: '{{ $selectedDate ?? '' }}' }">
        <header class="flex items-center justify-between mb-6">
            @include('notices::partials.header-simple', [
                'title' => 'Hold Notifications',
                'subtitle' => 'Unified notifications showing how holds are actually sent (combined when multiple items ready for same patron).',
            ])

            <form method="GET" class="flex items-center gap-2">
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <span class="sr-only">Date</span>
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-slate-700 text-slate-300">
                        <!-- calendar icon -->
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V4m8 3V4M5 9h14M6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z" />
                        </svg>
                    </span>
                    <input
                        type="date"
                        name="date"
                        x-model="date"
                        class="bg-slate-900 border border-slate-700 rounded-md px-2 py-1 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent"
                    />
                </label>
                <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 rounded-md bg-emerald-500 text-sm font-medium text-slate-900 hover:bg-emerald-400">
                    <!-- play icon -->
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M4.5 3.5a.75.75 0 0 1 1.14-.62l9 6a.75 0.75 0 0 1 0 1.24l-9 6A.75.75 0 0 1 4.5 15.5V3.5z" />
                    </svg>
                </button>
            </form>
        </header>

        <section class="bg-slate-900/70 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="px-4 py-2 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <div>
                    @if($selectedDate)
                        <span class="font-semibold">Date:</span>
                        <span class="ml-1 text-slate-100">{{ $selectedDate }}</span>
                    @else
                        <span class="font-semibold">Showing:</span>
                        <span class="ml-1 text-slate-100">All Notifications</span>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>Combined groups: {{ $combinedCount }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        <span>Total notifications: {{ $totalCount }}</span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                @php
                    $currentSort = $sort ?? request('sort', 'first_exported_at');
                    $currentDir  = $dir ?? strtolower(request('dir', 'asc'));

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

                <table class="min-w-full text-xs" data-testid="holds-deduped-table">
                    <thead class="sticky top-0 z-10 bg-slate-900/90 text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'patron_id', 'label' => 'Patron ID'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'item_record_id', 'label' => 'Item ID'])
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

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'status', 'label' => 'Status'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'first_exported_at', 'label' => 'First Exported'])
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">
                                @include('notices::partials.sortable-header', ['column' => 'last_exported_at', 'label' => 'Last Exported'])
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($notifications as $notification)
@php
                                $raw     = $notification->raw_payload ? json_decode($notification->raw_payload, true) : null;
                                $rawRows = is_array($raw) ? $raw : [];

                                $pickFirstNonEmpty = function (array $rows, string $key) {
                                    foreach ($rows as $row) {
                                        if (! is_array($row)) {
                                            continue;
                                        }

                                        $value = $row[$key] ?? null;
                                        if ($value !== null && $value !== '') {
                                            return $value;
                                        }
                                    }

                                    return null;
                                };

                                $itemId      = $pickFirstNonEmpty($rawRows, 'item_record_id');
                                $patronLast  = $pickFirstNonEmpty($rawRows, 'patron_last_name');
                                $patronFirst = $pickFirstNonEmpty($rawRows, 'patron_first_name');
                                $title       = $pickFirstNonEmpty($rawRows, 'title');
                                $queueId     = $pickFirstNonEmpty($rawRows, 'notification_queue_id');
                                $createdRaw  = $pickFirstNonEmpty($rawRows, 'notification_creation_date');

                                $displayName = null;
                                if ($patronLast || $patronFirst) {
                                    $parts = [];
                                    if ($patronLast) {
                                        $parts[] = $patronLast;
                                    }
                                    if ($patronFirst) {
                                        $parts[] = $patronFirst;
                                    }
                                    $displayName = implode(', ', $parts);
                                }

                                $optionId = $notification->delivery_option_id;
                                $option   = \Dcplibrary\Notices\Models\DeliveryOption::find($optionId);
                                $label    = $option?->delivery_option ?? $optionId;
                                $color    = 'slate';
                                if ($optionId === 1) {
                                    $color = 'sky'; // Mail
                                } elseif ($optionId === 2) {
                                    $color = 'emerald'; // Email
                                } elseif ($optionId === 3) {
                                    $color = 'amber'; // Voice
                                } elseif ($optionId === 8) {
                                    $color = 'pink'; // SMS
                                }

                                $firstExportSort = optional($notification->first_exported_at)->toIso8601String();
                                $lastExportSort  = optional($notification->last_exported_at)->toIso8601String();
@endphp
                            <tr class="hover:bg-slate-800/70">
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $notification->patron_id }}">{{ $notification->patron_id }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $itemId }}">{{ $itemId }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $displayName ?? '' }}">{{ $displayName ?? '—' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $notification->patron_barcode }}">{{ $notification->patron_barcode }}</td>
                                <td class="px-3 py-1.5 max-w-md truncate" title="{{ $title ?? '' }}" data-sort-value="{{ $title ?? '' }}">{{ $title ?? '—' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $optionId }}">
                                    @include('notices::partials.delivery-option-icon', ['optionId' => $optionId, 'label' => $label])
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $queueId ?? '' }}">{{ $queueId ?? '—' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $createdRaw ?? '' }}">{{ $createdRaw ?? '—' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $notification->status }}">
                                    @if($notification->status === 'delivered')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Delivered
                                        </span>
                                    @elseif($notification->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Pending
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Failed
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $firstExportSort }}">{{ optional($notification->first_exported_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap" data-sort-value="{{ $lastExportSort }}">{{ optional($notification->last_exported_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-3 py-4 text-center text-slate-500">No deduplicated holds found for {{ $selectedDate }}. Make sure you've run the import and dedup steps.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-800 bg-slate-900/80 text-xs text-slate-400 flex items-center justify-between">
                <div>
                    @if($notifications instanceof \Illuminate\Contracts\Pagination\Paginator)
                        <span>Showing
                            <span class="text-slate-200">{{ $notifications->firstItem() ?? 0 }}</span>
                            –
                            <span class="text-slate-200">{{ $notifications->lastItem() ?? 0 }}</span>
                            of
                            <span class="text-slate-200">{{ $notifications->total() }}</span>
                            deduplicated holds for
                            <span class="text-slate-200">{{ $selectedDate }}</span>
                        </span>
                    @endif
                </div>
                <div class="text-right">
                    {{ $notifications->links() }}
                </div>
            </div>
        </section>

        {{-- client-side sorter removed; sorting is now handled server-side via ?sort=&dir= --}}
    </div>
@endsection
