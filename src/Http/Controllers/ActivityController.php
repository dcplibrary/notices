<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Models\ActivityLog;
use Dcplibrary\Notices\Models\NotificationActivityLog;
use Dcplibrary\Notices\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Settings / Activity dashboard controller.
 *
 * Provides read-only views of system activity for administrators:
 * - Notification activity log (status changes, reconciliation events, etc.)
 * - User activity log (who ran imports, resolved issues, changed settings, etc.)
 */
class ActivityController extends Controller
{
    /**
     * Display combined activity overview.
     *
     * Includes three activity streams:
     * - notificationActivity: per-notification reconciliation/import events
     * - userActivity: user-driven actions (resolves, bulk operations, settings)
     * - systemActivity: system-level maintenance (cleanup.import_logs.* from activity_log)
     *
     * @return \\Illuminate\\View\\View
     */
    public function index(Request $request)
    {
        // Activity-centric filters (not the full notification filter set).
        // These are scoped to how events are recorded in notification_activity_logs.
        $filters = $request->only([
            'date_from',
            'date_to',
            'search',      // free-text across description/source/file/meta/notification
            'status',      // status of related notification (optional but still useful)
            'source',      // activity source key (notification_log, summary_report, ftp_holds, etc.)
        ]);

        $dateFrom      = $filters['date_from'] ?? null;
        $dateTo        = $filters['date_to']   ?? null;
        $dateFromStart = is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            ? CarbonImmutable::parse($dateFrom)->startOfDay()
            : null;
        $dateToEndExclusive = is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)
            ? CarbonImmutable::parse($dateTo)->addDay()->startOfDay()
            : null;

        $activityQuery = NotificationActivityLog::query()
            ->with(['notification', 'activityFile'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
        ;

        if (null !== $dateFromStart) {
            $activityQuery->where('occurred_at', '>=', $dateFromStart);
        }
        if (null !== $dateToEndExclusive) {
            $activityQuery->where('occurred_at', '<', $dateToEndExclusive);
        }

        // Optional: filter by status of the related notification
        if (!empty($filters['status'])) {
            $activityQuery->whereHas('notification', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            });
        }

        // Filter by activity source key (how the event was generated)
        if (!empty($filters['source'])) {
            $activityQuery->where('source', $filters['source']);
        }

        // (Intentionally no has_file filter for now; behaviour was confusing in UI.)

        // Combined search across description, source, related notification, and file
        if (!empty($filters['search'])) {
            $searchTerm      = $filters['search'];
            $isNumericSearch = ctype_digit($searchTerm);

            $activityQuery->where(function ($query) use ($searchTerm, $isNumericSearch) {
                $like = '%' . $searchTerm . '%';

                $query->where('description', 'LIKE', $like)
                    ->orWhere('source', 'LIKE', $like)
                    ->orWhere('meta', 'LIKE', $like)
                    ->orWhereHas('notification', function ($sub) use ($searchTerm, $isNumericSearch, $like) {
                        $sub->where('patron_barcode', 'LIKE', $like)
                            ->orWhere('failure_reason', 'LIKE', $like)
                        ;

                        if ($isNumericSearch) {
                            $sub->orWhere('id', (int) $searchTerm);
                        }
                    })
                    ->orWhereHas('activityFile', function ($sub) use ($like) {
                        $sub->where('original_name', 'LIKE', $like);
                    })
                ;
            });
        }

        $notificationActivity = $activityQuery->paginate(50, ['*'], 'notifications_page');

        // User activity uses the same date range + search text (where applicable)
        $userQuery = UserActivityLog::query()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
        ;

        if (null !== $dateFromStart) {
            $userQuery->where('occurred_at', '>=', $dateFromStart);
        }
        if (null !== $dateToEndExclusive) {
            $userQuery->where('occurred_at', '<', $dateToEndExclusive);
        }
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $userQuery->where(function ($query) use ($like) {
                $query->where('action', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like)
                    ->orWhere('meta', 'LIKE', $like)
                ;
            });
        }

        $userActivity = $userQuery->paginate(50, ['*'], 'users_page');

        // System-level maintenance activity (from activity_log) for cleanup runs.
        $systemQuery = ActivityLog::query()
            ->where('action', 'like', 'cleanup.import_logs%')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
        ;

        if (null !== $dateFromStart) {
            $systemQuery->where('created_at', '>=', $dateFromStart);
        }
        if (null !== $dateToEndExclusive) {
            $systemQuery->where('created_at', '<', $dateToEndExclusive);
        }
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $systemQuery->where(function ($query) use ($like) {
                $query->where('action', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like)
                    ->orWhere('meta', 'LIKE', $like)
                ;
            });
        }

        $systemActivity = $systemQuery->paginate(50, ['*'], 'system_page');

        // Lookup data for filters that are meaningful at the activity level
        $activitySources = NotificationActivityLog::query()
            ->select('source')
            ->whereNotNull('source')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
        ;

        return view('notices::settings.activity', [
            'notificationActivity' => $notificationActivity,
            'userActivity'         => $userActivity,
            'systemActivity'       => $systemActivity,
            'date_from'            => $dateFrom,
            'date_to'              => $dateTo,
            'filters'              => $filters,
            'activity_sources'     => $activitySources,
        ]);
    }
}
