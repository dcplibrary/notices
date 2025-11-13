<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Services\ShoutbombSubmissionParser;
use Dcplibrary\Notices\Services\ShoutbombFTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpdateDeliveryFromPatronListsCommand extends Command
{
    protected $signature = 'notices:update-delivery-from-lists 
                            {--date= : Specific date to process (Y-m-d format)}
                            {--days=30 : Number of days to process}';

    protected $description = 'Update delivery_type in shoutbomb_submissions from voice/text patron list files';

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
        $this->info('Updating delivery_type from patron list files...');
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

                // Update submissions for this date
                $updated = $this->updateSubmissions($date, $voicePatrons, $textPatrons);
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
            Log::error('Update delivery from patron lists failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Download and parse patron list file for a specific date.
     */
    protected function downloadAndParsePatronList(string $type, Carbon $date): array
    {
        try {
            // Try multiple date formats that might be in the filename
            $patterns = [
                "{$type}_patrons_submitted_{$date->format('Y-m-d')}",
                "{$type}_patrons_{$date->format('Y-m-d')}",
                "{$type}_patrons_submitted_{$date->format('Ymd')}",
            ];

            $files = $this->ftpService->listFiles('/');

            foreach ($patterns as $pattern) {
                foreach ($files as $file) {
                    $basename = basename($file);
                    
                    if (str_contains($basename, $pattern)) {
                        $localPath = $this->ftpService->downloadFile('/' . $basename);
                        if ($localPath) {
                            $patrons = $this->parser->parsePatronList($localPath);
                            $this->line("    Found patron list: {$basename} (" . count($patrons) . " patrons)");
                            return $patrons;
                        }
                    }
                }
            }

            return [];

        } catch (\Exception $e) {
            $this->warn("  Failed to download {$type} patron list: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Update submissions for a specific date based on patron lists.
     */
    protected function updateSubmissions(Carbon $date, array $voicePatrons, array $textPatrons): int
    {
        $updated = 0;

        // Get submissions for this date that don't have delivery_type set
        $submissions = DB::table('shoutbomb_submissions')
            ->whereDate('submitted_at', $date->format('Y-m-d'))
            ->whereNull('delivery_type')
            ->get();

        foreach ($submissions as $submission) {
            $barcode = $submission->patron_barcode;
            $deliveryType = null;

            // Check if patron is in voice list
            if (isset($voicePatrons[$barcode])) {
                $deliveryType = 'voice';
            } 
            // Check if patron is in text list
            elseif (isset($textPatrons[$barcode])) {
                $deliveryType = 'text';
            }

            // Update if we found a match
            if ($deliveryType) {
                DB::table('shoutbomb_submissions')
                    ->where('id', $submission->id)
                    ->update(['delivery_type' => $deliveryType]);
                $updated++;
            }
        }

        return $updated;
    }
}
