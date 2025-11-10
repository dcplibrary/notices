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

        // Check if Shoutbomb data exists for this notification
        $this->info("Checking ShoutbombPhoneNotice for this patron...");

        $phoneNotices = ShoutbombPhoneNotice::where('patron_barcode', $notification->patron_barcode)
            ->orderBy('notice_date', 'desc')
            ->limit(5)
            ->get();

        if ($phoneNotices->isEmpty()) {
            $this->error("  ✗ No ShoutbombPhoneNotice records found for barcode: {$notification->patron_barcode}");

            // Check if ANY Shoutbomb data exists
            $totalShoutbomb = ShoutbombPhoneNotice::count();
            $this->warn("  Total ShoutbombPhoneNotice records in database: {$totalShoutbomb}");

            if ($totalShoutbomb > 0) {
                $sample = ShoutbombPhoneNotice::first();
                $this->info("  Sample record - Barcode: {$sample->patron_barcode}, Date: {$sample->notice_date}");
            }
        } else {
            $this->info("  ✓ Found {$phoneNotices->count()} ShoutbombPhoneNotice records:");
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
            $exactMatch = ShoutbombPhoneNotice::where('patron_barcode', $notification->patron_barcode)
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
