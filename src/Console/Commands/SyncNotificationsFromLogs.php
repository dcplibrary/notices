<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\NotificationProjectionService;
use Illuminate\Console\Command;

class SyncNotificationsFromLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:sync-from-logs
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--days=1 : Number of days back from today if no range given}';

    /**
     * The console command description.
     */
    protected $description = 'Project NotificationLog rows into master notifications and events';

    public function handle(NotificationProjectionService $projectionService): int
    {
        $fromOption = $this->option('from');
        $toOption = $this->option('to');
        $days = (int) $this->option('days');

        if ($fromOption && $toOption) {
            $start = Carbon::parse($fromOption)->startOfDay();
            $end = Carbon::parse($toOption)->endOfDay();
        } elseif ($fromOption xor $toOption) {
            $this->error('You must provide both --from and --to, or neither.');
            return self::FAILURE;
        } else {
            // Default: sync last N days ending today
            $end = Carbon::today()->endOfDay();
            $start = (clone $end)->subDays(max($days - 1, 0))->startOfDay();
        }

        $this->info(sprintf(
            'Syncing notifications from NotificationLog between %s and %s...',
            $start->toDateTimeString(),
            $end->toDateTimeString()
        ));

        $count = $projectionService->syncRange($start, $end);

        $this->info("Projected {$count} NotificationLog rows into master notifications.");

        return self::SUCCESS;
    }
}
