<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Exception;
use Illuminate\Console\Command;

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
            'min' => PolarisPhoneNotice::min('notice_date'),
            'max' => PolarisPhoneNotice::max('notice_date'),
            'count' => PolarisPhoneNotice::count(),
        ];

        $this->table(
            ['Source', 'Earliest', 'Latest', 'Count'],
            [
                ['notification_logs', $notificationRange['min'], $notificationRange['max'], $notificationRange['count']],
                ['polaris_phone_notices', $shoutbombRange['min'], $shoutbombRange['max'], $shoutbombRange['count']],
            ]
        );

        $this->newLine();

        // Find overlapping date
        $overlapDate = PolarisPhoneNotice::whereBetween('notice_date', [$notificationRange['min'], $notificationRange['max']])
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

                // Check for matching Polaris data
                $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $notification->patron_barcode)
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
                        } catch (Exception $e) {
                            $this->error("  Polaris error: {$e->getMessage()}");
                        }
                    } else {
                        $this->info("✓ Accessor working correctly!");
                    }
                } else {
                    $this->error("✗ No matching PolarisPhoneNotice for this barcode");

                    // Show what barcodes exist for this date
                    $this->newLine();
                    $this->info("Barcodes in polaris_phone_notices for {$overlapDate}:");
                    $barcodes = PolarisPhoneNotice::whereDate('notice_date', $overlapDate)
                        ->limit(10)
                        ->get(['patron_barcode', 'first_name', 'last_name']);

                    foreach ($barcodes as $bc) {
                        $this->line("  {$bc->patron_barcode} - {$bc->first_name} {$bc->last_name}");
                    }
                }
            }
        } else {
            $this->error("✗ No overlapping dates between notification_logs and polaris_phone_notices");
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

        $this->info('Checking recent notifications and Shoutbomb data...');
        $this->newLine();

        // Get a recent notification
        $notification = NotificationLog::orderBy('notification_date', 'desc')->first();

        if (!$notification) {
            $this->error('No notifications found in database');

            return 1;
        }

        $this->info("Sample Notification:");
        $this->line("  ID: {$notification->id}");
        $this->line("  Date: {$notification->notification_date}");
        $this->line("  Patron Barcode: {$notification->patron_barcode}");
        $this->line("  Patron ID: {$notification->patron_id}");
        $this->line("  Type: {$notification->notification_type_name}");
        $this->newLine();

        // Check if Polaris phone notice data exists for this notification
        $this->info("Checking PolarisPhoneNotice for this patron...");

        $phoneNotices = PolarisPhoneNotice::where('patron_barcode', $notification->patron_barcode)
            ->orderBy('notice_date', 'desc')
            ->limit(5)
            ->get();

        if ($phoneNotices->isEmpty()) {
            $this->error("  ✗ No PolarisPhoneNotice records found for barcode: {$notification->patron_barcode}");

            // Check if ANY Polaris phone notice data exists
            $totalPolaris = PolarisPhoneNotice::count();
            $this->warn("  Total PolarisPhoneNotice records in database: {$totalPolaris}");

            if ($totalPolaris > 0) {
                $sample = PolarisPhoneNotice::first();
                $this->info("  Sample record - Barcode: {$sample->patron_barcode}, Date: {$sample->notice_date}");
            }
        } else {
            $this->info("  ✓ Found {$phoneNotices->count()} PolarisPhoneNotice records:");
            foreach ($phoneNotices as $pn) {
                $this->line("    - Date: {$pn->notice_date}, Name: {$pn->first_name} {$pn->last_name}, Title: " . substr($pn->title, 0, 40));
            }
        }

        $this->newLine();

        // Test the accessor
        $this->info("Testing patron_name accessor:");
        $patronName = $notification->patron_name;
        $this->line("  Result: {$patronName}");

        if ($patronName === $notification->patron_barcode || $patronName === 'Unknown Patron') {
            $this->error("  ✗ Accessor returned barcode/unknown instead of name");

            // Check date matching
            $exactMatch = PolarisPhoneNotice::where('patron_barcode', $notification->patron_barcode)
                ->whereDate('notice_date', $notification->notification_date->format('Y-m-d'))
                ->first();

            if ($exactMatch) {
                $this->info("  ✓ Exact date match found: {$exactMatch->first_name} {$exactMatch->last_name}");
            } else {
                $this->warn("  ✗ No exact date match found");
                $this->line("    Looking for: " . $notification->notification_date->format('Y-m-d'));
            }
        } else {
            $this->info("  ✓ Accessor returned proper name: {$patronName}");
        }

        $this->newLine();

        // Test items accessor
        $this->info("Testing items accessor:");
        $items = $notification->items;
        $this->line("  Items found: {$items->count()}");

        if ($items->isNotEmpty()) {
            $firstItem = $items->first();
            $title = $firstItem->bibliographic->Title ?? $firstItem->title ?? 'No title';
            $this->info("  ✓ First item: " . substr($title, 0, 60));
        } else {
            $this->warn("  ✗ No items found");
        }

        return 0;
    }
}
