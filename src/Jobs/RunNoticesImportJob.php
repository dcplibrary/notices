<?php

namespace Dcplibrary\Notices\Jobs;

use Dcplibrary\Notices\Models\JobRun;
use Dcplibrary\Notices\NoticesManager;
use Dcplibrary\Notices\Services\JobRunManager;
use Dcplibrary\Notices\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Queue job: run the full notices pipeline for a date range.
 *
 * This is the async/queued equivalent of running `php artisan notices:import`,
 * but without blocking the HTTP request.
 */
class RunNoticesImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $jobRunId,
        public string $fromDate, // Y-m-d
        public string $toDate,   // Y-m-d
        public int $orphanedDays = 7,
        public int $integrityDays = 30,
        public bool $integrityFix = true,
    ) {}

    public function handle(
        NoticesManager $manager,
        NotificationService $notificationService,
        JobRunManager $jobRunManager,
    ): void {
        /** @var null|JobRun $jobRun */
        $jobRun = JobRun::find($this->jobRunId);
        if (!$jobRun) {
            return;
        }

        // Mark as running
        $jobRun->update([
            'started_at'   => $jobRun->started_at ?? now(),
            'status'       => 'running',
            'trigger_type' => 'queue',
        ]);

        try {
            $from = new \DateTimeImmutable($this->fromDate);
            $to   = new \DateTimeImmutable($this->toDate);

            // Cancellation checkpoints: before we start, and between phases.
            if (($jobRun->meta['cancel_requested'] ?? false) === true) {
                $jobRunManager->finish($jobRun, 'cancelled', 'Cancellation requested before start.');

                return;
            }

            // Run the full import (imports + reconciliation + failure detection).
            $manager->runImportForDateRange($from, $to);

            $jobRun->refresh();
            if (($jobRun->meta['cancel_requested'] ?? false) === true) {
                $jobRunManager->finish($jobRun, 'cancelled', 'Cancellation requested after import.');

                return;
            }

            // After import + reconciliation, mark orphaned notifications.
            $orphaned = $notificationService->processOrphanedNotifications($this->orphanedDays);

            $jobRun->refresh();
            if (($jobRun->meta['cancel_requested'] ?? false) === true) {
                $jobRunManager->finish($jobRun, 'cancelled', 'Cancellation requested after orphaned processing.', [
                    'orphaned_results' => $orphaned,
                ]);

                return;
            }

            // Run integrity checks (optionally auto-fix) as the final step.
            $integrityExit = Artisan::call('notices:integrity-check', array_filter([
                '--days'           => $this->integrityDays,
                '--fix'            => $this->integrityFix ? true : null,
                '--no-interaction' => true,
            ], fn ($v) => null !== $v));
            $integrityOutput = Artisan::output();

            $jobRunManager->finish($jobRun, 'success', null, [
                'orphaned_results' => $orphaned,
                'integrity'        => [
                    'exit_code' => $integrityExit,
                    'output'    => $integrityOutput,
                ],
            ]);
        } catch (\Throwable $e) {
            $jobRunManager->finish($jobRun, 'failed', $e->getMessage());

            throw $e;
        }
    }
}
