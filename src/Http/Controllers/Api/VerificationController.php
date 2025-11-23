<?php

namespace Dcplibrary\Notices\Http\Controllers\Api;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Carbon\Carbon;

/**
 * API Controller for notice verification.
 *
 * Provides endpoints to verify notice delivery status.
 */
class VerificationController extends Controller
{
    public function __construct(
        protected NoticeVerificationService $verificationService
    ) {}

    /**
     * Verify notices by various criteria.
     *
     * GET /api/notices/verify?patron_barcode=X&date_from=Y&date_to=Z
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'patron_barcode' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|string',
            'item_barcode' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = NotificationLog::query();

        // Apply filters
        if ($request->has('patron_barcode')) {
            $query->where('patron_barcode', $request->patron_barcode);
        }

        if ($request->has('phone')) {
            $query->where('phone', $request->phone);
        }

        if ($request->has('email')) {
            $query->where('email', $request->email);
        }

        if ($request->has('item_barcode')) {
            $query->where('item_barcode', $request->item_barcode);
        }

        // Date range
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(30);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();
        $query->dateRange($dateFrom, $dateTo);

        // Limit results
        $notices = $query->orderBy('notification_date', 'desc')
            ->limit(100)
            ->get();

        // Verify each notice
        $results = $notices->map(function($notice) {
            $verification = $this->verificationService->verify($notice);

            return [
                'id' => $notice->id,
                'date' => $notice->notification_date->toISOString(),
                'patron' => [
                    'barcode' => $notice->patron_barcode,
                    'name' => $notice->patron_name,
                ],
                'contact' => [
                    'method' => $this->getDeliveryMethodName($notice->delivery_option_id),
                    'value' => $this->getContactValue($notice),
                ],
                'item' => [
                    'barcode' => $notice->item_barcode,
                    'title' => $notice->title,
                ],
                'notice_type' => $this->getNoticeTypeName($notice->notification_type_id),
                'verification' => $verification->toArray(),
                'status_message' => $verification->getStatusMessage(),
            ];
        });

        return response()->json([
            'notices' => $results,
            'summary' => [
                'total' => $results->count(),
                'verified' => $results->where('verification.verification.delivered', true)->count(),
                'failed' => $results->where('verification.verification.overall_status', 'failed')->count(),
                'pending' => $results->where('verification.verification.overall_status', 'pending')->count(),
            ],
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        ]);
    }

    /**
     * Get verification timeline for a specific notice.
     *
     * GET /api/notices/{id}/timeline
     */
    public function timeline(int $id): JsonResponse
    {
        $notice = NotificationLog::findOrFail($id);
        $verification = $this->verificationService->verify($notice);

        return response()->json([
            'notice_id' => $notice->id,
            'patron_barcode' => $notice->patron_barcode,
            'notice_date' => $notice->notification_date->toISOString(),
            // Backwards-compatible wrapper expected by some API tests
            'notice' => [
                'id' => $notice->id,
                'patron_barcode' => $notice->patron_barcode,
                'notification_date' => $notice->notification_date->toISOString(),
            ],
            'verification' => $verification->toArray(),
            'status_message' => $verification->getStatusMessage(),
        ]);
    }

    /**
     * Get verification history for a patron.
     *
     * GET /api/notices/patron/{barcode}
     */
    public function patron(Request $request, string $barcode): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|integer',
        ]);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : null;
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : null;

        $results = $this->verificationService->verifyByPatron($barcode, $dateFrom, $dateTo);

        // Calculate statistics
        $totalNotices = count($results);
        $successfulCount = collect($results)->filter(function($item) {
            return $item['verification']->overall_status === 'success';
        })->count();

        $byType = collect($results)->groupBy(function($item) {
            return $this->getNoticeTypeName($item['notice']->notification_type_id);
        })->map(function($group) {
            return $group->count();
        });

        $byMethod = collect($results)->groupBy(function($item) {
            return $this->getDeliveryMethodName($item['notice']->delivery_option_id);
        })->map(function($group) {
            return $group->count();
        });

        return response()->json([
            'patron' => [
                'barcode' => $barcode,
                'total_notices' => $totalNotices,
                'success_rate' => $totalNotices > 0 ? round(($successfulCount / $totalNotices) * 100, 1) : 0,
                'last_notice' => $totalNotices > 0 ? $results[0]['notice']->notification_date->toISOString() : null,
            ],
            'notices' => collect($results)->map(function($item) {
                return [
                    'id' => $item['notice']->id,
                    'date' => $item['notice']->notification_date->toISOString(),
                    'type' => $this->getNoticeTypeName($item['notice']->notification_type_id),
                    'method' => $this->getDeliveryMethodName($item['notice']->delivery_option_id),
                    'status' => $item['verification']->overall_status,
                    'status_message' => $item['verification']->getStatusMessage(),
                ];
            }),
'statistics' => [
                'by_type' => $byType,
                'by_method' => $byMethod,
            ],
            // Backwards-compatible key expected by some tests
            'data' => collect($results)->map(function($item) {
                return [
                    'id' => $item['notice']->id,
                    'date' => $item['notice']->notification_date->toISOString(),
                    'type' => $this->getNoticeTypeName($item['notice']->notification_type_id),
                    'method' => $this->getDeliveryMethodName($item['notice']->delivery_option_id),
                    'status' => $item['verification']->overall_status,
                ];
            }),
        ]);
    }

    /**
     * Get failed notices.
     *
     * GET /api/notices/failures
     */
    public function failures(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'reason' => 'nullable|string',
        ]);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(7);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();
        $reason = $request->reason;

        $failures = $this->verificationService->getFailedNotices($dateFrom, $dateTo, $reason);

        $byReason = collect($failures)->groupBy('failure_reason')->map(function($group) {
            return $group->count();
        })->sortDesc();

        return response()->json([
            'failures' => $failures,
            'summary' => [
                'total_failed' => count($failures),
                'by_reason' => $byReason,
                'date_range' => [
                    'from' => $dateFrom->toDateString(),
                    'to' => $dateTo->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Search notices with verification status.
     *
     * GET /api/notices/search
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string',
            'type' => 'nullable|integer',
            'status' => 'nullable|in:success,failed,pending,partial',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $query = NotificationLog::query();

        // Search query
        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('patron_barcode', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('item_barcode', 'LIKE', "%{$search}%");
            });
        }

        // Type filter
        if ($request->has('type')) {
            $query->ofType((int) $request->type);
        }

        // Date range
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(30);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();
        $query->dateRange($dateFrom, $dateTo);

        // Pagination
        $limit = (int) $request->input('limit', 50);
        $offset = (int) $request->input('offset', 0);

        $total = $query->count();
        $notices = $query->orderBy('notification_date', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        // Verify and filter by status if requested
        $results = $notices->map(function($notice) {
            $verification = $this->verificationService->verify($notice);
            return [
                'notice' => $notice,
                'verification' => $verification,
            ];
        });

        if ($request->has('status')) {
            $results = $results->filter(function($item) use ($request) {
                return $item['verification']->overall_status === $request->status;
            })->values();
        }

        $payload = $results->map(function($item) {
            return [
                'id' => $item['notice']->id,
                'date' => $item['notice']->notification_date->toISOString(),
                'patron_barcode' => $item['notice']->patron_barcode,
                'type' => $this->getNoticeTypeName($item['notice']->notification_type_id),
                'method' => $this->getDeliveryMethodName($item['notice']->delivery_option_id),
                'status' => $item['verification']->overall_status,
                'status_message' => $item['verification']->getStatusMessage(),
            ];
        });

        return response()->json([
            // Primary key used by newer API clients
            'notices' => $payload,
            // Backwards-compatible key expected by some tests/clients
            'data' => $payload,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'showing' => $results->count(),
            ],
        ]);
    }

    /**
     * Helper: Get delivery method name.
     */
    protected function getDeliveryMethodName(int $id): string
    {
        $methods = config('notices.delivery_options', []);
        return $methods[$id] ?? "Unknown";
    }

    /**
     * Helper: Get notice type name.
     */
    protected function getNoticeTypeName(int $id): string
    {
        $types = config('notices.notification_types', []);
        return $types[$id] ?? "Unknown";
    }

    /**
     * Get troubleshooting summary.
     *
     * GET /api/notices/troubleshooting/summary
     */
    public function troubleshootingSummary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(7);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();

        $summary = $this->verificationService->getTroubleshootingSummary($dateFrom, $dateTo);

        return response()->json([
            'summary' => $summary,
            'date_range' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
        ]);
    }

    /**
     * Get failures grouped by reason.
     *
     * GET /api/notices/troubleshooting/by-reason
     */
    public function failuresByReason(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(7);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();

        $failures = $this->verificationService->getFailuresByReason($dateFrom, $dateTo);

        return response()->json([
            'failures_by_reason' => $failures,
            'total' => array_sum(array_column($failures, 'count')),
            'date_range' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
        ]);
    }

    /**
     * Get failures grouped by notification type.
     *
     * GET /api/notices/troubleshooting/by-type
     */
    public function failuresByType(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(7);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();

        $failures = $this->verificationService->getFailuresByType($dateFrom, $dateTo);

        return response()->json([
            'failures_by_type' => $failures,
            'total' => array_sum(array_column($failures, 'count')),
            'date_range' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
        ]);
    }

    /**
     * Get verification mismatches.
     *
     * GET /api/notices/troubleshooting/mismatches
     */
    public function mismatches(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(1);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();

        $mismatches = $this->verificationService->getMismatches($dateFrom, $dateTo);

        return response()->json([
            'mismatches' => $mismatches,
            'date_range' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
        ]);
    }

    /**
     * Helper: Get contact value (phone or email).
     */
    protected function getContactValue(NotificationLog $notice): ?string
    {
        return match($notice->delivery_option_id) {
            3, 8 => $notice->phone,  // Voice or SMS
            2 => $notice->email,      // Email
            default => null,
        };
    }
}
