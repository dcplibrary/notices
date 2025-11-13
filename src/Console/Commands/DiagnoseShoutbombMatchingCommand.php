<?php

namespace Dcplibrary\Notices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseShoutbombMatchingCommand extends Command
{
    protected $signature = 'notices:diagnose-shoutbomb';

    protected $description = 'Diagnose why shoutbomb_submissions are not matching notification_logs';

    public function handle()
    {
        $this->info('Diagnosing shoutbomb_submissions matching...');
        $this->newLine();

        // Check date ranges
        $this->info('Date Ranges:');
        
        $submissionDates = DB::table('shoutbomb_submissions')
            ->selectRaw('MIN(DATE(submitted_at)) as min_date, MAX(DATE(submitted_at)) as max_date')
            ->first();
        
        $this->line("  Shoutbomb submissions: {$submissionDates->min_date} to {$submissionDates->max_date}");
        
        $notificationDates = DB::table('notification_logs')
            ->selectRaw('MIN(DATE(notification_date)) as min_date, MAX(DATE(notification_date)) as max_date')
            ->first();
        
        $this->line("  Notification logs: {$notificationDates->min_date} to {$notificationDates->max_date}");
        $this->newLine();

        // Check sample patron barcodes from both tables
        $this->info('Sample Patron Barcodes:');
        
        $sampleSubmissions = DB::table('shoutbomb_submissions')
            ->select('patron_barcode')
            ->distinct()
            ->limit(5)
            ->pluck('patron_barcode');
        
        $this->line("  Shoutbomb submissions samples:");
        foreach ($sampleSubmissions as $barcode) {
            $this->line("    - '{$barcode}' (length: " . strlen($barcode) . ")");
        }
        
        $sampleNotifications = DB::table('notification_logs')
            ->select('patron_barcode')
            ->whereNotNull('patron_barcode')
            ->distinct()
            ->limit(5)
            ->pluck('patron_barcode');
        
        $this->line("  Notification logs samples:");
        foreach ($sampleNotifications as $barcode) {
            $this->line("    - '{$barcode}' (length: " . strlen($barcode) . ")");
        }
        $this->newLine();

        // Check if there are any matching barcodes at all
        $matchingBarcodes = DB::table('shoutbomb_submissions as ss')
            ->join('notification_logs as nl', 'ss.patron_barcode', '=', 'nl.patron_barcode')
            ->count();
        
        $this->info("Matching Records:");
        $this->line("  Total records with same patron_barcode: {$matchingBarcodes}");
        
        if ($matchingBarcodes > 0) {
            // Check if dates overlap
            $matchingWithDate = DB::table('shoutbomb_submissions as ss')
                ->join('notification_logs as nl', function($join) {
                    $join->on('ss.patron_barcode', '=', 'nl.patron_barcode')
                         ->whereRaw('DATE(ss.submitted_at) = DATE(nl.notification_date)');
                })
                ->count();
            
            $this->line("  With matching dates: {$matchingWithDate}");
            
            // Check delivery_option_id distribution
            $deliveryOptions = DB::table('notification_logs')
                ->selectRaw('delivery_option_id, COUNT(*) as count')
                ->groupBy('delivery_option_id')
                ->get();
            
            $this->newLine();
            $this->info('Notification Logs Delivery Options:');
            foreach ($deliveryOptions as $option) {
                $this->line("  delivery_option_id {$option->delivery_option_id}: {$option->count}");
            }
        }
        
        $this->newLine();

        // Check notification_logs for phone-related delivery methods
        $phoneNotifications = DB::table('notification_logs')
            ->whereIn('delivery_option_id', [3, 8])
            ->count();
        
        $this->info("Phone Notifications in notification_logs:");
        $this->line("  Voice (3) or SMS (8): {$phoneNotifications}");
        
        return Command::SUCCESS;
    }
}
