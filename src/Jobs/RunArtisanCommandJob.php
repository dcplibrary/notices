<?php

namespace Dcplibrary\Notices\Jobs;

use Dcplibrary\Notices\Models\JobRun;
use Dcplibrary\Notices\Services\JobRunManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Queue job: run a single Artisan command and record output in JobRun meta.
 *
 * Useful for admin maintenance actions that are already implemented as commands.
 */
class RunArtisanCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string,mixed> $params
     */
    public function __construct(
        public int $jobRunId,
        public string $command,
        public array $params = [],
    ) {}

    public function handle(JobRunManager $jobRunManager): void
    {
        /** @var null|JobRun $jobRun */
        $jobRun = JobRun::find($this->jobRunId);
        if (!$jobRun) {
            return;
        }

        $jobRun->update([
            'started_at'   => $jobRun->started_at ?? now(),
            'status'       => 'running',
            'trigger_type' => 'queue',
        ]);

        try {
            $jobRun->refresh();
            if (($jobRun->meta['cancel_requested'] ?? false) === true) {
                $jobRunManager->finish($jobRun, 'cancelled', 'Cancellation requested before command start.');

                return;
            }

            $params                     = $this->params;
            $params['--no-interaction'] = $params['--no-interaction'] ?? true;

            $exitCode = Artisan::call($this->command, $params);
            $output   = Artisan::output();

            $jobRunManager->finish($jobRun, 0 === $exitCode ? 'success' : 'failed', 0 === $exitCode ? null : 'Command failed', [
                'command' => [
                    'name'      => $this->command,
                    'params'    => $params,
                    'exit_code' => $exitCode,
                    'output'    => $output,
                ],
            ]);
        } catch (\Throwable $e) {
            $jobRunManager->finish($jobRun, 'failed', $e->getMessage());

            throw $e;
        }
    }
}
