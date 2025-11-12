<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Dcplibrary\Notices\Models\ShoutbombKeywordUsage;
use Dcplibrary\Notices\Models\ShoutbombRegistration;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SeedDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:seed-demo
                            {--days=30 : Number of days of historical data to generate}
                            {--fresh : Truncate existing data before seeding}';

    /**
     * The console command description.
     */
    protected $description = 'Seed the database with realistic demo data for testing and visualization';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (!$this->confirm('This will delete all existing notification data. Are you sure?')) {
                $this->info('Demo seeding cancelled.');
                return 0;
            }

            $this->info('Truncating existing data...');
            NotificationLog::truncate();
            DailyNotificationSummary::truncate();
            ShoutbombDelivery::truncate();
            ShoutbombKeywordUsage::truncate();
            ShoutbombRegistration::truncate();
        }

        $days = (int) $this->option('days');
        $this->info("Generating {$days} days of demo data...");

        $startDate = now()->subDays($days);
        $bar = $this->output->createProgressBar($days);

        $stats = [
            'notification_logs' => 0,
            'daily_summaries' => 0,
            'shoutbomb_deliveries' => 0,
            'keyword_usage' => 0,
            'registrations' => 0,
        ];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Generate notification logs for this day
            $stats['notification_logs'] += $this->generateNotificationLogs($date);

            // Generate daily summaries
            $stats['daily_summaries'] += $this->generateDailySummaries($date);

            // Generate Shoutbomb deliveries (SMS/Voice)
            $stats['shoutbomb_deliveries'] += $this->generateShoutbombDeliveries($date);

            // Generate keyword usage (every day)
            $stats['keyword_usage'] += $this->generateKeywordUsage($date);

            // Generate registration snapshots (weekly)
            if ($date->dayOfWeek === 0) { // Sunday
                $stats['registrations'] += $this->generateRegistrations($date);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Demo data seeded successfully!');
        $this->newLine();
        $this->table(
            ['Data Type', 'Records Created'],
            [
                ['Notification Logs', number_format($stats['notification_logs'])],
                ['Daily Summaries', number_format($stats['daily_summaries'])],
                ['Shoutbomb Deliveries', number_format($stats['shoutbomb_deliveries'])],
                ['Keyword Usage', number_format($stats['keyword_usage'])],
                ['Registration Snapshots', number_format($stats['registrations'])],
            ]
        );

        return 0;
    }

    /**
     * Generate notification logs for a specific date.
     */
    protected function generateNotificationLogs(Carbon $date): int
    {
        $count = 0;

        // Email holds (high volume, high success)
        NotificationLog::factory()
            ->count(rand(30, 50))
            ->email()
            ->holds()
            ->successful()
            ->create(['notification_date' => $date]);
        $count += 40;

        // Email overdues (medium volume, high success)
        NotificationLog::factory()
            ->count(rand(20, 30))
            ->email()
            ->overdues()
            ->successful()
            ->create(['notification_date' => $date]);
        $count += 25;

        // SMS holds (medium volume, good success)
        NotificationLog::factory()
            ->count(rand(15, 25))
            ->sms()
            ->holds()
            ->successful()
            ->create(['notification_date' => $date]);
        $count += 20;

        // SMS with some failures
        NotificationLog::factory()
            ->count(rand(2, 5))
            ->sms()
            ->holds()
            ->failed()
            ->create(['notification_date' => $date]);
        $count += 3;

        // Voice notifications (lower volume)
        NotificationLog::factory()
            ->count(rand(5, 10))
            ->voice()
            ->overdues()
            ->successful()
            ->create(['notification_date' => $date]);
        $count += 7;

        return $count;
    }

    /**
     * Generate daily summaries for a specific date.
     */
    protected function generateDailySummaries(Carbon $date): int
    {
        $count = 0;

        // Email holds summary
        DailyNotificationSummary::factory()
            ->email()
            ->holds()
            ->highSuccess()
            ->create(['summary_date' => $date]);
        $count++;

        // Email overdues summary
        DailyNotificationSummary::factory()
            ->email()
            ->overdues()
            ->highSuccess()
            ->create(['summary_date' => $date]);
        $count++;

        // SMS holds summary
        DailyNotificationSummary::factory()
            ->sms()
            ->holds()
            ->create(['summary_date' => $date]);
        $count++;

        // Voice overdues summary
        DailyNotificationSummary::factory()
            ->voice()
            ->overdues()
            ->create(['summary_date' => $date]);
        $count++;

        return $count;
    }

    /**
     * Generate Shoutbomb deliveries for a specific date.
     */
    protected function generateShoutbombDeliveries(Carbon $date): int
    {
        $count = 0;

        // Successful SMS deliveries
        ShoutbombDelivery::factory()
            ->count(rand(15, 25))
            ->sms()
            ->delivered()
            ->create(['sent_date' => $date]);
        $count += 20;

        // Failed SMS deliveries
        ShoutbombDelivery::factory()
            ->count(rand(1, 3))
            ->sms()
            ->failed()
            ->create(['sent_date' => $date]);
        $count += 2;

        // Opted out (from email report)
        ShoutbombDelivery::factory()
            ->count(rand(0, 2))
            ->sms()
            ->optedOut()
            ->create(['sent_date' => $date]);
        $count += 1;

        // Invalid number from email report
        ShoutbombDelivery::factory()
            ->count(rand(0, 1))
            ->sms()
            ->invalid()
            ->create(['sent_date' => $date]);
        $count += 1;

        // Successful voice deliveries
        ShoutbombDelivery::factory()
            ->count(rand(5, 10))
            ->voice()
            ->delivered()
            ->create(['sent_date' => $date]);
        $count += 7;

        // Undelivered voice from email report
        ShoutbombDelivery::factory()
            ->count(rand(0, 2))
            ->voice()
            ->emailUndeliveredVoice()
            ->create(['sent_date' => $date]);
        $count += 1;

        return $count;
    }

    /**
     * Generate keyword usage for a specific date.
     */
    protected function generateKeywordUsage(Carbon $date): int
    {
        $count = 0;

        $keywords = [
            ['factory_state' => 'holds', 'usage_range' => [50, 100]],
            ['factory_state' => 'renew', 'usage_range' => [30, 60]],
            ['factory_state' => 'checkouts', 'usage_range' => [20, 40]],
            ['factory_state' => 'fines', 'usage_range' => [10, 25]],
            ['factory_state' => 'help', 'usage_range' => [5, 15]],
        ];

        foreach ($keywords as $keyword) {
            ShoutbombKeywordUsage::factory()
                ->{$keyword['factory_state']}()
                ->create([
                    'usage_date' => $date,
                    'usage_count' => rand($keyword['usage_range'][0], $keyword['usage_range'][1]),
                ]);
            $count++;
        }

        return $count;
    }

    /**
     * Generate registration snapshots.
     */
    protected function generateRegistrations(Carbon $date): int
    {
        ShoutbombRegistration::factory()
            ->growing()
            ->textDominant()
            ->create(['snapshot_date' => $date]);

        return 1;
    }
}
