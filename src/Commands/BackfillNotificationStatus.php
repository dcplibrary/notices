<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Console\Command;

class BackfillNotificationStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:backfill-status
                            {--chunk=1000 : Number of records to process per chunk}';

    /**
     * The console command description.
     */
    protected $description = 'Backfill status and status_description fields on existing notification logs';

    /**
     * Status ID mappings.
     */
    private const COMPLETED_STATUSES = [1, 2, 12, 15, 16];
    private const FAILED_STATUSES = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting notification status backfill...');
        
        $chunkSize = (int) $this->option('chunk');
        $totalProcessed = 0;
        $progressBar = null;

        // Get total count
        $total = NotificationLog::count();
        
        if ($total === 0) {
            $this->info('No notification logs found to process.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$total} notification logs in chunks of {$chunkSize}...");
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        // Process in chunks to avoid memory issues
        NotificationLog::chunk($chunkSize, function ($notifications) use (&$totalProcessed, $progressBar) {
            foreach ($notifications as $notification) {
                $this->setStatus($notification);
                $totalProcessed++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);
        $this->info("Successfully processed {$totalProcessed} notification logs.");

        return Command::SUCCESS;
    }

    /**
     * Set status fields based on notification_status_id.
     */
    private function setStatus(NotificationLog $notification): void
    {
        $statusId = $notification->notification_status_id;

        // Determine status
        if (in_array($statusId, self::COMPLETED_STATUSES)) {
            $status = 'completed';
        } elseif (in_array($statusId, self::FAILED_STATUSES)) {
            $status = 'failed';
        } else {
            $status = 'pending';
        }

        // Get status description
        $description = config("notices.notification_statuses.{$statusId}");

        // Update without triggering model events or updated_at changes
        $notification->updateQuietly([
            'status' => $status,
            'status_description' => $description,
        ]);
    }
}
