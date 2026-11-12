<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\JobRun;

/**
 * Helper service to encapsulate JobRun lifecycle for console commands.
 *
 * Usage pattern in a command:
 *
 * $jobRun = $jobRunManager->start('notices:import', [ 'options' => $this->options() ]);
 * try {
 *     // ... work ...
 *     $jobRunManager->finish($jobRun, 'success');
 * } catch (\Throwable $e) {
 *     $jobRunManager->finish($jobRun, 'failed', $e->getMessage());
 *     throw $e;
 * }
 */
class JobRunManager
{
    /**
     * Start a new job run.
     *
     * @param string   $jobName     Fully qualified command/job name (e.g. "notices:import").
     * @param array    $meta        Optional metadata to store on the job run (date ranges, options, etc.).
     * @param string   $triggerType manual|schedule|queue
     * @param null|int $userId      optional triggering user id (for manual runs started via UI)
     */
    public function start(string $jobName, array $meta = [], string $triggerType = 'manual', ?int $userId = null): JobRun
    {
        return JobRun::create([
            'job_name'             => $jobName,
            'trigger_type'         => $triggerType,
            'triggered_by_user_id' => $userId,
            'started_at'           => now(),
            'status'               => 'running',
            'meta'                 => $meta,
        ]);
    }

    /**
     * Mark a job run as finished.
     *
     * @param string      $status       New status: success|failed|partial
     * @param null|string $errorMessage Optional error message when failed/partial
     * @param null|array  $extraMeta    Optional additional metadata to merge into existing meta
     */
    public function finish(JobRun $jobRun, string $status = 'success', ?string $errorMessage = null, ?array $extraMeta = null): void
    {
        $meta = $jobRun->meta ?? [];
        if ($extraMeta) {
            $meta = array_merge($meta, $extraMeta);
        }

        $jobRun->update([
            'finished_at'   => now(),
            'status'        => $status,
            'error_message' => $errorMessage,
            'meta'          => $meta,
        ]);
    }
}
