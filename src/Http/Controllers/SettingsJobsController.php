<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Jobs\RunArtisanCommandJob;
use Dcplibrary\Notices\Jobs\RunNoticesImportJob;
use Dcplibrary\Notices\Models\JobRun;
use Dcplibrary\Notices\Models\NotificationActivityLog;
use Dcplibrary\Notices\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Settings jobs controller.
 *
 * Handles jobs dashboard, job run status endpoints, and admin queue actions.
 */
class SettingsJobsController extends Controller
{
    /**
     * Jobs dashboard (queued/running/recent job runs).
     */
    public function jobs()
    {
        $runs = JobRun::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get()
        ;

        $activeCount = JobRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->count()
        ;

        $statusCounts = JobRun::query()
            ->selectRaw('COALESCE(status, "unknown") as status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray()
        ;

        return view('notices::settings.jobs', [
            'runs'          => $runs,
            'active_count'  => $activeCount,
            'status_counts' => $statusCounts,
        ]);
    }

    /**
     * API endpoint (admin): Run the full notices pipeline (notices:import).
     *
     * Body accepts optional date options:
     * - date: YYYY-MM-DD
     * - from/to: YYYY-MM-DD
     * - days: int
     */
    public function runImportApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'           => 'nullable|date_format:Y-m-d',
            'from'           => 'nullable|date_format:Y-m-d',
            'to'             => 'nullable|date_format:Y-m-d',
            'days'           => 'nullable|integer|min:1|max:365',
            'orphaned_days'  => 'nullable|integer|min:1|max:365',
            'integrity_days' => 'nullable|integer|min:1|max:365',
            'integrity_fix'  => 'nullable|boolean',
        ]);

        // Resolve date range like the CLI command would
        $from = $data['from'] ?? null;
        $to   = $data['to']   ?? null;

        if (!empty($data['date'])) {
            $from = $data['date'];
            $to   = $data['date'];
        } elseif (!empty($data['days'])) {
            $toDate   = now()->toDateString();
            $fromDate = now()->subDays(((int) $data['days']) - 1)->toDateString();
            $from     = $fromDate;
            $to       = $toDate;
        } else {
            $from = $from ?: now()->toDateString();
            $to   = $to ?: $from;
        }

        $jobRun = JobRun::create([
            'job_name'             => 'notices:import',
            'trigger_type'         => 'queue',
            'triggered_by_user_id' => auth()->user()?->getAuthIdentifier(),
            'started_at'           => null,
            'finished_at'          => null,
            'status'               => 'queued',
            'meta'                 => [
                'requested' => $data,
                'from'      => $from,
                'to'        => $to,
            ],
        ]);

        RunNoticesImportJob::dispatch(
            $jobRun->id,
            $from,
            $to,
            (int) ($data['orphaned_days'] ?? 7),
            (int) ($data['integrity_days'] ?? 30),
            (bool) ($data['integrity_fix'] ?? true),
        );

        return response()->json([
            'success'    => true,
            'queued'     => true,
            'job_run_id' => $jobRun->id,
            'status'     => 'queued',
        ], 202);
    }

    /**
     * API endpoint (admin): Run delivery confirmation import (notices:import-confirmations).
     */
    public function runImportConfirmationsApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $jobRun = JobRun::create([
            'job_name'             => 'notices:import-confirmations',
            'trigger_type'         => 'queue',
            'triggered_by_user_id' => auth()->user()?->getAuthIdentifier(),
            'started_at'           => null,
            'finished_at'          => null,
            'status'               => 'queued',
            'meta'                 => ['requested' => $data],
        ]);

        $params = [];
        if (!empty($data['date'])) {
            $params['date'] = $data['date'];
        }

        RunArtisanCommandJob::dispatch($jobRun->id, 'notices:import-confirmations', $params);

        return response()->json([
            'success'    => true,
            'queued'     => true,
            'job_run_id' => $jobRun->id,
            'status'     => 'queued',
        ], 202);
    }

    /**
     * API endpoint (admin): Run orphaned notification processing (notices:process-orphaned).
     */
    public function runProcessOrphanedApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $jobRun = JobRun::create([
            'job_name'             => 'notices:process-orphaned',
            'trigger_type'         => 'queue',
            'triggered_by_user_id' => auth()->user()?->getAuthIdentifier(),
            'started_at'           => null,
            'finished_at'          => null,
            'status'               => 'queued',
            'meta'                 => ['requested' => $data],
        ]);

        RunArtisanCommandJob::dispatch($jobRun->id, 'notices:process-orphaned', [
            '--days' => (int) ($data['days'] ?? 7),
        ]);

        return response()->json([
            'success'    => true,
            'queued'     => true,
            'job_run_id' => $jobRun->id,
            'status'     => 'queued',
        ], 202);
    }

    /**
     * API endpoint (admin): Run integrity checks (notices:integrity-check).
     */
    public function runIntegrityCheckApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'fix'  => 'nullable|boolean',
        ]);

        $jobRun = JobRun::create([
            'job_name'             => 'notices:integrity-check',
            'trigger_type'         => 'queue',
            'triggered_by_user_id' => auth()->user()?->getAuthIdentifier(),
            'started_at'           => null,
            'finished_at'          => null,
            'status'               => 'queued',
            'meta'                 => ['requested' => $data],
        ]);

        $params = [
            '--days' => (int) ($data['days'] ?? 30),
        ];
        if (!empty($data['fix'])) {
            $params['--fix'] = true;
        }

        RunArtisanCommandJob::dispatch($jobRun->id, 'notices:integrity-check', $params);

        return response()->json([
            'success'    => true,
            'queued'     => true,
            'job_run_id' => $jobRun->id,
            'status'     => 'queued',
        ], 202);
    }

    /**
     * API endpoint (admin): Cleanup cached import/export files (notices:cleanup-import-logs).
     */
    public function runCleanupImportLogsApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $days   = (int) ($data['days'] ?? 30);
        $today  = now()->startOfDay();
        $cutoff = (clone $today)->subDays($days);
        $from   = (clone $today)->subDays(365);

        $jobRun = JobRun::create([
            'job_name'             => 'notices:cleanup-import-logs',
            'trigger_type'         => 'queue',
            'triggered_by_user_id' => auth()->user()?->getAuthIdentifier(),
            'started_at'           => null,
            'finished_at'          => null,
            'status'               => 'queued',
            'meta'                 => [
                'requested' => $data,
                'from'      => $from->format('Y-m-d'),
                'to'        => $cutoff->format('Y-m-d'),
            ],
        ]);

        RunArtisanCommandJob::dispatch($jobRun->id, 'notices:cleanup-import-logs', [
            '--from'  => $from->format('Y-m-d'),
            '--to'    => $cutoff->format('Y-m-d'),
            '--force' => true,
        ]);

        return response()->json([
            'success'    => true,
            'queued'     => true,
            'job_run_id' => $jobRun->id,
            'status'     => 'queued',
        ], 202);
    }

    /**
     * API endpoint (admin): List recent job runs.
     */
    public function jobRunsApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $limit = (int) ($data['limit'] ?? 50);

        $statusCounts = JobRun::query()
            ->selectRaw('COALESCE(status, "unknown") as status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray()
        ;

        $runs = JobRun::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (JobRun $run) => [
                'id'            => $run->id,
                'job_name'      => $run->job_name      ?? null,
                'status'        => $run->status        ?? null,
                'trigger_type'  => $run->trigger_type  ?? null,
                'error_message' => $run->error_message ?? null,
                'started_at'    => $run->started_at?->toIso8601String(),
                'finished_at'   => $run->finished_at?->toIso8601String(),
                'meta'          => $run->meta,
            ])
            ->values()
        ;

        return response()->json([
            'data' => $runs,
            'meta' => [
                'counts' => $statusCounts,
            ],
        ]);
    }

    /**
     * API endpoint (admin): Get a single job run with related logs.
     */
    public function jobRunApi(int $id): JsonResponse
    {
        $run = JobRun::query()
            ->with(['activityFiles', 'activityLogs', 'userActivityLogs'])
            ->findOrFail($id)
        ;

        return response()->json([
            'id'             => $run->id,
            'command'        => $run->command ?? null,
            'status'         => $run->status  ?? null,
            'trigger'        => $run->trigger ?? null,
            'started_at'     => $run->started_at?->toIso8601String(),
            'finished_at'    => $run->finished_at?->toIso8601String(),
            'meta'           => $run->meta,
            'activity_files' => $run->activityFiles->map(fn ($f) => [
                'id'         => $f->id,
                'path'       => $f->path  ?? null,
                'label'      => $f->label ?? null,
                'created_at' => $f->created_at?->toIso8601String(),
            ])->values(),
            'activity_logs' => $run->activityLogs->map(fn ($l) => [
                'id'         => $l->id,
                'message'    => $l->message ?? null,
                'created_at' => $l->created_at?->toIso8601String(),
                'meta'       => $l->meta ?? null,
            ])->values(),
            'user_activity_logs' => $run->userActivityLogs->map(fn ($l) => [
                'id'         => $l->id,
                'action'     => $l->action  ?? null,
                'message'    => $l->message ?? null,
                'created_at' => $l->created_at?->toIso8601String(),
                'meta'       => $l->meta ?? null,
            ])->values(),
        ]);
    }

    /**
     * API endpoint (admin): Incremental job events (polling-friendly).
     *
     * Query params:
     * - since_activity_log_id: only return NotificationActivityLog rows with id > this
     * - since_user_activity_log_id: only return UserActivityLog rows with id > this
     */
    public function jobRunEventsApi(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'since_activity_log_id'      => 'nullable|integer|min:0',
            'since_user_activity_log_id' => 'nullable|integer|min:0',
            'limit'                      => 'nullable|integer|min:1|max:500',
        ]);

        $limit = (int) ($data['limit'] ?? 200);

        $run = JobRun::query()->findOrFail($id);

        $sinceActivity = (int) ($data['since_activity_log_id'] ?? 0);
        $sinceUser     = (int) ($data['since_user_activity_log_id'] ?? 0);

        $activityLogs = NotificationActivityLog::query()
            ->where('job_run_id', $run->id)
            ->where('id', '>', $sinceActivity)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn ($l) => [
                'id'         => $l->id,
                'message'    => $l->message ?? null,
                'created_at' => $l->created_at?->toIso8601String(),
                'meta'       => $l->meta ?? null,
            ])
            ->values()
        ;

        $userLogs = UserActivityLog::query()
            ->where('job_run_id', $run->id)
            ->where('id', '>', $sinceUser)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn ($l) => [
                'id'         => $l->id,
                'action'     => $l->action  ?? null,
                'message'    => $l->message ?? null,
                'created_at' => $l->created_at?->toIso8601String(),
                'meta'       => $l->meta ?? null,
            ])
            ->values()
        ;

        return response()->json([
            'job_run' => [
                'id'          => $run->id,
                'status'      => $run->status,
                'started_at'  => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ],
            'activity_logs'      => $activityLogs,
            'user_activity_logs' => $userLogs,
            'next'               => [
                'since_activity_log_id'      => $activityLogs->last()['id'] ?? $sinceActivity,
                'since_user_activity_log_id' => $userLogs->last()['id']     ?? $sinceUser,
            ],
        ]);
    }

    /**
     * API endpoint (admin): Request cancellation for a job run.
     *
     * This is best-effort: queued jobs will stop at safe checkpoints (between steps).
     * It cannot reliably interrupt an Artisan command already executing.
     */
    public function cancelJobRunApi(int $id): JsonResponse
    {
        $run = JobRun::query()->findOrFail($id);

        $meta                        = $run->meta ?? [];
        $meta['cancel_requested']    = true;
        $meta['cancel_requested_at'] = now()->toIso8601String();

        $run->update([
            'meta' => $meta,
        ]);

        return response()->json([
            'success'          => true,
            'job_run_id'       => $run->id,
            'status'           => $run->status,
            'cancel_requested' => true,
        ]);
    }
}
