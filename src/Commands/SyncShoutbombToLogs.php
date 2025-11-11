<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncShoutbombToLogs extends Command
{
    protected $signature = 'notices:sync-shoutbomb-to-logs
                            {--date= : Sync data for specific date (YYYY-MM-DD)}
                            {--days=7 : Number of days to sync (default: 7)}
                            {--dry-run : Show what would be synced without actually syncing}';

    protected $description = 'Sync Shoutbomb PhoneNotices data into notification_logs for dashboard visibility';

    public function handle(): int
    {
        $this->info('🔄 Syncing Shoutbomb data to notification_logs...');
        $this->newLine();

        // Determine date range
        if ($this->option('date')) {
            $startDate = \Carbon\Carbon::parse($this->option('date'))->startOfDay();
            $endDate = $startDate->copy()->endOfDay();
        } else {
            $days = (int) $this->option('days');
            $endDate = now();
            $startDate = now()->subDays($days);
        }

        $this->line("→ Date range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->newLine();

        // Get Shoutbomb phone notices that aren't already in notification_logs
        $phoneNotices = PolarisPhoneNotice::whereBetween('notice_date', [$startDate, $endDate])
            ->get();

        if ($phoneNotices->isEmpty()) {
            $this->warn('No Shoutbomb phone notices found for this date range.');
            return Command::SUCCESS;
        }

        $this->line("Found {$phoneNotices->count()} phone notices");

        // Group by delivery type
        $voiceCount = $phoneNotices->where('delivery_type', 'voice')->count();
        $textCount = $phoneNotices->where('delivery_type', 'text')->count();

        $this->table(
            ['Delivery Type', 'Count'],
            [
                ['Voice', number_format($voiceCount)],
                ['Text/SMS', number_format($textCount)],
                ['Total', number_format($phoneNotices->count())],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run mode - no data will be synced');
            $this->line('These notifications would be added to notification_logs');
            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (!$this->confirm('Sync these notifications to notification_logs?')) {
            $this->line('Cancelled');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('→ Syncing to notification_logs...');

        $synced = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($phoneNotices->count());
        $progressBar->start();

        foreach ($phoneNotices as $phoneNotice) {
            try {
                // Check if this notice already exists in notification_logs
                $exists = NotificationLog::where('patron_barcode', $phoneNotice->patron_barcode)
                    ->where('notification_date', $phoneNotice->notice_date)
                    ->where('delivery_option_id', $phoneNotice->delivery_type === 'voice' ? 3 : 8)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // Convert phone notice to notification log format
                NotificationLog::create([
                    'patron_id' => $phoneNotice->patron_id,
                    'patron_barcode' => $phoneNotice->patron_barcode,
                    'notification_date' => $phoneNotice->notice_date,
                    'notification_type_id' => $this->mapNotificationType($phoneNotice),
                    'delivery_option_id' => $phoneNotice->delivery_type === 'voice' ? 3 : 8,
                    'notification_status_id' => 12, // Assume success since it's in PhoneNotices.csv
                    'delivery_string' => $phoneNotice->phone,
                    'holds_count' => 0, // PhoneNotices.csv doesn't have counts
                    'overdues_count' => 0,
                    'reporting_org_id' => config('notices.reporting_org_id'),
                    'details' => "Synced from PhoneNotices.csv (file: {$phoneNotice->source_file})",
                    'imported_at' => now(),
                ]);

                $synced++;

            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error syncing notice {$phoneNotice->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->line('─────────────────────────────────────────');
        $this->info('✅ Sync completed!');
        $this->line('─────────────────────────────────────────');

        $this->table(
            ['Result', 'Count'],
            [
                ['Synced', number_format($synced)],
                ['Skipped (already exists)', number_format($skipped)],
                ['Errors', number_format($errors)],
            ]
        );

        if ($synced > 0) {
            $this->newLine();
            $this->info('Voice/SMS notifications are now visible in:');
            $this->line('  • Dashboard overview (/notices)');
            $this->line('  • Analytics page (/notices/analytics)');
            $this->line('  • Notifications list (/notices/notifications)');
            $this->newLine();
            $this->warn('⚠️  Remember to run aggregation to update statistics:');
            $this->line('  php artisan notices:aggregate');
        }

        $this->newLine();

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Map PhoneNotice to notification type ID.
     * Since PhoneNotices.csv doesn't include type info, we use a default.
     */
    protected function mapNotificationType(PolarisPhoneNotice $phoneNotice): int
    {
        // PhoneNotices.csv doesn't specify notification type
        // Could be holds, overdues, or other types
        // Default to "Hold Ready" (2) as it's most common for phone notifications
        // This could be enhanced by matching against shoutbomb_submissions if needed
        return 2; // Hold Ready
    }
}
