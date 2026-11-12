<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Http\Requests\Api\ListNotificationsRequest;
use Dcplibrary\Notices\Http\Resources\NotificationResource;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PolarisNotificationHistory;
use Dcplibrary\Notices\Services\LookupTableService;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Support\NotificationActivityRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for notification-focused screens and APIs.
 */
class NotificationsController extends Controller
{
    protected NotificationService $notificationService;
    protected LookupTableService $lookupTableService;

    public function __construct(NotificationService $notificationService, LookupTableService $lookupTableService)
    {
        $this->notificationService = $notificationService;
        $this->lookupTableService  = $lookupTableService;
    }

    /**
     * Display searchable notification list.
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'date_from',
            'date_to',
            'status',
            'notification_type',
            'delivery_option_id',
            'search',
            'hour',
            'orphaned',
            'lx_starter_failure_only',
        ]);

        $sortBy  = $request->input('sort', 'first_exported_at');
        $sortDir = $request->input('dir', 'desc');

        $notifications = $this->notificationService->searchNotifications($filters, $sortBy, $sortDir);

        // Get lookup data for filters (only enabled items)
        $notificationTypes = $this->lookupTableService->getEnabledNotificationTypes();
        $deliveryOptions   = $this->lookupTableService->getEnabledDeliveryOptions();

        return view('notices::notifications.index', [
            'notifications'      => $notifications,
            'filters'            => $filters,
            'notification_types' => $notificationTypes,
            'delivery_options'   => $deliveryOptions,
            'sort'               => $sortBy,
            'dir'                => $sortDir,
        ]);
    }

    /**
     * AJAX search endpoint for admin notifications list.
     */
    public function search(Request $request)
    {
        $filters = $request->only([
            'date_from',
            'date_to',
            'status',
            'notification_type',
            'delivery_option_id',
            'search',
            'lx_starter_failure_only',
        ]);

        $sortBy  = $request->input('sort', 'first_exported_at');
        $sortDir = $request->input('dir', 'desc');
        $perPage = (int) $request->input('per_page', 25);

        $notifications = $this->notificationService->searchNotifications(
            $filters,
            $sortBy,
            $sortDir,
            $perPage,
            true // include totals
        );

        $isLengthAware = $notifications instanceof LengthAwarePaginatorContract;

        // Format notifications for the frontend
        $formattedNotifications = collect($notifications->items())->map(function ($notification) {
            return $this->formatNotificationForList($notification);
        });

        return response()->json([
            'data' => $formattedNotifications,
            'meta' => [
                'current_page'   => $notifications->currentPage(),
                'last_page'      => $isLengthAware ? $notifications->lastPage() : null,
                'per_page'       => $notifications->perPage(),
                'total'          => $isLengthAware ? $notifications->total() : null,
                'from'           => $notifications->firstItem(),
                'to'             => $notifications->lastItem(),
                'has_more_pages' => $notifications->hasMorePages(),
            ],
        ]);
    }

    /**
     * Display notification history for a specific patron.
     */
    public function patron(string $barcode)
    {
        $notifications = $this->notificationService->getPatronNotificationHistory($barcode);

        return view('notices::notifications.patron', [
            'barcode'       => $barcode,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Get notification details as JSON for modal display.
     */
    public function show(int $id, NotificationActivityRepository $activityRepository)
    {
        $notification = Notification::with(['deliveryOption', 'importBatch', 'activeIssues'])
            ->findOrFail($id)
        ;

        // Extract patron name and item info from raw payload
        $raw     = $notification->raw_payload ? json_decode($notification->raw_payload, true) : null;
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

        $patronEmail = $pickFirstNonEmpty($rawRows, 'patron_email');

        // Fallback: derive patron info from delivery sources when raw_payload is missing it
        if (!$patronName || !$patronEmail) {
            $patronFromSources = \Dcplibrary\Notices\Support\DeliverySourceExtractor::extractPatronFromSources($notification);

            if ($patronFromSources) {
                if (!$patronName && !empty($patronFromSources['full_name'])) {
                    $patronName = $patronFromSources['full_name'];
                }

                if (!$patronEmail && !empty($patronFromSources['email'])) {
                    $patronEmail = $patronFromSources['email'];
                }
            }
        }

        // Look up matching Polaris NotificationHistory row for mail/email notifications
        $notificationHistory = null;
        if (in_array($notification->delivery_option_id, [1, 2], true)) {
            $historyQuery = PolarisNotificationHistory::query()
                ->where('patron_id', $notification->patron_id)
                ->where('notification_type_id', $notification->notification_type_id)
                ->where('delivery_option_id', $notification->delivery_option_id)
            ;

            if ($notification->first_exported_at) {
                $dayStart = CarbonImmutable::parse($notification->first_exported_at)->startOfDay();
                $historyQuery->where('notice_date', '>=', $dayStart)
                    ->where('notice_date', '<', $dayStart->addDay())
                ;
            }

            $notificationHistory = $historyQuery
                ->orderByDesc('notice_date')
                ->orderByDesc('id')
                ->first()
            ;
        }

        // Get formatted notice sent date (first_exported_at or generated_at)
        $noticeSentDate = $notification->first_exported_at ?? $notification->generated_at;

        // Extract source filename from raw payload
        $sourceFilename = $pickFirstNonEmpty($rawRows, 'source_filename');

        // Derive item title and amount, falling back to NotificationHistory when needed
        $itemTitle = $pickFirstNonEmpty($rawRows, 'title');
        if (!$itemTitle && $notificationHistory) {
            $itemTitle = $notificationHistory->title;
        }

        $amount = $notificationHistory?->amount;

        $notificationHistoryPayload = null;
        $notificationHistoryUrl     = null;

        if ($notificationHistory) {
            $notificationHistoryPayload = [
                'notice_date'        => $notificationHistory->notice_date?->format('M j, Y g:i A'),
                'amount'             => $notificationHistory->amount,
                'status_id'          => $notificationHistory->notification_status_id,
                'txn_id'             => $notificationHistory->txn_id,
                'delivery_option_id' => $notificationHistory->delivery_option_id,
            ];

            // Link to the NotificationHistory data viewer for the relevant date/channel
            $notificationHistoryUrl = route('notices.data.notification-history', array_filter([
                'date'    => $notification->first_exported_at?->toDateString(),
                'channel' => 2 === $notification->delivery_option_id ? 'email' : (1 === $notification->delivery_option_id ? 'mail' : null),
            ]));
        }

        // Format active issues
        $activeIssues = $notification->activeIssues->map(function ($issue) {
            return [
                'id'            => $issue->id,
                'issue_type'    => $issue->issue_type,
                'issue_details' => $issue->issue_details,
                'detected_at'   => $issue->detected_at?->format('M j, Y g:i A'),
            ];
        });

        // Get delivery confirmation sources (for email notifications)
        $sources = \Dcplibrary\Notices\Support\DeliverySourceExtractor::extractSources($notification);

        // Derived timeline built from core notification fields + sources
        $timelineEvents = \Dcplibrary\Notices\Support\DeliverySourceExtractor::buildActivityLog($notification, $sources);

        // DB-backed activity log entries (import pipeline, reconciliations, etc.)
        $dbActivity = $activityRepository->forNotification($notification);

        // Combine timeline + DB events into a single activity_log array for the UI
        $activityLog = array_merge($timelineEvents, $dbActivity);

        return response()->json([
            'id'                       => $notification->id,
            'patron_id'                => $notification->patron_id,
            'patron_barcode'           => $notification->patron_barcode,
            'patron_name'              => $patronName ?: null,
            'patron_first_name'        => $patronFirst,
            'patron_last_name'         => $patronLast,
            'patron_email'             => $patronEmail,
            'notification_type'        => $notification->notification_type,
            'delivery_option'          => $notification->deliveryOption,
            'status'                   => $notification->status,
            'failure_reason'           => $notification->failure_reason_formatted,
            'item_title'               => $itemTitle,
            'item_barcode'             => $pickFirstNonEmpty($rawRows, 'item_barcode'),
            'item_record_id'           => $pickFirstNonEmpty($rawRows, 'item_record_id'),
            'amount'                   => $amount,
            'notice_sent_date'         => $noticeSentDate?->format('M j, Y'),
            'generated_at'             => $notification->generated_at?->format('M j, Y g:i A'),
            'first_exported_at'        => $notification->first_exported_at?->format('M j, Y g:i A'),
            'last_exported_at'         => $notification->last_exported_at?->format('M j, Y g:i A'),
            'delivered_at'             => $notification->delivered_at?->format('M j, Y g:i A'),
            'failed_at'                => $notification->failed_at?->format('M j, Y g:i A'),
            'is_combined'              => $notification->is_combined,
            'source_filename'          => $sourceFilename,
            'import_batch_identifier'  => $notification->importBatch?->identifier,
            'raw_payload'              => $raw,
            'active_issues'            => $activeIssues,
            'sources'                  => $sources,
            'notification_history'     => $notificationHistoryPayload,
            'notification_history_url' => $notificationHistoryUrl,
            'activity_log'             => $activityLog,
        ]);
    }

    /**
     * API endpoint: Notifications list.
     */
    public function notificationsApi(ListNotificationsRequest $request)
    {
        $filters = [
            'search'                  => $request->input('search'),
            'date_from'               => $request->input('date_from'),
            'date_to'                 => $request->input('date_to'),
            'status'                  => $request->input('status'),
            'notification_type'       => $request->input('notification_type'),
            'delivery_option_id'      => $request->input('delivery_option_id'),
            'patron_barcode'          => $request->input('patron_barcode'),
            'orphaned'                => $request->input('orphaned'),
            'lx_starter_failure_only' => $request->input('lx_starter_failure_only'),
        ];

        $sortBy        = $request->input('sort', 'first_exported_at');
        $sortDir       = $request->input('dir', 'desc');
        $perPage       = (int) $request->input('per_page', 50);
        $includeTotals = filter_var($request->input('include_totals', true), FILTER_VALIDATE_BOOLEAN);

        $notifications = $this->notificationService->searchNotifications(
            $filters,
            $sortBy,
            $sortDir,
            $perPage,
            $includeTotals
        );
        $isLengthAware = $notifications instanceof LengthAwarePaginatorContract;

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page'   => $notifications->currentPage(),
                'last_page'      => $isLengthAware ? $notifications->lastPage() : null,
                'per_page'       => $notifications->perPage(),
                'total'          => $isLengthAware ? $notifications->total() : null,
                'from'           => $notifications->firstItem(),
                'to'             => $notifications->lastItem(),
                'has_more_pages' => $notifications->hasMorePages(),
                'include_totals' => $includeTotals,
            ],
            'links' => [
                'first' => $notifications->url(1),
                'last'  => $isLengthAware ? $notifications->url($notifications->lastPage()) : null,
                'prev'  => $notifications->previousPageUrl(),
                'next'  => $notifications->nextPageUrl(),
            ],
        ]);
    }

    /**
     * API endpoint: Patron notification history.
     */
    public function patronApi(string $barcode)
    {
        $notifications = $this->notificationService->getPatronNotificationHistory($barcode);

        return response()->json([
            'patron_barcode' => $barcode,
            'notifications'  => NotificationResource::collection($notifications),
        ]);
    }

    /**
     * Format a notification for the list view JSON response.
     */
    protected function formatNotificationForList(Notification $notification): array
    {
        $raw     = $notification->raw_payload ? json_decode($notification->raw_payload, true) : null;
        $rawRows = is_array($raw) ? $raw : [];

        $pickFirst = function (array $rows, string $key) {
            foreach ($rows as $row) {
                if (is_array($row) && !empty($row[$key])) {
                    return $row[$key];
                }
            }

            return null;
        };

        // Build patron name from display fields first, then raw payload
        $patronName = null;
        $firstName  = $notification->display_patron_first_name;
        $lastName   = $notification->display_patron_last_name;
        if ($firstName || $lastName) {
            $patronName = trim(($lastName ?? '') . ($lastName && $firstName ? ', ' : '') . ($firstName ?? ''));
        }
        if (!$patronName) {
            $first = $pickFirst($rawRows, 'patron_first_name');
            $last  = $pickFirst($rawRows, 'patron_last_name');
            if ($first || $last) {
                $patronName = trim(($last ?? '') . ($last && $first ? ', ' : '') . ($first ?? ''));
            }
        }

        // Get item info
        $itemTitle   = $notification->display_title ?: $pickFirst($rawRows, 'title');
        $itemBarcode = $notification->display_item_barcode ?: $pickFirst($rawRows, 'item_barcode');

        // Get delivery option info
        $deliveryOption = $notification->deliveryOption;

        return [
            'id'                    => $notification->id,
            'patron_barcode'        => $notification->patron_barcode,
            'patron_name'           => $patronName,
            'item_title'            => $itemTitle,
            'item_barcode'          => $itemBarcode,
            'notification_type'     => $notification->notification_type,
            'delivery_option_id'    => $notification->delivery_option_id,
            'delivery_option_label' => $deliveryOption?->display_label ?? 'Unknown',
            'delivery_option_icon'  => $deliveryOption?->icon_name     ?? 'unknown',
            'status'                => $notification->status,
            'failure_reason'        => $notification->failure_reason,
            'active_issues_count'   => $notification->active_issues_count ?? 0,
            'sent_date'             => $notification->first_exported_at?->format('M j, Y'),
            'sent_time'             => $notification->first_exported_at?->format('g:i A'),
            'sent_timestamp'        => $notification->first_exported_at?->timestamp,
        ];
    }
}
