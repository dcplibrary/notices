<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Illuminate\Console\Command;

class InspectDeliveryMethods extends Command
{
    protected $signature = 'notices:inspect-delivery-methods';

    protected $description = 'Inspect delivery methods in notification logs and summaries';

    public function handle(): int
    {
        $this->info('🔍 Inspecting delivery methods in database...');
        $this->newLine();

        // Check notification_logs
        $this->line('→ Checking notification_logs table:');
        $logCounts = NotificationLog::selectRaw('delivery_option_id, COUNT(*) as count')
            ->groupBy('delivery_option_id')
            ->orderBy('delivery_option_id')
            ->get();

        if ($logCounts->isEmpty()) {
            $this->warn('  No data in notification_logs table');
        } else {
            $this->table(
                ['Delivery ID', 'Delivery Method', 'Count'],
                $logCounts->map(function ($item) {
                    return [
                        $item->delivery_option_id,
                        config('notices.delivery_options')[$item->delivery_option_id] ?? 'Unknown',
                        number_format($item->count),
                    ];
                })
            );
        }

        $this->newLine();

        // Check daily_notification_summary
        $this->line('→ Checking daily_notification_summary table:');
        $summaryCounts = DailyNotificationSummary::selectRaw('delivery_option_id, SUM(total_sent) as total')
            ->groupBy('delivery_option_id')
            ->orderBy('delivery_option_id')
            ->get();

        if ($summaryCounts->isEmpty()) {
            $this->warn('  No data in daily_notification_summary table');
            $this->line('  Run: php artisan notices:aggregate');
        } else {
            $this->table(
                ['Delivery ID', 'Delivery Method', 'Total Sent'],
                $summaryCounts->map(function ($item) {
                    return [
                        $item->delivery_option_id,
                        config('notices.delivery_options')[$item->delivery_option_id] ?? 'Unknown',
                        number_format($item->total),
                    ];
                })
            );
        }

        $this->newLine();

        // Check for Voice specifically
        $voiceCount = NotificationLog::where('delivery_option_id', 3)->count();
        $smsCount = NotificationLog::where('delivery_option_id', 8)->count();

        $this->line('─────────────────────────────────────────');
        $this->info('Voice & SMS Summary:');
        $this->line('─────────────────────────────────────────');
        $this->line("Voice (ID 3): " . number_format($voiceCount) . " notifications");
        $this->line("SMS (ID 8): " . number_format($smsCount) . " notifications");

        if ($voiceCount === 0 && $smsCount === 0) {
            $this->newLine();
            $this->warn('⚠️  No Voice or SMS notifications found in Polaris data.');
            $this->line('');
            $this->line('Possible reasons:');
            $this->line('  1. Polaris is not logging Voice/SMS notifications');
            $this->line('  2. No Voice/SMS notifications have been imported yet');
            $this->line('  3. Voice/SMS notifications use a different delivery option ID');
            $this->line('');
            $this->line('Note: Shoutbomb submission data is tracked separately.');
            $this->line('      View Shoutbomb stats at: /notices/shoutbomb');
        }

        $this->newLine();

        return Command::SUCCESS;
    }
}
