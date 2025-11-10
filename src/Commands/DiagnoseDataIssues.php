<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\ShoutbombPhoneNotice;
use Illuminate\Console\Command;

class DiagnoseDataIssues extends Command
{
    protected $signature = 'notices:diagnose-data';

    protected $description = 'Diagnose data issues - Unknown values and missing Voice/SMS notifications';

    public function handle(): int
    {
        $this->info('🔍 Diagnosing data issues...');
        $this->newLine();

        // Check for Unknown notification types
        $this->line('→ Checking for Unknown notification types:');
        $unknownTypes = NotificationLog::selectRaw('notification_type_id, COUNT(*) as count')
            ->whereNotIn('notification_type_id', array_keys(config('notices.notification_types')))
            ->groupBy('notification_type_id')
            ->get();

        if ($unknownTypes->isEmpty()) {
            $this->info('  ✅ All notification types are valid');
        } else {
            $this->warn('  ⚠️  Found Unknown notification types:');
            $this->table(
                ['Type ID', 'Count', 'Status'],
                $unknownTypes->map(fn($item) => [
                    $item->notification_type_id,
                    number_format($item->count),
                    'Not in config - will show as "Unknown"'
                ])
            );
        }

        $this->newLine();

        // Check for Unknown delivery options
        $this->line('→ Checking for Unknown delivery options:');
        $unknownDelivery = NotificationLog::selectRaw('delivery_option_id, COUNT(*) as count')
            ->whereNotIn('delivery_option_id', array_keys(config('notices.delivery_options')))
            ->groupBy('delivery_option_id')
            ->get();

        if ($unknownDelivery->isEmpty()) {
            $this->info('  ✅ All delivery options are valid');
        } else {
            $this->warn('  ⚠️  Found Unknown delivery options:');
            $this->table(
                ['Delivery ID', 'Count', 'Status'],
                $unknownDelivery->map(fn($item) => [
                    $item->delivery_option_id,
                    number_format($item->count),
                    'Not in config - will show as "Unknown"'
                ])
            );
        }

        $this->newLine();

        // Check for Unknown statuses
        $this->line('→ Checking for Unknown notification statuses:');
        $unknownStatus = NotificationLog::selectRaw('notification_status_id, COUNT(*) as count')
            ->whereNotIn('notification_status_id', array_keys(config('notices.notification_statuses')))
            ->groupBy('notification_status_id')
            ->get();

        if ($unknownStatus->isEmpty()) {
            $this->info('  ✅ All notification statuses are valid');
        } else {
            $this->warn('  ⚠️  Found Unknown notification statuses:');
            $this->table(
                ['Status ID', 'Count', 'Status'],
                $unknownStatus->map(fn($item) => [
                    $item->notification_status_id,
                    number_format($item->count),
                    'Not in config - will show as "Unknown"'
                ])
            );
        }

        $this->newLine();
        $this->line('─────────────────────────────────────────');

        // Check for Voice/SMS data discrepancy
        $this->info('📊 Voice/SMS Data Analysis:');
        $this->newLine();

        $polarisVoice = NotificationLog::where('delivery_option_id', 3)->count();
        $polarisSMS = NotificationLog::where('delivery_option_id', 8)->count();

        $shoutbombVoice = ShoutbombPhoneNotice::where('delivery_type', 'voice')->count();
        $shoutbombText = ShoutbombPhoneNotice::where('delivery_type', 'text')->count();

        $this->table(
            ['Source', 'Voice', 'Text/SMS'],
            [
                ['Polaris (notification_logs)', number_format($polarisVoice), number_format($polarisSMS)],
                ['Shoutbomb (phone_notices)', number_format($shoutbombVoice), number_format($shoutbombText)],
            ]
        );

        $this->newLine();

        if ($shoutbombVoice > 0 || $shoutbombText > 0) {
            $this->warn('⚠️  Voice/SMS notifications in Shoutbomb data are NOT included in dashboards!');
            $this->newLine();
            $this->line('The problem:');
            $this->line('  • Dashboard and Analytics pages only show data from notification_logs');
            $this->line('  • Shoutbomb Voice/SMS data is in shoutbomb_phone_notices table');
            $this->line('  • These two data sources are not merged');
            $this->newLine();
            $this->line('Solutions:');
            $this->line('  1. Run: php artisan notices:sync-shoutbomb-to-logs');
            $this->line('     (Merges Shoutbomb data into notification_logs)');
            $this->newLine();
            $this->line('  2. View Shoutbomb-specific stats at: /notices/shoutbomb');
            $this->line('     (Already shows Voice/SMS submission data)');
        } else {
            if ($polarisVoice === 0 && $polarisSMS === 0) {
                $this->warn('⚠️  No Voice or SMS notifications found in either source.');
                $this->line('     This may mean:');
                $this->line('     • Notifications haven\'t been imported yet');
                $this->line('     • Polaris isn\'t logging Voice/SMS notifications');
                $this->line('     • PhoneNotices.csv hasn\'t been imported');
            } else {
                $this->info('✅ Voice/SMS notifications are present in Polaris data');
            }
        }

        $this->newLine();

        return Command::SUCCESS;
    }
}
