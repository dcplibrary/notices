<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Models\ActivityLog;
use Dcplibrary\Notices\Models\NotificationActivityLog;
use Dcplibrary\Notices\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Settings activity API controller.
 */
class SettingsActivityController extends Controller
{
    /**
     * API endpoint (admin): Notification activity logs (pipeline/reconciliation events).
     *
     * Query params:
     * - date_from/date_to (Y-m-d)
     * - search (free text)
     * - status (related notification status)
     * - source (activity source key)
     * - job_run_id (optional)
     * - per_page (default 50)
     */
    public function notificationActivityLogsApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from'  => 'nullable|date_format:Y-m-d',
            'date_to'    => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search'     => 'nullable|string|max:255',
            'status'     => 'nullable|string|max:50',
            'source'     => 'nullable|string|max:100',
            'job_run_id' => 'nullable|integer|min:1',
            'per_page'   => 'nullable|integer|min:1|max:200',
            'page'       => 'nullable|integer|min:1',
        ]);

        $query = NotificationActivityLog::query()
            ->with(['notification', 'activityFile'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
        ;

        if (!empty($data['date_from'])) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($data['date_from'])->startOfDay());
        }
        if (!empty($data['date_to'])) {
            $query->where('occurred_at', '<', CarbonImmutable::parse($data['date_to'])->addDay()->startOfDay());
        }
        if (!empty($data['job_run_id'])) {
            $query->where('job_run_id', (int) $data['job_run_id']);
        }
        if (!empty($data['source'])) {
            $query->where('source', $data['source']);
        }
        if (!empty($data['status'])) {
            $query->whereHas('notification', function ($q) use ($data) {
                $q->where('status', $data['status']);
            });
        }
        if (!empty($data['search'])) {
            $like            = '%' . $data['search'] . '%';
            $searchTerm      = $data['search'];
            $isNumericSearch = ctype_digit($searchTerm);

            $query->where(function ($q) use ($like, $searchTerm, $isNumericSearch) {
                $q->where('description', 'LIKE', $like)
                    ->orWhere('source', 'LIKE', $like)
                    ->orWhere('meta', 'LIKE', $like)
                    ->orWhereHas('notification', function ($sub) use ($like, $searchTerm, $isNumericSearch) {
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

        $perPage = (int) ($data['per_page'] ?? 50);
        $page    = (int) ($data['page'] ?? 1);

        $p = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($p->items())->map(function (NotificationActivityLog $row) {
                return [
                    'id'           => $row->id,
                    'occurred_at'  => $row->occurred_at?->toIso8601String(),
                    'source'       => $row->source,
                    'description'  => $row->description,
                    'meta'         => $row->meta,
                    'job_run_id'   => $row->job_run_id,
                    'notification' => $row->notification ? [
                        'id'                 => $row->notification->id,
                        'patron_barcode'     => $row->notification->patron_barcode,
                        'status'             => $row->notification->status,
                        'delivery_option_id' => $row->notification->delivery_option_id,
                        'notification_type'  => $row->notification->notification_type,
                    ] : null,
                    'activity_file' => $row->activityFile ? [
                        'id'            => $row->activityFile->id,
                        'original_name' => $row->activityFile->original_name ?? null,
                        'path'          => $row->activityFile->path          ?? null,
                    ] : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'from'         => $p->firstItem(),
                'to'           => $p->lastItem(),
            ],
        ]);
    }

    /**
     * API endpoint (admin): User activity logs (who did what).
     */
    public function userActivityLogsApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from'  => 'nullable|date_format:Y-m-d',
            'date_to'    => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search'     => 'nullable|string|max:255',
            'job_run_id' => 'nullable|integer|min:1',
            'per_page'   => 'nullable|integer|min:1|max:200',
            'page'       => 'nullable|integer|min:1',
        ]);

        $query = UserActivityLog::query()
            ->with(['notification', 'jobRun'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
        ;

        if (!empty($data['date_from'])) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($data['date_from'])->startOfDay());
        }
        if (!empty($data['date_to'])) {
            $query->where('occurred_at', '<', CarbonImmutable::parse($data['date_to'])->addDay()->startOfDay());
        }
        if (!empty($data['job_run_id'])) {
            $query->where('job_run_id', (int) $data['job_run_id']);
        }
        if (!empty($data['search'])) {
            $like = '%' . $data['search'] . '%';
            $query->where(function ($q) use ($like) {
                $q->where('action', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like)
                    ->orWhere('meta', 'LIKE', $like)
                ;
            });
        }

        $perPage = (int) ($data['per_page'] ?? 50);
        $page    = (int) ($data['page'] ?? 1);
        $p       = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($p->items())->map(function (UserActivityLog $row) {
                return [
                    'id'              => $row->id,
                    'occurred_at'     => $row->occurred_at?->toIso8601String(),
                    'action'          => $row->action      ?? null,
                    'description'     => $row->description ?? null,
                    'meta'            => $row->meta,
                    'user_id'         => $row->user_id,
                    'user_type'       => $row->user_type,
                    'notification_id' => $row->notification_id,
                    'job_run_id'      => $row->job_run_id,
                    'job_run'         => $row->jobRun ? [
                        'id'       => $row->jobRun->id,
                        'job_name' => $row->jobRun->job_name,
                        'status'   => $row->jobRun->status,
                    ] : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'from'         => $p->firstItem(),
                'to'           => $p->lastItem(),
            ],
        ]);
    }

    /**
     * API endpoint (admin): System activity log entries (from activity_log).
     *
     * Defaults to cleanup-related events, mirroring Settings → Activity.
     */
    public function systemActivityLogsApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search'    => 'nullable|string|max:255',
            'action'    => 'nullable|string|max:100',
            'per_page'  => 'nullable|integer|min:1|max:200',
            'page'      => 'nullable|integer|min:1',
        ]);

        $query = ActivityLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
        ;

        // Mirror UI default: cleanup.* only unless action filter is explicitly provided
        if (!empty($data['action'])) {
            $query->where('action', 'LIKE', $data['action'] . '%');
        } else {
            $query->where('action', 'like', 'cleanup.import_logs%');
        }

        if (!empty($data['date_from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($data['date_from'])->startOfDay());
        }
        if (!empty($data['date_to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($data['date_to'])->addDay()->startOfDay());
        }
        if (!empty($data['search'])) {
            $like = '%' . $data['search'] . '%';
            $query->where(function ($q) use ($like) {
                $q->where('action', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like)
                    ->orWhere('meta', 'LIKE', $like)
                ;
            });
        }

        $perPage = (int) ($data['per_page'] ?? 50);
        $page    = (int) ($data['page'] ?? 1);
        $p       = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($p->items())->map(function (ActivityLog $row) {
                return [
                    'id'           => $row->id,
                    'created_at'   => $row->created_at?->toIso8601String(),
                    'action'       => $row->action      ?? null,
                    'description'  => $row->description ?? null,
                    'meta'         => $row->meta,
                    'subject_type' => $row->subject_type ?? null,
                    'subject_id'   => $row->subject_id   ?? null,
                    'user_type'    => $row->user_type    ?? null,
                    'user_id'      => $row->user_id      ?? null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'from'         => $p->firstItem(),
                'to'           => $p->lastItem(),
            ],
        ]);
    }
}
