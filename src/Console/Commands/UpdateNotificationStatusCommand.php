<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateNotificationStatusCommand extends Command
{
    protected $signature = 'notices:update-status';

    protected $description = 'Update status field on existing notification logs based on notification_status_id';

    public function handle()
    {
        $this->info('Updating status field on notification logs...');

        // Status mappings from NotificationLog model
        $completedStatuses = [1, 2, 12, 15, 16];
        $failedStatuses = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14];

        try {
            // Update completed statuses
            $completedCount = DB::table('notification_logs')
                ->whereIn('notification_status_id', $completedStatuses)
                ->update(['status' => 'completed']);

            $this->line("Updated {$completedCount} records to 'completed'");

            // Update failed statuses
            $failedCount = DB::table('notification_logs')
                ->whereIn('notification_status_id', $failedStatuses)
                ->update(['status' => 'failed']);

            $this->line("Updated {$failedCount} records to 'failed'");

            // Update pending statuses (anything not in completed or failed)
            $pendingCount = DB::table('notification_logs')
                ->whereNotIn('notification_status_id', array_merge($completedStatuses, $failedStatuses))
                ->update(['status' => 'pending']);

            $this->line("Updated {$pendingCount} records to 'pending'");

            $total = $completedCount + $failedCount + $pendingCount;
            $this->info("✓ Successfully updated {$total} notification logs");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to update statuses: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
