<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PolarisNotificationHistory;
use Dcplibrary\Notices\Models\StatusMessage;
use Dcplibrary\Notices\Services\LookupTableService;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Support\NotificationActivityRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for the simplified user portal notification status viewer.
 * Accessible to users with role='user' (and admins for testing/preview).
 */
class UserPortalController extends Controller
{
    protected NotificationService $notificationService;
    protected LookupTableService $lookupTableService;

    public function __construct(NotificationService $notificationService, LookupTableService $lookupTableService)
    {
        $this->notificationService = $notificationService;
        $this->lookupTableService  = $lookupTableService;
    }

    /**
     * Display the user portal notification status viewer.
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'date_from',
            'date_to',
            'search',
            'status',              // Single status: delivered, failed, pending
            'notification_type',   // Single notification type ID
            'delivery_option_id',  // Single delivery option ID
        ]);

        [$filters, $initialQuickRange] = $this->normalizeInitialFilters($filters);

        // Convert filter arrays to query parameters
        $queryFilters = $this->buildQueryFilters($filters);

        // For initial page load, fetch notifications server-side
        // Subsequent searches will use the API endpoint for better UX
        $notifications = $this->notificationService->searchNotifications(
            $queryFilters,
            'first_exported_at',
            'desc',
            25
        );

        // Get lookup data for filter pills
        $notificationTypes = $this->lookupTableService->getEnabledNotificationTypes();
        $deliveryOptions   = $this->lookupTableService->getEnabledDeliveryOptions();

        // Pre-format notifications to avoid complex inline PHP in the view
        $formattedNotifications = collect($notifications->items())->map(function ($notification) {
            return $this->formatNotificationForList($notification);
        });

        return view('notices::user-portal.index', [
            'notifications'           => $notifications,
            'formatted_notifications' => $formattedNotifications,
            'filters'                 => $filters,
            'initial_quick_range'     => $initialQuickRange,
            'notification_types'      => $notificationTypes,
            'delivery_options'        => $deliveryOptions,
        ]);
    }

    /**
     * API endpoint for async notification search (with loading indicator support).
     */
    public function search(Request $request)
    {
        $filters = $request->only([
            'date_from',
            'date_to',
            'search',
            'status',
            'notification_type',
            'delivery_option_id',
        ]);

        $queryFilters = $this->buildQueryFilters($filters);

        $perPage = (int) $request->input('per_page', 25);
        $page    = (int) $request->input('page', 1);

        $notifications = $this->notificationService->searchNotifications(
            $queryFilters,
            'first_exported_at',
            'desc',
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
     * Get notification details for the detail modal.
     */
    public function show(int $id, NotificationActivityRepository $activityRepository)
    {
        $notification = Notification::with(['deliveryOption', 'activeIssues'])
            ->findOrFail($id)
        ;

        // Extract data from raw payload
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

        // Build patron name: display fields first (original case), then raw payload, then sources
        $patronName = $this->buildPatronName(
            $notification->display_patron_first_name,
            $notification->display_patron_last_name
        );

        if (!$patronName) {
            $patronName = $this->buildPatronName(
                $pickFirstNonEmpty($rawRows, 'patron_first_name'),
                $pickFirstNonEmpty($rawRows, 'patron_last_name')
            );
        }

        if (!$patronName) {
            $patronFromSources = \Dcplibrary\Notices\Support\DeliverySourceExtractor::extractPatronFromSources($notification);
            if ($patronFromSources && !empty($patronFromSources['full_name'])) {
                $patronName = $patronFromSources['full_name'];
            }
        }

        // Get item info: display fields first (original case), then raw payload
        $itemTitle   = $notification->display_title ?: $pickFirstNonEmpty($rawRows, 'title');
        $itemBarcode = $notification->display_item_barcode ?: $pickFirstNonEmpty($rawRows, 'item_barcode');

        // Fallback: NotificationLog-derived payloads often don't include item/title.
        // For mail/email, the corresponding Polaris NotificationHistory row can provide title.
        if (
            !$itemTitle
            && $notification->first_exported_at
            && in_array($notification->delivery_option_id, [1, 2], true)
        ) {
            $dayStart = CarbonImmutable::parse($notification->first_exported_at)->startOfDay();

            $history = PolarisNotificationHistory::query()
                ->where('patron_id', $notification->patron_id)
                ->where('notification_type_id', $notification->notification_type_id)
                ->where('delivery_option_id', $notification->delivery_option_id)
                ->where('notice_date', '>=', $dayStart)
                ->where('notice_date', '<', $dayStart->addDay())
                ->orderByDesc('notice_date')
                ->orderByDesc('id')
                ->first()
            ;

            if ($history?->title) {
                $itemTitle = $history->title;
            }
        }

        // Get contact info
        $contactInfo = $this->extractContactInfo($notification, $rawRows);

        // Get delivery option from database (uses display_label which respects overrides)
        $deliveryOption = $notification->deliveryOption;
        $channel        = $deliveryOption?->display_label ?? 'Unknown';
        $iconName       = $deliveryOption?->icon_name     ?? 'unknown';

        // Resolve status message from database
        $statusMessageData = $this->resolveStatusMessage($notification, $iconName);

        return response()->json([
            'id'                => $notification->id,
            'patron_name'       => $patronName,
            'patron_barcode'    => $notification->patron_barcode,
            'patron_id'         => $notification->patron_id,
            'item_title'        => $itemTitle,
            'item_barcode'      => $itemBarcode,
            'notification_type' => $notification->notification_type,
            'channel'           => $channel,
            'delivery_option'   => $deliveryOption,
            'status'            => $notification->status,
            'failure_reason'    => $notification->failure_reason_formatted,
            'contact_info'      => $contactInfo,
            'delivered_at'      => $notification->delivered_at?->format('F j, Y'),
            'failed_at'         => $notification->failed_at?->format('F j, Y'),
            'first_exported_at' => $notification->first_exported_at?->format('F j, Y'),
            'status_message'    => $statusMessageData,
            'active_issues'     => $notification->activeIssues->map(fn ($issue) => [
                'issue_type'    => $issue->issue_type,
                'issue_details' => $issue->issue_details,
            ]),
        ]);
    }

    /**
     * Convert frontend filter format to backend query filters.
     */
    protected function buildQueryFilters(array $filters): array
    {
        $queryFilters = [];

        if (!empty($filters['date_from'])) {
            $queryFilters['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $queryFilters['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $queryFilters['search'] = $filters['search'];
        }

        // Status filter (single value)
        if (!empty($filters['status'])) {
            $queryFilters['status'] = $filters['status'];
        }

        // Notification type filter (by ID)
        if (!empty($filters['notification_type'])) {
            $queryFilters['notification_type'] = (int) $filters['notification_type'];
        }

        // Delivery option filter (by ID)
        if (!empty($filters['delivery_option_id'])) {
            $queryFilters['delivery_option_id'] = (int) $filters['delivery_option_id'];
        }

        return $queryFilters;
    }

    /**
     * Format a notification for the list view.
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

        // Build patron name from display fields first (original case), then raw payload, then sources
        $patronName = $this->buildPatronName(
            $notification->display_patron_first_name,
            $notification->display_patron_last_name
        );

        if (!$patronName) {
            $patronName = $this->buildPatronName(
                $pickFirst($rawRows, 'patron_first_name'),
                $pickFirst($rawRows, 'patron_last_name')
            );
        }

        if (!$patronName) {
            $fromSources = \Dcplibrary\Notices\Support\DeliverySourceExtractor::extractPatronFromSources($notification);
            if ($fromSources && !empty($fromSources['full_name'])) {
                $patronName = $fromSources['full_name'];
            }
        }

        // Get item info from display fields first (original case), then raw payload
        $itemTitle   = $notification->display_title ?: $pickFirst($rawRows, 'title');
        $itemBarcode = $notification->display_item_barcode ?: $pickFirst($rawRows, 'item_barcode');

        // Get contact info: indexed fields first, then delivery_string from raw payload
        $contactInfo = $this->extractContactInfo($notification, $rawRows);

        // Load delivery option from database
        $deliveryOption = $notification->deliveryOption;

        return [
            'id'                    => $notification->id,
            'sent_date'             => $notification->first_exported_at?->format('M j, Y'),
            'sent_time'             => $notification->first_exported_at?->format('g:i A'),
            'sent_timestamp'        => $notification->first_exported_at?->timestamp,
            'patron_name'           => $patronName,
            'patron_barcode'        => $notification->patron_barcode,
            'item_title'            => $itemTitle,
            'item_barcode'          => $itemBarcode,
            'notification_type'     => $notification->notification_type,
            'delivery_option_id'    => $notification->delivery_option_id,
            'delivery_option_label' => $deliveryOption?->display_label,
            'delivery_option_name'  => $deliveryOption?->delivery_option,
            'delivery_option_icon'  => $deliveryOption?->icon_name ?? 'unknown',
            'status'                => $notification->status,
            'contact_info'          => $contactInfo,
            'failure_reason'        => $notification->failure_reason_formatted,
        ];
    }

    /**
     * Build patron name from first and last name.
     */
    protected function buildPatronName(?string $firstName, ?string $lastName): ?string
    {
        $name = trim(($lastName ?? '') . ($lastName && $firstName ? ', ' : '') . ($firstName ?? ''));

        return $name ?: null;
    }

    /**
     * Extract contact info from notification.
     * Uses delivery option name from database to determine type (not hardcoded IDs).
     */
    protected function extractContactInfo(Notification $notification, array $rawRows): ?string
    {
        $deliveryOption = $notification->deliveryOption;
        $iconName       = $deliveryOption?->icon_name ?? 'unknown';

        // Try indexed fields first based on delivery type
        if ('email' === $iconName && $notification->indexed_patron_email) {
            return $notification->indexed_patron_email;
        }
        if (in_array($iconName, ['phone', 'sms'], true) && $notification->indexed_patron_phone) {
            return $notification->indexed_patron_phone;
        }

        // Try delivery_string from raw payload (this is the actual delivery address used)
        foreach ($rawRows as $row) {
            if (is_array($row) && !empty($row['delivery_string'])) {
                return $row['delivery_string'];
            }
        }

        // Fallback: try patron_email or patron_phone from raw payload based on type
        if ('email' === $iconName) {
            foreach ($rawRows as $row) {
                if (is_array($row) && !empty($row['patron_email'])) {
                    return $row['patron_email'];
                }
            }
        }
        if (in_array($iconName, ['phone', 'sms'], true)) {
            foreach ($rawRows as $row) {
                if (is_array($row) && !empty($row['patron_phone'])) {
                    return $row['patron_phone'];
                }
            }
        }

        return null;
    }

    /**
     * Resolve status message from database for any notification status.
     *
     * Maps the notification's status and delivery channel (and failure source for failed)
     * to a status_messages record and returns the message + action for display.
     *
     * @param string $iconName The delivery option icon name (email, sms, phone, mail)
     *
     * @return null|array Array with code, label, message, action, status
     */
    protected function resolveStatusMessage(Notification $notification, string $iconName): ?array
    {
        $status     = $notification->status;
        $statusCode = null;

        if ('failed' === $status) {
            // For failed, resolve the specific failure code
            $statusCode = $this->resolveFailureCode($notification, $iconName);
        } else {
            // For delivered/pending, build the code from status + channel
            $channelSuffix = match ($iconName) {
                'email' => 'email',
                'phone' => 'voice',
                'sms'   => 'sms',
                'mail'  => 'mail',
                default => 'email',
            };
            $statusCode = "{$status}_{$channelSuffix}";
        }

        // Look up the status message from database
        $statusMessage = StatusMessage::where('code', $statusCode)->first();

        if (!$statusMessage) {
            // Fallback to a generic message if code not found
            $fallbackLabel = match ($status) {
                'delivered' => 'Delivered',
                'pending'   => 'Pending',
                'failed'    => 'Delivery Failure',
                default     => 'Unknown Status',
            };
            $fallbackMessage = match ($status) {
                'delivered' => '<p>This notification was successfully delivered.</p>',
                'pending'   => '<p>This notification is being processed.</p>',
                'failed'    => '<p>This notification could not be delivered.</p>',
                default     => '<p>Status unknown.</p>',
            };

            return [
                'code'    => $statusCode,
                'label'   => $fallbackLabel,
                'message' => $fallbackMessage,
                'action'  => 'failed' === $status
                    ? '<p>Please verify the patron\'s contact information in Polaris and select an appropriate notification preference.</p>'
                    : null,
                'status' => $status,
            ];
        }

        return [
            'code'    => $statusMessage->code,
            'label'   => $statusMessage->label,
            'message' => $statusMessage->message,
            'action'  => $statusMessage->action,
            'status'  => $statusMessage->status,
        ];
    }

    /**
     * Resolve the specific failure code for a failed notification.
     */
    protected function resolveFailureCode(Notification $notification, string $iconName): string
    {
        $activeIssues = $notification->activeIssues ?? collect();
        $failureCode  = null;

        if ($activeIssues->isNotEmpty()) {
            $issue = $activeIssues->first();

            // For failed_delivery_string issues, get the actual failure_source
            if ('failed_delivery_string' === $issue->issue_type && $issue->failed_delivery_string_id) {
                $issue->load('failedDeliveryString');
                $failureSource      = $issue->failedDeliveryString?->failure_source;
                $confirmationSource = $issue->failedDeliveryString?->confirmation_source;

                if ($failureSource || $confirmationSource) {
                    $failureCode = $this->mapSourceToFailureCode($failureSource, $confirmationSource, $iconName);
                }
            } else {
                // Direct issue types map to failure codes
                $failureCode = $this->mapIssueTypeToFailureCode($issue->issue_type);
            }
        }

        // Check failure_reason for known system error patterns
        if (!$failureCode && $notification->failure_reason) {
            $failureReason = $notification->failure_reason;

            if (str_contains($failureReason, 'Polaris/LX Starter error') || str_contains($failureReason, 'delivery_string is empty')) {
                $failureCode = 'polaris_export_failure';
            } elseif (str_contains($failureReason, 'LX Starter communication error')) {
                $failureCode = 'lx_starter_failure';
            } elseif (str_contains($failureReason, 'notification_status_id remained NULL')) {
                $failureCode = 'polaris_export_failure';
            }
        }

        // Fallback based on delivery method
        if (!$failureCode) {
            $failureCode = match ($iconName) {
                'email' => 'email_delivery_failure',
                'sms'   => 'phone_unreachable',
                'phone' => 'voice_failure',
                default => 'email_delivery_failure',
            };
        }

        return $failureCode;
    }

    /**
     * Map failure_source and confirmation_source to a failure_reasons code.
     */
    protected function mapSourceToFailureCode(?string $failureSource, ?string $confirmationSource, string $iconName): ?string
    {
        // Map confirmation_source values from Shoutbomb
        if ($confirmationSource) {
            $confirmationMappings = [
                'opted-out'                   => 'opted_out',
                'invalid'                     => 'phone_unreachable',
                'Voice notices not delivered' => 'voice_failure',
                'Calls that generated errors' => 'call_not_completed',
            ];

            if (isset($confirmationMappings[$confirmationSource])) {
                return $confirmationMappings[$confirmationSource];
            }
        }

        // Map failure_source values
        if ($failureSource) {
            $failureSourceMappings = [
                // Shoutbomb sources
                'shoutbomb_opted_out'     => 'opted_out',
                'shoutbomb_invalid_phone' => 'phone_unreachable',
                'shoutbomb_voice_failure' => 'voice_failure',
                'shoutbomb_call_error'    => 'call_not_completed',
                'shoutbomb_mismatched'    => 'mismatched_preference',

                // Email sources
                'email_invalid' => 'email_invalid_address',
                'email_bounce'  => 'email_delivery_failure',
                'email_failed'  => 'email_delivery_failure',

                // System sources
                'null_preference'        => 'null_preference',
                'polaris_export_failure' => 'polaris_export_failure',
                'lx_starter_failure'     => 'lx_starter_failure',
            ];

            if (isset($failureSourceMappings[$failureSource])) {
                return $failureSourceMappings[$failureSource];
            }
        }

        return null;
    }

    /**
     * Map issue_type to a failure_reasons code.
     */
    protected function mapIssueTypeToFailureCode(string $issueType): ?string
    {
        $mappings = [
            'null_preference'        => 'null_preference',
            'mismatched_preference'  => 'mismatched_preference',
            'polaris_export_failure' => 'polaris_export_failure',
            'lx_starter_failure'     => 'lx_starter_failure',
            'email_invalid'          => 'email_invalid_address',
            'email_failed'           => 'email_delivery_failure',
        ];

        return $mappings[$issueType] ?? null;
    }

    /**
     * Apply the same default filter behavior the frontend assumes.
     *
     * The viewer UI defaults to "Yesterday" when no date range is specified, so the initial
     * server-rendered payload must also be scoped to yesterday to avoid showing a mixed
     * dataset (and confusing "pending" statuses for out-of-range notifications).
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function normalizeInitialFilters(array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;
        $search   = $filters['search']    ?? null;

        if ((!$dateFrom || !$dateTo) && $search) {
            $filters['date_from'] = '';
            $filters['date_to']   = '';

            return [$filters, 'all'];
        }

        if (!$dateFrom && !$dateTo) {
            $yesterday = CarbonImmutable::now()->subDay()->toDateString();

            $filters['date_from'] = $yesterday;
            $filters['date_to']   = $yesterday;

            return [$filters, 'yesterday'];
        }

        return [$filters, $this->inferQuickRange($dateFrom, $dateTo)];
    }

    protected function inferQuickRange(?string $dateFrom, ?string $dateTo): string
    {
        if (!$dateFrom && !$dateTo) {
            return 'all';
        }

        $today     = CarbonImmutable::now()->toDateString();
        $yesterday = CarbonImmutable::now()->subDay()->toDateString();

        if ($dateFrom === $today && $dateTo === $today) {
            return 'today';
        }

        if ($dateFrom === $yesterday && $dateTo === $yesterday) {
            return 'yesterday';
        }

        return 'custom';
    }
}
