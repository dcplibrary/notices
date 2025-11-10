<?php

namespace Dcplibrary\Notices\Console\Commands;

use Illuminate\Console\Command;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\ShoutbombPhoneNotice;

class DiagnosePatronDataCommand extends Command
{
    protected $signature = 'notices:diagnose-patron-data';
    protected $description = 'Diagnose why patron names and item titles are not showing';

    public function handle()
    {
        $this->info('=== PATRON DATA DIAGNOSIS ===');
        $this->newLine();

        // Check date ranges
        $this->info('Date Range Analysis:');

        $notificationRange = [
            'min' => NotificationLog::min('notification_date'),
            'max' => NotificationLog::max('notification_date'),
            'count' => NotificationLog::count(),
        ];

        $shoutbombRange = [
            'min' => ShoutbombPhoneNotice::min('notice_date'),
            'max' => ShoutbombPhoneNotice::max('notice_date'),
            'count' => ShoutbombPhoneNotice::count(),
        ];

        $this->table(
            ['Source', 'Earliest', 'Latest', 'Count'],
            [
                ['notification_logs', $notificationRange['min'], $notificationRange['max'], $notificationRange['count']],
                ['shoutbomb_phone_notices', $shoutbombRange['min'], $shoutbombRange['max'], $shoutbombRange['count']],
            ]
        );

        $this->newLine();

        // Find overlapping date
        $overlapDate = ShoutbombPhoneNotice::whereBetween('notice_date', [$notificationRange['min'], $notificationRange['max']])
            ->orderBy('notice_date', 'desc')
            ->value('notice_date');

        if ($overlapDate) {
            $this->info("✓ Found overlapping date: {$overlapDate}");

            // Get a notification from that date
            $notification = NotificationLog::whereDate('notification_date', $overlapDate)
                ->first();

            if ($notification) {
                $this->newLine();
                $this->info("Sample Notification from overlapping date:");
                $this->line("  ID: {$notification->id}");
                $this->line("  Date: {$notification->notification_date}");
                $this->line("  Patron Barcode: {$notification->patron_barcode}");
                $this->line("  Patron ID: {$notification->patron_id}");
                $this->line("  Type: {$notification->notification_type_name}");
                $this->newLine();

                // Check for matching Shoutbomb data
                $phoneNotice = ShoutbombPhoneNotice::where('patron_barcode', $notification->patron_barcode)
                    ->whereDate('notice_date', $overlapDate)
                    ->first();

                if ($phoneNotice) {
                    $this->info("✓ MATCH FOUND!");
                    $this->line("  Name: {$phoneNotice->first_name} {$phoneNotice->last_name}");
                    $this->line("  Title: " . substr($phoneNotice->title, 0, 50));

                    // Test accessor
                    $patronName = $notification->patron_name;
                    $this->newLine();
                    $this->info("Accessor Result: '{$patronName}'");

                    if (empty($patronName)) {
                        $this->error("✗ Accessor returned empty string!");
                        $this->warn("Debugging accessor...");

                        // Check Polaris connection
                        try {
                            $patron = $notification->patron;
                            if ($patron) {
                                $this->line("  Polaris patron found: {$patron->FormattedName}");
                            } else {
                                $this->line("  Polaris patron: NULL");
                            }
                        } catch (\Exception $e) {
                            $this->error("  Polaris error: {$e->getMessage()}");
                        }
                    } else {
                        $this->info("✓ Accessor working correctly!");
                    }
                } else {
                    $this->error("✗ No matching ShoutbombPhoneNotice for this barcode");

                    // Show what barcodes exist for this date
                    $this->newLine();
                    $this->info("Barcodes in shoutbomb_phone_notices for {$overlapDate}:");
                    $barcodes = ShoutbombPhoneNotice::whereDate('notice_date', $overlapDate)
                        ->limit(10)
                        ->get(['patron_barcode', 'first_name', 'last_name']);

                    foreach ($barcodes as $bc) {
                        $this->line("  {$bc->patron_barcode} - {$bc->first_name} {$bc->last_name}");
                    }
                }
            }
        } else {
            $this->error("✗ No overlapping dates between notification_logs and shoutbomb_phone_notices");
            $this->warn("The data imports are from completely different date ranges!");
        }

        $this->newLine();
        $this->info('Checking for other matching strategies...');

        // Check if we should match on patron_id instead
        $notificationWithId = NotificationLog::whereNotNull('patron_id')
            ->whereNotNull('patron_barcode')
            ->first();

        if ($notificationWithId) {
            $this->line("Sample patron_id: {$notificationWithId->patron_id}");
            $this->line("Sample patron_barcode: {$notificationWithId->patron_barcode}");
        }

        return 0;
    }
}
