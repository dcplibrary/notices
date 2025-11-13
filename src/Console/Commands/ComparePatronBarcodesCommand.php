<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Services\ShoutbombSubmissionParser;
use Dcplibrary\Notices\Services\ShoutbombFTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ComparePatronBarcodesCommand extends Command
{
    protected $signature = 'notices:compare-barcodes {--date=}';

    protected $description = 'Compare patron barcodes between submissions and patron lists';

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
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDays(1);
        
        $this->info("Comparing barcodes for {$date->format('Y-m-d')}...");
        $this->newLine();

        try {
            // Get submissions for this date
            $submissions = DB::table('shoutbomb_submissions')
                ->whereDate('submitted_at', $date->format('Y-m-d'))
                ->limit(10)
                ->get();

            if ($submissions->isEmpty()) {
                $this->warn('No submissions found for this date');
                
                // Show available dates
                $availableDates = DB::table('shoutbomb_submissions')
                    ->selectRaw('DATE(submitted_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->limit(10)
                    ->get();
                
                $this->newLine();
                $this->line('Available dates with submissions:');
                foreach ($availableDates as $d) {
                    $this->line("  {$d->date}: {$d->count} submissions");
                }
                
                return Command::SUCCESS;
            }

            $this->info("Sample patron barcodes from submissions:");
            foreach ($submissions as $i => $sub) {
                $barcode = $sub->patron_barcode;
                $this->line(sprintf(
                    "  %d. '%s' (len:%d, type:%s, source:%s)",
                    $i + 1,
                    $barcode,
                    strlen($barcode),
                    gettype($barcode),
                    $sub->source_file
                ));
            }

            $this->newLine();

            // Connect to FTP and get patron lists
            if (!$this->ftpService->connect()) {
                $this->error('Failed to connect to FTP');
                return Command::FAILURE;
            }

            // Download patron lists
            $voicePatrons = $this->downloadAndParsePatronList('voice', $date);
            $textPatrons = $this->downloadAndParsePatronList('text', $date);

            $this->ftpService->disconnect();

            if (!empty($voicePatrons)) {
                $this->info("Sample barcodes from VOICE patron list:");
                $count = 0;
                foreach ($voicePatrons as $barcode => $phone) {
                    if ($count++ >= 10) break;
                    $this->line(sprintf(
                        "  %d. '%s' (len:%d, phone:%s)",
                        $count,
                        $barcode,
                        strlen($barcode),
                        $phone
                    ));
                }
                $this->newLine();
            }

            if (!empty($textPatrons)) {
                $this->info("Sample barcodes from TEXT patron list:");
                $count = 0;
                foreach ($textPatrons as $barcode => $phone) {
                    if ($count++ >= 10) break;
                    $this->line(sprintf(
                        "  %d. '%s' (len:%d, phone:%s)",
                        $count,
                        $barcode,
                        strlen($barcode),
                        $phone
                    ));
                }
                $this->newLine();
            }

            // Try to find any matches
            $this->info("Checking for matches...");
            $voiceMatches = 0;
            $textMatches = 0;
            
            foreach ($submissions as $sub) {
                if (isset($voicePatrons[$sub->patron_barcode])) {
                    $voiceMatches++;
                    $this->line("  ✓ MATCH: '{$sub->patron_barcode}' found in voice list");
                }
                if (isset($textPatrons[$sub->patron_barcode])) {
                    $textMatches++;
                    $this->line("  ✓ MATCH: '{$sub->patron_barcode}' found in text list");
                }
            }

            $this->newLine();
            $this->line("Matches found: Voice={$voiceMatches}, Text={$textMatches} out of " . $submissions->count() . " submissions checked");

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
}
