<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Dcplibrary\Notices\Models\ShoutbombSubmission;
use Illuminate\Console\Command;

class DiagnoseDashboardDataCommand extends Command
{
    protected $signature = 'notices:diagnose-dashboard {--days=30 : Number of days to analyze}';

    protected $description = 'Comprehensive diagnosis of why dashboard shows incomplete data';

    public function handle(): int
    {
        $this->info('🔍 Diagnosing Dashboard Data Issues...');
        $this->newLine();

        $days = (int) $this->option('days');
        $endDate = now();
        $startDate = now()->subDays($days);

        // === STEP 1: Check notification_logs table ===
        $this->line('═══════════════════════════════════════════');
        $this->info('STEP 1: Checking notification_logs table');
        $this->line('═══════════════════════════════════════════');
        $this->newLine();

        $totalLogs = NotificationLog::count();
        $logsInRange = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total records in notification_logs', number_format($totalLogs)],
                ['Records in last ' . $days . ' days', number_format($logsInRange)],
            ]
        );

        if ($totalLogs === 0) {
            $this->error('❌ notification_logs table is EMPTY!');
            $this->line('   This is why your dashboard shows no data.');
            $this->newLine();
            $this->line('   Solution: Import data with one of these commands:');
            $this->line('   • php artisan notices:import --days=90  (from Polaris)');
            $this->line('   • php artisan notices:sync-shoutbomb-to-logs --days=90  (from Shoutbomb)');
            $this->newLine();
        } else {
            $this->info("✅ notification_logs has {$totalLogs} total records");

            // Check date ranges
            $oldestDate = NotificationLog::min('notification_date');
            $newestDate = NotificationLog::max('notification_date');

            $this->table(
                ['Date Range', 'Value'],
                [
                    ['Oldest notification', Carbon::parse($oldestDate)->format('Y-m-d H:i')],
                    ['Newest notification', Carbon::parse($newestDate)->format('Y-m-d H:i')],
                    ['Dashboard looking at', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')],
                ]
            );

            // Check if dates are in the future or past
            if (Carbon::parse($newestDate)->lt($startDate)) {
                $this->warn('⚠️  WARNING: All data is OLDER than the dashboard date range!');
                $this->line('   Dashboard is looking at: ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'));
                $this->line('   But newest data is from: ' . Carbon::parse($newestDate)->format('Y-m-d'));
                $this->newLine();
            }

            // Check delivery method breakdown
            $this->newLine();
            $this->line('→ Delivery Methods in notification_logs:');
            $deliveryBreakdown = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
                ->selectRaw('delivery_option_id, COUNT(*) as count')
                ->groupBy('delivery_option_id')
                ->get();

            if ($deliveryBreakdown->isEmpty()) {
                $this->warn('  No records found in date range');
            } else {
                $deliveryOptions = config('notices.delivery_options', []);
                $this->table(
                    ['Delivery Option', 'Count'],
                    $deliveryBreakdown->map(function ($item) use ($deliveryOptions) {
                        $name = $deliveryOptions[$item->delivery_option_id] ?? "Unknown (ID: {$item->delivery_option_id})";

                        return [$name, number_format($item->count)];
                    })
                );
            }
        }

        $this->newLine();

        // === STEP 2: Check daily_notification_summary table ===
        $this->line('═══════════════════════════════════════════');
        $this->info('STEP 2: Checking daily_notification_summary table');
        $this->line('═══════════════════════════════════════════');
        $this->newLine();

        $totalSummary = DailyNotificationSummary::count();
        $summaryInRange = DailyNotificationSummary::whereBetween('summary_date', [$startDate, $endDate])->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total summary records', number_format($totalSummary)],
                ['Summary records in last ' . $days . ' days', number_format($summaryInRange)],
            ]
        );

        if ($totalSummary === 0) {
            $this->warn('⚠️  daily_notification_summary table is EMPTY!');
            $this->line('   This means analytics/trend charts won\'t work properly.');
            $this->newLine();
            $this->line('   Solution: Run aggregation:');
            $this->line('   • php artisan notices:aggregate --days=90');
            $this->newLine();
        } else {
            $oldestSummary = DailyNotificationSummary::min('summary_date');
            $newestSummary = DailyNotificationSummary::max('summary_date');

            $this->table(
                ['Date Range', 'Value'],
                [
                    ['Oldest summary', Carbon::parse($oldestSummary)->format('Y-m-d')],
                    ['Newest summary', Carbon::parse($newestSummary)->format('Y-m-d')],
                ]
            );
        }

        $this->newLine();

        // === STEP 3: Check Shoutbomb data ===
        $this->line('═══════════════════════════════════════════');
        $this->info('STEP 3: Checking Shoutbomb data (Voice/SMS)');
        $this->line('═══════════════════════════════════════════');
        $this->newLine();

        $phoneNoticesTotal = PolarisPhoneNotice::count();
        $phoneNoticesInRange = PolarisPhoneNotice::whereBetween('notice_date', [$startDate, $endDate])->count();
        $submissionsTotal = ShoutbombSubmission::count();
        $submissionsInRange = ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])->count();

        $this->table(
            ['Data Source', 'Total', 'Last ' . $days . ' days'],
            [
                ['polaris_phone_notices', number_format($phoneNoticesTotal), number_format($phoneNoticesInRange)],
                ['shoutbomb_submissions', number_format($submissionsTotal), number_format($submissionsInRange)],
            ]
        );

        if ($phoneNoticesTotal > 0 || $submissionsTotal > 0) {
            $this->newLine();
            $this->warn('⚠️  You have Shoutbomb data that is NOT in notification_logs!');
            $this->line('   This is why Voice/SMS don\'t appear on the dashboard.');
            $this->newLine();
            $this->line('   Solution: Sync Shoutbomb data to notification_logs:');
            $this->line('   • php artisan notices:sync-shoutbomb-to-logs --days=' . $days . ' --force');
            $this->newLine();
        }

        $this->newLine();

        // === STEP 4: Final Recommendations ===
        $this->line('═══════════════════════════════════════════');
        $this->info('RECOMMENDATIONS');
        $this->line('═══════════════════════════════════════════');
        $this->newLine();

        if ($totalLogs === 0) {
            $this->line('1️⃣  CRITICAL: Import notification data');
            $this->line('   Run: php artisan notices:import --days=90');
            $this->newLine();
        }

        if ($phoneNoticesTotal > 0 && $totalLogs > 0) {
            $this->line('2️⃣  Sync Shoutbomb Voice/SMS to dashboard');
            $this->line('   Run: php artisan notices:sync-shoutbomb-to-logs --days=90 --force');
            $this->newLine();
        }

        if ($totalLogs > 0 && $totalSummary === 0) {
            $this->line('3️⃣  Generate daily summaries for charts');
            $this->line('   Run: php artisan notices:aggregate --days=90');
            $this->newLine();
        }

        if ($totalLogs > 0 && $logsInRange === 0) {
            $this->line('4️⃣  Date range mismatch - import recent data');
            $this->line('   Run: php artisan notices:import --days=7');
            $this->newLine();
        }

        $this->line('═══════════════════════════════════════════');

        return Command::SUCCESS;
    }
}
