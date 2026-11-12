<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Http\Resources\FailedContactResource;
use Dcplibrary\Notices\Http\Resources\IssueResource;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Services\LookupTableService;
use Dcplibrary\Notices\Services\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for issue-focused screens and APIs.
 */
class IssuesController extends Controller
{
    protected NotificationService $notificationService;
    protected LookupTableService $lookupTableService;

    public function __construct(NotificationService $notificationService, LookupTableService $lookupTableService)
    {
        $this->notificationService = $notificationService;
        $this->lookupTableService  = $lookupTableService;
    }

    /**
     * Display all active delivery issues.
     */
    public function issues(Request $request)
    {
        $issueType  = $request->input('type');
        $quickRange = $request->input('quick_range');

        // Normalize optional date range for charts/links.
        // For the Issues **overview** (no specific type selected), we now
        // default to **yesterday** so the stats line up with the dashboard,
        // but when quick_range=all we intentionally leave the range open
        // (all time) and let downstream services treat nulls as unbounded.
        $rawDateFrom = $request->input('date_from');
        $rawDateTo   = $request->input('date_to');

        if (!$issueType) {
            if ('all' === $quickRange) {
                // Explicit "All Time" selection: do not constrain by date.
                $dateFrom = null;
                $dateTo   = null;
            } else {
                // When no explicit range is supplied, default to the last 7 days
                // (trailing window ending today) so the Issues overview feels
                // more like a recent-activity view.
                $today        = now()->toDateString();
                $sevenDaysAgo = now()->subDays(7)->toDateString();

                $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $rawDateFrom)
                    ? $rawDateFrom
                    : $sevenDaysAgo;

                $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $rawDateTo)
                    ? $rawDateTo
                    : $today;
            }
        } else {
            // For specific issue types (including failed_contacts), default to all-time
            // unless the user explicitly supplies a range.
            $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $rawDateFrom)
                ? $rawDateFrom
                : null;

            $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $rawDateTo)
                ? $rawDateTo
                : null;
        }

        // Get statistics for dashboard (always needed)
        $stats = $this->notificationService->getIssueStatistics();

        // Get orphaned notifications data (Polaris → LX Starter failures)
        // Allow filtering by days back (default 30)
        $orphanedDays = (int) ($request->input('orphaned_days') ?: 30);
        $orphanedData = $this->notificationService->getOrphanedNotifications($orphanedDays);

        // Failure breakdown charts (failed notifications only)
        $failedByMethod         = $this->notificationService->getFailedNotificationCountsByDeliveryMethod($dateFrom, $dateTo);
        $failedByType           = $this->notificationService->getFailedNotificationCountsByNotificationType($dateFrom, $dateTo);
        $lxStarterFailuresCount = $this->notificationService->countLxStarterFailures($dateFrom, $dateTo);

        // Get SMS opt-out failures
        $smsOptOutData = $this->notificationService->getSmsOptOutFailures(30);

        // Failed contact health – used for prominent CTAs in the issues view as well.
        $failedContactCounts    = $this->notificationService->getFailedContactCountsByChannelForRange($dateFrom, $dateTo);
        $failedContactsSmsVoice = $this->notificationService->getDashboardFailedContacts([3, 8], 5);
        $failedContactsEmail    = $this->notificationService->getDashboardFailedContacts([2], 5);

        // Resolve override-aware labels for SMS/Voice for use in headings
        $lookupLabelService = app(\Dcplibrary\Notices\Services\LookupLabelService::class);
        $deliveryLabels     = [
            'sms'   => $lookupLabelService->getDeliveryLabel('sms'),
            'voice' => $lookupLabelService->getDeliveryLabel('voice'),
        ];

        // If viewing failed contacts summary, get aggregated data
        if ('failed_contacts' === $issueType) {
            $showResolved = (bool) $request->input('show_resolved');

            // For Invalid Contact Information view, the **default** is all time
            // (date_from/date_to are null). When the user explicitly chooses a
            // range via the date picker, we pass it through.
            $failedContactFilters = [
                'search' => $request->input('search'),
            ];

            if ($dateFrom && $dateTo) {
                $failedContactFilters['date_from'] = $dateFrom;
                $failedContactFilters['date_to']   = $dateTo;
            }

            $failedStrings = $this->notificationService->getFailedDeliveryStrings($showResolved, $failedContactFilters);

            // Get lookup data for filters (only enabled items)
            $deliveryOptions = $this->lookupTableService->getEnabledDeliveryOptions();

            return view('notices::notifications.issues', [
                'issues'         => collect(), // Empty collection for issues
                'failed_strings' => $failedStrings,
                'selected_type'  => $issueType,
                'filters'        => [
                    'search'    => $request->input('search'),
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                ],
                'delivery_options'         => $deliveryOptions,
                'sort'                     => 'notification_date',
                'dir'                      => 'desc',
                'stats'                    => $stats,
                'orphaned_data'            => $orphanedData,
                'orphaned_days'            => $orphanedDays,
                'sms_opt_out_data'         => $smsOptOutData,
                'failed_contact_counts'    => $failedContactCounts,
                'failed_contacts_smsvoice' => $failedContactsSmsVoice,
                'failed_contacts_email'    => $failedContactsEmail,
                'delivery_labels'          => $deliveryLabels,
                'failed_by_method'         => $failedByMethod,
                'failed_by_type'           => $failedByType,
                'lx_starter_failures'      => $lxStarterFailuresCount,
            ]);
        }

        // Get search filters
        $filters = $request->only([
            'search',
            'date_from',
            'date_to',
            'delivery_option_id',
            'show_resolved',
        ]);

        $sortBy  = $request->input('sort', 'notification_date');
        $sortDir = $request->input('dir', 'desc');

        // Get individual issues with search and sort
        $issues = $this->notificationService->searchIssues($issueType, $filters, $sortBy, $sortDir);

        // Get lookup data for filters (only enabled items)
        $deliveryOptions = $this->lookupTableService->getEnabledDeliveryOptions();

        return view('notices::notifications.issues', [
            'issues'                   => $issues,
            'failed_strings'           => collect(), // Empty collection for failed strings
            'selected_type'            => $issueType,
            'filters'                  => $filters,
            'delivery_options'         => $deliveryOptions,
            'sort'                     => $sortBy,
            'dir'                      => $sortDir,
            'stats'                    => $stats,
            'orphaned_data'            => $orphanedData,
            'orphaned_days'            => $orphanedDays,
            'sms_opt_out_data'         => $smsOptOutData,
            'failed_contact_counts'    => $failedContactCounts,
            'failed_contacts_smsvoice' => $failedContactsSmsVoice,
            'failed_contacts_email'    => $failedContactsEmail,
            'delivery_labels'          => $deliveryLabels,
            'failed_by_method'         => $failedByMethod,
            'failed_by_type'           => $failedByType,
            'lx_starter_failures'      => $lxStarterFailuresCount,
        ]);
    }

    /**
     * Diagnostic view: clusters of NotificationLog email rows with empty/NULL delivery_string.
     */
    public function emailSystemErrorClusters(Request $request)
    {
        // Default to last 14 days.
        $dateFrom = $request->input('date_from') ?: now()->subDays(14)->toDateString();
        $dateTo   = $request->input('date_to') ?: now()->toDateString();

        // Validate date formats
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateFrom)) {
            $dateFrom = now()->subDays(14)->toDateString();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateTo)) {
            $dateTo = now()->toDateString();
        }

        $clusters = $this->notificationService->getEmailBlankDeliveryStringClusters($dateFrom, $dateTo);

        return view('notices::notifications.email-system-error-clusters', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'clusters'  => $clusters,
        ]);
    }

    /**
     * Display orphaned notifications (Polaris → LX Starter failures).
     */
    public function orphaned(Request $request)
    {
        $daysBack = (int) ($request->input('orphaned_days') ?: 30);
        if ($daysBack < 1) {
            $daysBack = 30;
        }

        $orphanedData = $this->notificationService->getOrphanedNotifications($daysBack);

        return view('notices::notifications.orphaned', [
            'orphaned_data' => $orphanedData,
            'orphaned_days' => $daysBack,
        ]);
    }

    /**
     * Display patrons affected by a specific failed delivery string.
     */
    public function affectedPatrons(int $id)
    {
        $failedString    = \Dcplibrary\Notices\Models\FailedDeliveryString::findOrFail($id);
        $affectedPatrons = $this->notificationService->getAffectedPatrons($id);

        return view('notices::notifications.affected-patrons', [
            'failed_string'    => $failedString,
            'affected_patrons' => $affectedPatrons,
        ]);
    }

    /**
     * Resolve a single delivery issue.
     */
    public function resolveIssue(Request $request, int $id)
    {
        $request->validate([
            'resolution_notes' => 'required|string|max:1000',
        ]);

        $issue = PatronDeliveryIssue::findOrFail($id);

        $this->notificationService->resolveIssue($issue, $request->resolution_notes);

        return redirect()
            ->back()
            ->with('success', 'Issue resolved successfully.')
        ;
    }

    /**
     * API endpoint: Resolve a single delivery issue.
     */
    public function resolveIssueApi(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'resolution_notes' => 'required|string|max:1000',
        ]);

        $issue   = PatronDeliveryIssue::findOrFail($id);
        $updated = $this->notificationService->resolveIssue($issue, $data['resolution_notes']);

        $fresh = $issue->fresh();

        return response()->json([
            'success'     => (bool) $updated,
            'issue_id'    => $issue->id,
            'resolved_at' => $fresh?->resolved_at?->toIso8601String(),
        ]);
    }

    /**
     * Display bulk resolution form.
     */
    public function bulkResolveForm(Request $request)
    {
        $type = $request->input('type'); // 'time_window', 'patron', 'notifications'

        return view('notices::notifications.bulk-resolve', [
            'type' => $type,
        ]);
    }

    /**
     * Process bulk resolution of issues.
     */
    public function bulkResolve(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'             => 'required|in:time_window,patron,notifications',
            'resolution_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput()
            ;
        }

        $count = 0;

        switch ($request->type) {
            case 'time_window':
                $request->validate([
                    'start_time' => 'required|date_format:H:i',
                    'end_time'   => 'required|date_format:H:i',
                    'date'       => 'required|date_format:Y-m-d',
                ]);

                $count = $this->notificationService->bulkResolveByTimeWindow(
                    $request->start_time,
                    $request->end_time,
                    $request->date,
                    $request->resolution_notes
                );

                break;

            case 'patron':
                $request->validate([
                    'patron_barcode' => 'required|string',
                ]);

                $count = $this->notificationService->bulkResolveByPatron(
                    $request->patron_barcode,
                    $request->resolution_notes
                );

                break;

            case 'notifications':
                // Parse notification IDs from JSON string
                $notificationIds = json_decode($request->input('notification_ids', '[]'), true);

                if (empty($notificationIds) || !is_array($notificationIds)) {
                    return redirect()
                        ->back()
                        ->withErrors(['notification_ids' => 'Please provide at least one valid notification ID.'])
                        ->withInput()
                    ;
                }

                $count = $this->notificationService->bulkResolveByNotifications(
                    $notificationIds,
                    $request->resolution_notes
                );

                break;
        }

        return redirect()
            ->route('notifications.issues')
            ->with('success', "Successfully resolved {$count} issue(s).")
        ;
    }

    /**
     * API endpoint: Bulk resolve issues.
     */
    public function bulkResolveApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'                  => 'required|in:time_window,patron,notifications',
            'resolution_notes'      => 'required|string|max:1000',
            'date'                  => 'nullable|date_format:Y-m-d',
            'start_time'            => 'nullable|date_format:H:i',
            'end_time'              => 'nullable|date_format:H:i',
            'patron_barcode'        => 'nullable|string|max:50',
            'notification_ids'      => 'nullable|array',
            'notification_ids.*'    => 'integer',
            'notification_ids_json' => 'nullable|string',
        ]);

        $count = 0;

        switch ($data['type']) {
            case 'time_window':
                Validator::make($data, [
                    'date'       => 'required|date_format:Y-m-d',
                    'start_time' => 'required|date_format:H:i',
                    'end_time'   => 'required|date_format:H:i',
                ])->validate();

                $count = $this->notificationService->bulkResolveByTimeWindow(
                    $data['start_time'],
                    $data['end_time'],
                    $data['date'],
                    $data['resolution_notes']
                );

                break;

            case 'patron':
                Validator::make($data, [
                    'patron_barcode' => 'required|string|max:50',
                ])->validate();

                $count = $this->notificationService->bulkResolveByPatron(
                    $data['patron_barcode'],
                    $data['resolution_notes']
                );

                break;

            case 'notifications':
                $ids = $data['notification_ids'] ?? null;

                if ((!$ids || !is_array($ids)) && !empty($data['notification_ids_json'])) {
                    $decoded = json_decode($data['notification_ids_json'], true);
                    if (is_array($decoded)) {
                        $ids = $decoded;
                    }
                }

                if (empty($ids) || !is_array($ids)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please provide at least one valid notification ID.',
                    ], 422);
                }

                $count = $this->notificationService->bulkResolveByNotifications(
                    $ids,
                    $data['resolution_notes']
                );

                break;
        }

        return response()->json([
            'success'        => true,
            'resolved_count' => (int) $count,
        ]);
    }

    /**
     * Get delivery issue details as JSON for modal display.
     */
    public function showIssue(int $id)
    {
        $issue = PatronDeliveryIssue::with(['notification', 'failedDeliveryString.deliveryOption'])
            ->findOrFail($id)
        ;

        // Extract patron name from notification raw payload if available
        $patronName = null;
        if ($issue->notification && $issue->notification->raw_payload) {
            $raw     = json_decode($issue->notification->raw_payload, true);
            $rawRows = is_array($raw) ? $raw : [];

            $pickFirstNonEmpty = function (array $rows, string $key) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $value = $row[$key] ?? null;
                    if (null !== $value && '' !== $value) {
                        return $value;
                    }
                }

                return null;
            };

            $patronLast  = $pickFirstNonEmpty($rawRows, 'patron_last_name');
            $patronFirst = $pickFirstNonEmpty($rawRows, 'patron_first_name');
            $patronName  = trim(($patronLast ?? '') . ($patronLast && $patronFirst ? ', ' : '') . ($patronFirst ?? ''));
        }

        // Build notification preview if notification exists
        $notificationPreview = null;
        if ($issue->notification) {
            $notificationPreview = [
                'type'        => $issue->notification->notification_type,
                'status'      => $issue->notification->status,
                'exported_at' => $issue->notification->first_exported_at?->format('M j, Y g:i A'),
            ];
        }

        // Build sources information
        $sources = [];

        // If linked to a notification, include export file source
        if ($issue->notification) {
            $sources['export_file'] = [
                'filename'         => $issue->notification->source_filename,
                'export_date'      => $issue->notification->first_exported_at?->format('M j, Y'),
                'batch_identifier' => $issue->notification->import_batch_identifier,
            ];
        }

        // Include detection information
        $sources['detected_during'] = [
            'timestamp'  => $issue->detected_at?->format('M j, Y g:i A'),
            'issue_type' => $issue->issue_type,
        ];

        // If there's a failed delivery string, include it as a source
        if ($issue->failedDeliveryString) {
            $sources['failed_contact'] = [
                'delivery_string' => $issue->failedDeliveryString->delivery_string,
                'delivery_option' => $issue->failedDeliveryString->deliveryOption?->delivery_option,
                'first_detected'  => $issue->failedDeliveryString->created_at?->format('M j, Y g:i A'),
            ];
        }

        return response()->json([
            'id'                     => $issue->id,
            'patron_barcode'         => $issue->patron_barcode,
            'patron_name'            => $patronName ?: null,
            'patron_id'              => $issue->notification?->patron_id,
            'issue_type'             => $issue->issue_type,
            'detected_at'            => $issue->detected_at?->format('M j, Y g:i A'),
            'resolved_at'            => $issue->resolved_at?->format('M j, Y g:i A'),
            'resolved_by_name'       => $issue->resolved_by_name,
            'resolution_notes'       => $issue->resolution_notes,
            'issue_details'          => $issue->issue_details,
            'failed_delivery_string' => $issue->failedDeliveryString?->delivery_string,
            'delivery_method'        => $issue->notification?->deliveryOption?->delivery_option
                                 ?? $issue->failedDeliveryString?->deliveryOption?->delivery_option,
            'notification_id'      => $issue->notification_id,
            'notification_preview' => $notificationPreview,
            'sources'              => $sources,
        ]);
    }

    /**
     * API endpoint: Issues list.
     */
    public function issuesApi(Request $request)
    {
        $issueType      = $request->input('type');
        $perPage        = max(1, min(200, (int) $request->input('per_page', 50)));
        $includeDetails = filter_var($request->input('include_details', false), FILTER_VALIDATE_BOOLEAN);
        $includeTotals  = filter_var($request->input('include_totals', false), FILTER_VALIDATE_BOOLEAN);

        $filters = [
            'search'             => $request->input('search'),
            'date_from'          => $request->input('date_from'),
            'date_to'            => $request->input('date_to'),
            'delivery_option_id' => $request->input('delivery_option_id'),
        ];

        $sortBy  = $request->input('sort', 'detected_at');
        $sortDir = $request->input('dir', 'desc');

        if ('failed_contacts' === $issueType) {
            // Return failed delivery strings (returns Collection, need to paginate manually)
            $allIssues   = $this->notificationService->getFailedDeliveryStrings(false);
            $currentPage = (int) $request->input('page', 1);
            $items       = $allIssues->forPage($currentPage, $perPage);

            // Create manual paginator
            $issues = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $allIssues->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            // Manual paginator in this branch always has totals.
            $includeTotals = true;
        } else {
            // Return patron delivery issues (already paginated)
            $issues = $this->notificationService->searchIssues(
                $issueType,
                $filters,
                $sortBy,
                $sortDir,
                $includeDetails,
                $perPage,
                $includeTotals
            );
            // Override per_page if needed
            if ($issues->perPage() !== $perPage) {
                $currentPage = $issues->currentPage();
                $issues      = $issues->setPath($request->url())->appends($request->query());
            }
        }

        $isLengthAware = $issues instanceof LengthAwarePaginatorContract;

        // Transform items based on type
        $items = $issues->items();
        if ('failed_contacts' === $issueType) {
            $transformedItems = FailedContactResource::collection($items);
        } else {
            $transformedItems = IssueResource::collection($items);
        }

        return response()->json([
            'data' => $transformedItems,
            'meta' => [
                'current_page'    => $issues->currentPage(),
                'last_page'       => $isLengthAware ? $issues->lastPage() : null,
                'per_page'        => $issues->perPage(),
                'total'           => $isLengthAware ? $issues->total() : null,
                'from'            => $issues->firstItem(),
                'to'              => $issues->lastItem(),
                'include_details' => $includeDetails,
                'has_more_pages'  => $issues->hasMorePages(),
                'include_totals'  => $includeTotals,
            ],
            'links' => [
                'first' => $issues->url(1),
                'last'  => $isLengthAware ? $issues->url($issues->lastPage()) : null,
                'prev'  => $issues->previousPageUrl(),
                'next'  => $issues->nextPageUrl(),
            ],
        ]);
    }

    /**
     * API endpoint: Issue statistics.
     */
    public function issueStatisticsApi()
    {
        $stats = $this->notificationService->getIssueStatistics();

        return response()->json($stats);
    }

    /**
     * API endpoint: Orphaned notifications.
     */
    public function orphanedNotificationsApi(Request $request)
    {
        $days = (int) $request->input('days', 30);

        $orphanedData = $this->notificationService->getOrphanedNotifications($days);

        return response()->json($orphanedData);
    }
}
