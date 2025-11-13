<?php

namespace Dcplibrary\Notices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillShoutbombDeliveryTypeCommand extends Command
{
    protected $signature = 'notices:backfill-delivery-type';

    protected $description = 'Backfill delivery_type in shoutbomb_submissions from notification_logs data';

    public function handle()
    {
        $this->info('Backfilling delivery_type in shoutbomb_submissions...');

        try {
            // Update delivery_type based on notification_logs
            // Match by patron_barcode and date (within same day)
            $updated = DB::statement("
                UPDATE shoutbomb_submissions ss
                SET delivery_type = CASE
                    WHEN (
                        SELECT delivery_option_id
                        FROM notification_logs nl
                        WHERE nl.patron_barcode = ss.patron_barcode
                        AND DATE(nl.notification_date) = DATE(ss.submitted_at)
                        AND nl.delivery_option_id IN (3, 8)
                        ORDER BY nl.notification_date DESC
                        LIMIT 1
                    ) = 3 THEN 'voice'
                    WHEN (
                        SELECT delivery_option_id
                        FROM notification_logs nl
                        WHERE nl.patron_barcode = ss.patron_barcode
                        AND DATE(nl.notification_date) = DATE(ss.submitted_at)
                        AND nl.delivery_option_id IN (3, 8)
                        ORDER BY nl.notification_date DESC
                        LIMIT 1
                    ) = 8 THEN 'text'
                END
                WHERE ss.delivery_type IS NULL
            ");

            // Count how many were updated
            $count = DB::table('shoutbomb_submissions')
                ->whereNotNull('delivery_type')
                ->count();

            $total = DB::table('shoutbomb_submissions')->count();
            $null = DB::table('shoutbomb_submissions')
                ->whereNull('delivery_type')
                ->count();

            $this->info("✓ Backfill complete");
            $this->line("  Total submissions: {$total}");
            $this->line("  With delivery_type: {$count}");
            $this->line("  Still NULL: {$null}");

            if ($null > 0) {
                $this->warn("  Note: {$null} submissions could not be matched to notification logs");
                $this->line("  These may be from dates before notification_logs were imported");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to backfill delivery_type: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
