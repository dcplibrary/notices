<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Services\ShoutbombSubmissionParser;
use Dcplibrary\Notices\Services\ShoutbombFTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateDeliveryViaPatronIdCommand extends Command
{
    protected $signature = 'notices:update-delivery-via-patronid 
                            {--date= : Specific date to process (Y-m-d format)}
                            {--days=30 : Number of days to process}';

    protected $description = 'Update delivery_type by matching PatronID (in submissions) to PatronID in Polaris, then to patron lists';

    protected ShoutbombSubmissionParser $parser;
    protected ShoutbombFTPService $ftpService;

    public function __construct(ShoutbombSubmissionParser $parser, ShoutbombFTPService $ftpService)
    {
        parent::__construct();
        $this->parser = $parser;
        $this->ftpService = $ftpService;
    }

    public function handle()
    {
        $this->info('Updating delivery_type via PatronID lookup...');
        $this->newLine();

        try {
            // Connect to FTP
            if (!$this->ftpService->connect()) {
                $this->error('Failed to connect to FTP server');
                return Command::FAILURE;
            }

            // Determine date range
            if ($this->option('date')) {
                $dates = [Carbon::parse($this->option('date'))];
            } else {
                $days = $this->option('days');
                $dates = [];
                for ($i = 0; $i < $days; $i++) {
                    $dates[] = now()->subDays($i);
                }
            }

            $totalUpdated = 0;

            foreach ($dates as $date) {
                $this->line("Processing {$date->format('Y-m-d')}...");
                
                // Download patron lists for this date
                $voicePatrons = $this->downloadAndParsePatronList('voice', $date);
                $textPatrons = $this->downloadAndParsePatronList('text', $date);

                if (empty($voicePatrons) && empty($textPatrons)) {
                    $this->warn("  No patron lists found for {$date->format('Y-m-d')}");
                    continue;
                }

                $this->line("  Found " . count($voicePatrons) . " voice patrons, " . count($textPatrons) . " text patrons");

                // Build a lookup: PatronID => delivery_type
                $patronIdToDelivery = $this->buildPatronIdLookup($voicePatrons, $textPatrons);
                
                $this->line("  Built lookup for " . count($patronIdToDelivery) . " patron IDs");

                // Update submissions for this date
                $updated = $this->updateSubmissionsViaPatronId($date, $patronIdToDelivery);
                $totalUpdated += $updated;

                if ($updated > 0) {
                    $this->info("  ✓ Updated {$updated} submissions");
                }
            }

            $this->ftpService->disconnect();

            $this->newLine();
            $this->info("✓ Complete! Updated {$totalUpdated} total submissions");

            // Show final stats
            $stats = DB::table('shoutbomb_submissions')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN delivery_type = "voice" THEN 1 ELSE 0 END) as voice,
                    SUM(CASE WHEN delivery_type = "text" THEN 1 ELSE 0 END) as text,
                    SUM(CASE WHEN delivery_type IS NULL THEN 1 ELSE 0 END) as null_count
                ')
                ->first();

            $this->newLine();
            $this->line("Final Statistics:");
            $this->line("  Total submissions: {$stats->total}");
            $this->line("  Voice: {$stats->voice}");
            $this->line("  Text: {$stats->text}");
            $this->line("  NULL: {$stats->null_count}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function downloadAndParsePatronList(string $type, Carbon $date): array
    {
        try {
            $patterns = [
                "{$type}_patrons_submitted_{$date->format('Y-m-d')}",
                "{$type}_patrons_{$date->format('Y-m-d')}",
            ];

            $files = $this->ftpService->listFiles('/');

            foreach ($patterns as $pattern) {
                foreach ($files as $file) {
                    $basename = basename($file);
                    
                    if (str_contains($basename, $pattern)) {
                        $localPath = $this->ftpService->downloadFile('/' . $basename);
                        if ($localPath) {
                            return $this->parser->parsePatronList($localPath);
                        }
                    }
                }
            }

            return [];

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Build a lookup from PatronID to delivery_type by querying Polaris.
     */
    protected function buildPatronIdLookup(array $voicePatrons, array $textPatrons): array
    {
        $lookup = [];

        // Get all barcodes from both lists
        $allBarcodes = array_merge(array_keys($voicePatrons), array_keys($textPatrons));

        if (empty($allBarcodes)) {
            return [];
        }

        // Query Polaris to get PatronID for each barcode
        $barcodeToPatronId = DB::connection('polaris')
            ->table('Polaris.Polaris.Patrons')
            ->whereIn('Barcode', $allBarcodes)
            ->pluck('PatronID', 'Barcode')
            ->toArray();

        // Build PatronID => delivery_type lookup
        foreach ($voicePatrons as $barcode => $phone) {
            if (isset($barcodeToPatronId[$barcode])) {
                $patronId = $barcodeToPatronId[$barcode];
                $lookup[$patronId] = 'voice';
            }
        }

        foreach ($textPatrons as $barcode => $phone) {
            if (isset($barcodeToPatronId[$barcode])) {
                $patronId = $barcodeToPatronId[$barcode];
                $lookup[$patronId] = 'text';
            }
        }

        return $lookup;
    }

    /**
     * Update submissions by matching their patron_barcode (which is actually PatronID) to lookup.
     */
    protected function updateSubmissionsViaPatronId(Carbon $date, array $patronIdToDelivery): int
    {
        $updated = 0;

        // Get submissions for this date that don't have delivery_type set
        $submissions = DB::table('shoutbomb_submissions')
            ->whereDate('submitted_at', $date->format('Y-m-d'))
            ->whereNull('delivery_type')
            ->get();

        foreach ($submissions as $submission) {
            $patronId = (int) $submission->patron_barcode; // patron_barcode field contains PatronID
            
            if (isset($patronIdToDelivery[$patronId])) {
                $deliveryType = $patronIdToDelivery[$patronId];
                
                DB::table('shoutbomb_submissions')
                    ->where('id', $submission->id)
                    ->update(['delivery_type' => $deliveryType]);
                $updated++;
            }
        }

        return $updated;
    }
}
