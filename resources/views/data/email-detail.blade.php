@extends('notices::layouts.app')

@section('body')
<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="{{ route('notices.data.emails') }}" class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Emails
        </a>
    </div>

    <!-- Email Header -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">{{ $email->subject }}</h1>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">From</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $email->sender_email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Received</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $email->received_at?->format('F j, Y g:i A') ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Imported</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $email->imported_at?->format('F j, Y g:i A') ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Message ID</dt>
                <dd class="mt-1 text-sm text-slate-600 dark:text-slate-400 font-mono break-all">{{ $email->message_id }}</dd>
            </div>
        </dl>
    </div>

    <!-- Email Body -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Email Content</h2>

        @if($email->parsed_data && $email->emailReportType?->code === 'shoutbomb_mismatched_preference')
            <!-- Mismatched Preference Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Total Mismatches</dt>
                    <dd class="mt-1 text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($email->parsed_data['mismatches_count'] ?? 0) }}</dd>
                </div>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Report Date</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $email->parsed_data['report_date'] ? \Carbon\Carbon::parse($email->parsed_data['report_date'])->format('F j, Y') : 'N/A' }}</dd>
                </div>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Email Received</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $email->received_at?->format('F j, Y g:i A') ?? 'N/A' }}</dd>
                </div>
            </div>

            <!-- Mismatches Table -->
            @if(!empty($email->parsed_data['mismatches']))
                <div>
                    <h3 class="text-md font-semibold text-orange-600 dark:text-orange-400 mb-3">
                        Mismatched Preferences ({{ count($email->parsed_data['mismatches']) }})
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Home Library</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Patron Phone</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">New Barcodes<br><span class="text-xs text-slate-400">(Latest Preference)</span></th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Old Barcodes<br><span class="text-xs text-slate-400">(Needs Update)</span></th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">New Preference</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Old Preference</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($email->parsed_data['mismatches'] as $mismatch)
                                    @php
                                        $newOption = \Dcplibrary\Notices\Models\DeliveryOption::find($mismatch['new_delivery_option_id'] ?? null);
                                        $oldOption = \Dcplibrary\Notices\Models\DeliveryOption::find($mismatch['old_delivery_option_id'] ?? null);
                                    @endphp
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-3 py-2 text-sm text-slate-900 dark:text-white">{{ $mismatch['home_library'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm font-mono text-slate-900 dark:text-white">{{ $mismatch['patron_phone'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm font-mono text-slate-900 dark:text-white">
                                            @if(!empty($mismatch['new_barcodes']))
                                                {{ implode(', ', $mismatch['new_barcodes']) }}
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm font-mono text-orange-600 dark:text-orange-400">
                                            @if(!empty($mismatch['old_barcodes']))
                                                {{ implode(', ', $mismatch['old_barcodes']) }}
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm">
                                            @if($newOption)
                                                <span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded text-xs">
                                                    {{ $newOption->display_label }}
                                                </span>
                                            @else
                                                <span class="text-slate-400">Unknown</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm">
                                            @if($oldOption)
                                                <span class="px-2 py-1 bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 rounded text-xs">
                                                    {{ $oldOption->display_label }}
                                                </span>
                                            @else
                                                <span class="text-slate-400">Unknown</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg">
                        <p class="text-sm text-orange-800 dark:text-orange-200">
                            <strong>Action Required:</strong> Update the <strong>Old Barcodes</strong> to match the <strong>New Barcodes</strong> preference in Polaris. 
                            The old barcodes are highlighted in orange to indicate they need updating.
                        </p>
                    </div>
                </div>
            @endif

            <!-- Raw Email Content -->
            @if($email->hasFullBody())
                <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                    <h3 class="text-md font-semibold text-slate-900 dark:text-white mb-3">Raw Email Content</h3>
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 overflow-x-auto">
                        <pre class="text-sm text-slate-900 dark:text-slate-100 whitespace-pre-wrap font-mono">{{ $email->body_full }}</pre>
                    </div>
                </div>
            @endif
        @elseif($email->parsed_data && $email->emailReportType?->code === 'polaris_summary_report')
            <!-- Summary Report Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Total Sent</dt>
                    <dd class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($email->parsed_data['total_sent'] ?? 0) }}</dd>
                </div>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Total Failed</dt>
                    <dd class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($email->parsed_data['total_failed'] ?? 0) }}</dd>
                </div>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Report Date</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $email->parsed_data['report_date'] ?? 'N/A' }}</dd>
                </div>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Time Range</dt>
                    <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-white">{{ $email->parsed_data['start_time'] ?? '' }} - {{ $email->parsed_data['end_time'] ?? '' }}</dd>
                </div>
            </div>

            <!-- Successes Table -->
            @if(!empty($email->parsed_data['successes']))
                <div class="mb-6">
                    <h3 class="text-md font-semibold text-green-600 dark:text-green-400 mb-3">
                        Successfully Sent ({{ count($email->parsed_data['successes']) }})
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Patron Name</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Barcode</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Email/Phone</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Method</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($email->parsed_data['successes'] as $success)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-3 py-2 text-sm text-slate-900 dark:text-white">{{ $success['patron_name'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm font-mono text-slate-900 dark:text-white">{{ $success['patron_barcode'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ $success['email'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ $success['notification_type'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm text-center">
                                            @php
                                                $method = $success['delivery_method'] ?? null;
                                                $optionId = $method ? \Dcplibrary\Notices\Models\DeliveryOption::getIdFromMethod((string) $method) : null;
                                                $label = app(\Dcplibrary\Notices\Services\LookupLabelService::class)->getDeliveryLabel($optionId ?: ($method ?? ''));
                                            @endphp
                                            @include('notices::partials.delivery-option-icon', [
                                                'optionId' => $optionId ?: 0,
                                                'label'    => $label ?: 'Unknown',
                                            ])
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Failures Table -->
            @if(!empty($email->parsed_data['failures']))
                <div>
                    <h3 class="text-md font-semibold text-red-600 dark:text-red-400 mb-3">
                        Failed Deliveries ({{ count($email->parsed_data['failures']) }})
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Patron Name</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Barcode</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Email/Phone</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Method</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Reason</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($email->parsed_data['failures'] as $failure)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-3 py-2 text-sm text-slate-900 dark:text-white">{{ $failure['patron_name'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm font-mono text-slate-900 dark:text-white">{{ $failure['patron_barcode'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ $failure['email'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ $failure['notification_type'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-sm text-center">
                                            @php
                                                $method = $failure['delivery_method'] ?? null;
                                                $optionId = $method ? \Dcplibrary\Notices\Models\DeliveryOption::getIdFromMethod((string) $method) : null;
                                                $label = app(\Dcplibrary\Notices\Services\LookupLabelService::class)->getDeliveryLabel($optionId ?: ($method ?? ''));
                                            @endphp
                                            @include('notices::partials.delivery-option-icon', [
                                                'optionId' => $optionId ?: 0,
                                                'label'    => $label ?: 'Unknown',
                                            ])
                                        </td>
                                        <td class="px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ $failure['raw'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Action Required -->
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Action Required:</strong> Review failed deliveries and update patron contact information or delivery preferences in Polaris as needed.
                </p>
            </div>

            <!-- Raw Email Content -->
            @if($email->hasFullBody())
                <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                    <h3 class="text-md font-semibold text-slate-900 dark:text-white mb-3">Raw Email Content</h3>
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 overflow-x-auto">
                        <pre class="text-sm text-slate-900 dark:text-slate-100 whitespace-pre-wrap font-mono">{{ $email->body_full }}</pre>
                    </div>
                </div>
            @endif
        @elseif($email->parsed_data)
            <!-- Generic Parsed Data Display -->
            <div class="mb-6">
                <h3 class="text-md font-semibold text-slate-900 dark:text-white mb-3">Parsed Data</h3>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm text-slate-900 dark:text-slate-100 whitespace-pre-wrap font-mono">{{ json_encode($email->parsed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>

            <!-- Action Required -->
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Action Required:</strong> Review the parsed data above and take appropriate action based on the report type.
                </p>
            </div>

            <!-- Raw Email Content -->
            @if($email->hasFullBody())
                <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                    <h3 class="text-md font-semibold text-slate-900 dark:text-white mb-3">Raw Email Content</h3>
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 overflow-x-auto">
                        <pre class="text-sm text-slate-900 dark:text-slate-100 whitespace-pre-wrap font-mono">{{ $email->body_full }}</pre>
                    </div>
                </div>
            @endif
        @elseif($email->hasFullBody())
            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 overflow-x-auto">
                <pre class="text-sm text-slate-900 dark:text-slate-100 whitespace-pre-wrap font-mono">{{ $email->body_full }}</pre>
            </div>
        @else
            <div class="text-sm text-slate-600 dark:text-slate-400 italic">
                No email body stored
            </div>
        @endif
    </div>

    <!-- Related Issues -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            Related Issues
        </h2>

        @php
            // Build the issues filter URL based on email report date
            $reportDate = $email->report_date ?? $email->received_at;
            $issuesUrl = route('notices.issues', [
                'date_from' => $reportDate?->format('Y-m-d'),
                'date_to' => $reportDate?->format('Y-m-d'),
                'quick_range' => 'custom',
            ]);
        @endphp

        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">View Delivery Issues</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 mb-4">
                See all delivery issues that occurred on {{ $reportDate?->format('F j, Y') ?? 'this date' }}
            </p>
            <a href="{{ $issuesUrl }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-dcpl-blue text-white rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                View Issues for {{ $reportDate?->format('m/d/Y') ?? 'This Date' }}
            </a>
        </div>
    </div>
</div>

<x-notices::unified-notification-modal :admin="true" />
@endsection
