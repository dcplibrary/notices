<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Services\ActivityLogArchiver;
use Illuminate\Console\Command;

/**
 * Archive notification_activity_logs older than the retention window.
 *
 * Kept rows stay in the live table for the activity screens. Archived rows
 * are written to storage/app/notices/activity-archives and removed. Staff
 * backups do not include this table.
 */
class ArchiveActivityLogsCommand extends Command
{
    protected $signature = 'notices:archive-activity-logs
        {--days= : Days of activity logs to keep. Defaults to notices.activity_log.retention_days (NOTICES_ACTIVITY_LOG_RETENTION_DAYS).}
        {--dry-run : Count rows that would be archived without writing a file or deleting}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Archive notification activity logs older than the retention window and delete them from the live table.';

    public function handle(ActivityLogArchiver $archiver): int
    {
        $configured = (int) config('notices.activity_log.retention_days', 90);
        $days       = max(1, (int) ($this->option('days') ?: $configured));
        $dryRun     = (bool) $this->option('dry-run');
        $force      = (bool) $this->option('force');

        $this->info('[notices:archive-activity-logs] Keeping the last ' . $days . ' day(s) of notification activity logs.');

        if (!$dryRun && !$force && !$this->confirm('Archive older notification activity logs and delete them from the live table?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $result = $archiver->archive($days, $dryRun);

        if ($dryRun) {
            $this->info('Dry run: ' . $result['eligible'] . ' row(s) would be archived.');

            return self::SUCCESS;
        }

        if (0 === $result['archived']) {
            $this->info('No activity logs were old enough to archive.');

            return self::SUCCESS;
        }

        $this->info('Archived ' . $result['archived'] . ' row(s) to ' . $result['path'] . '.');

        return self::SUCCESS;
    }
}
