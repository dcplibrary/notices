<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\ShoutbombSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ShoutbombSubmissionImporter
{
    protected ShoutbombSubmissionParser $parser;
    protected ShoutbombFTPService $ftpService;

    public function __construct(ShoutbombSubmissionParser $parser, ShoutbombFTPService $ftpService)
    {
        $this->parser = $parser;
        $this->ftpService = $ftpService;
    }

    /**
     * Import all submission files from FTP.
     *
     * This imports the OFFICIAL SQL-generated submission files that are
     * sent to Shoutbomb (holds, overdue, renew).
     */
    public function importFromFTP(?Carbon $startDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(1);

        Log::info("Starting Shoutbomb submission import (official system)", [
            'start_date' => $startDate->format('Y-m-d'),
        ]);

        $results = [
            'holds' => 0,
            'overdues' => 0,
            'renewals' => 0,
            'voice_patrons' => 0,
            'text_patrons' => 0,
            'errors' => 0,
        ];

        try {
            // Connect to FTP
            if (!$this->ftpService->connect()) {
                throw new \Exception('Failed to connect to FTP');
            }

            // Download and process patron lists
            $voicePatrons = $this->downloadAndParsePatronList('voice', $startDate);
            $textPatrons = $this->downloadAndParsePatronList('text', $startDate);

            $results['voice_patrons'] = count($voicePatrons);
            $results['text_patrons'] = count($textPatrons);

            // Import holds
            $results['holds'] = $this->importSubmissionType('holds', $startDate, $voicePatrons, $textPatrons);

            // Import overdues
            $results['overdues'] = $this->importSubmissionType('overdue', $startDate, $voicePatrons, $textPatrons);

            // Import renewals
            $results['renewals'] = $this->importSubmissionType('renew', $startDate, $voicePatrons, $textPatrons);

            $this->ftpService->disconnect();

        } catch (\Exception $e) {
            Log::error("Shoutbomb submission import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Download and parse patron list file.
     */
    protected function downloadAndParsePatronList(string $type, Carbon $date): array
    {
        try {
            // Find patron list file for the date
            $pattern = "{$type}_patrons_submitted_{$date->format('Y-m-d')}";
            $files = $this->ftpService->listFiles('/');

            Log::info("Looking for patron list", [
                'pattern' => $pattern,
                'files_found' => count($files),
            ]);

            foreach ($files as $file) {
                $basename = basename($file);

                if (str_contains($basename, $pattern)) {
                    // Use basename to avoid path issues
                    $localPath = $this->ftpService->downloadFile('/' . $basename);
                    if ($localPath) {
                        Log::info("Found and downloaded patron list", [
                            'type' => $type,
                            'file' => $basename,
                            'records' => count($this->parser->parsePatronList($localPath)),
                        ]);
                        return $this->parser->parsePatronList($localPath);
                    }
                }
            }

            Log::warning("Patron list file not found", [
                'type' => $type,
                'date' => $date->format('Y-m-d'),
                'pattern' => $pattern,
                'total_files' => count($files),
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error("Failed to download patron list", [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Import submissions of a specific type.
     */
    protected function importSubmissionType(
        string $type,
        Carbon $date,
        array $voicePatrons,
        array $textPatrons
    ): int {
        $imported = 0;

        try {
            // Find submission files
            $pattern = "{$type}_submitted_{$date->format('Y-m-d')}";
            $files = $this->ftpService->listFiles('/');

            Log::info("Looking for submission files", [
                'type' => $type,
                'pattern' => $pattern,
                'files_found' => count($files),
            ]);

            foreach ($files as $file) {
                $basename = basename($file);

                if (str_contains($basename, $pattern)) {
                    // Use basename to avoid path issues
                    $localPath = $this->ftpService->downloadFile('/' . $basename);

                    if ($localPath) {
                        $count = $this->processSubmissionFile($localPath, $basename, $type, $voicePatrons, $textPatrons);
                        $imported += $count;

                        Log::info("Imported {$type} submissions", [
                            'file' => $basename,
                            'count' => $count,
                        ]);
                    }
                }
            }

            if ($imported === 0) {
                Log::warning("No {$type} submission files found", [
                    'pattern' => $pattern,
                    'total_files' => count($files),
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Failed to import {$type} submissions", [
                'error' => $e->getMessage(),
            ]);
        }

        return $imported;
    }

    /**
     * Process a single submission file.
     */
    protected function processSubmissionFile(
        string $filePath,
        string $filename,
        string $type,
        array $voicePatrons,
        array $textPatrons
    ): int {
        // Parse file based on type
        $submissions = match($type) {
            'holds' => $this->parser->parseHoldsFile($filePath),
            'overdue' => $this->parser->parseOverdueFile($filePath),
            'renew' => $this->parser->parseRenewFile($filePath),
            default => [],
        };

        $submittedAt = $this->parser->extractTimestampFromFilename($filename);

        $imported = 0;
        $batch = [];

        foreach ($submissions as $submission) {
            // Determine delivery type (voice or text) based on patron lists
            $patronBarcode = $submission['patron_barcode'];
            $deliveryType = null;

            if (isset($voicePatrons[$patronBarcode])) {
                $deliveryType = 'voice';
            } elseif (isset($textPatrons[$patronBarcode])) {
                $deliveryType = 'text';
            }

            // Add metadata
            $submission['submitted_at'] = $submittedAt;
            $submission['source_file'] = $filename;
            $submission['delivery_type'] = $deliveryType;
            $submission['imported_at'] = now();
            $submission['created_at'] = now();
            $submission['updated_at'] = now();

            $batch[] = $submission;

            // Insert in batches of 500
            if (count($batch) >= 500) {
                ShoutbombSubmission::insert($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        // Insert remaining
        if (!empty($batch)) {
            ShoutbombSubmission::insert($batch);
            $imported += count($batch);
        }

        return $imported;
    }

    /**
     * Import from local file (for testing).
     */
    public function importFromFile(string $filePath, string $type): array
    {
        $filename = basename($filePath);

        $submissions = match($type) {
            'holds' => $this->parser->parseHoldsFile($filePath),
            'overdue' => $this->parser->parseOverdueFile($filePath),
            'renew' => $this->parser->parseRenewFile($filePath),
            default => [],
        };

        $submittedAt = $this->parser->extractTimestampFromFilename($filename);

        $imported = 0;

        foreach ($submissions as $submission) {
            $submission['submitted_at'] = $submittedAt;
            $submission['source_file'] = $filename;
            $submission['delivery_type'] = null; // No patron lists in local import
            $submission['imported_at'] = now();

            ShoutbombSubmission::create($submission);
            $imported++;
        }

        return [
            'imported' => $imported,
            'file' => $filename,
            'type' => $type,
        ];
    }

    /**
     * Get import statistics.
     */
    public function getStats(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = ShoutbombSubmission::query();

        if ($startDate && $endDate) {
            $query->whereBetween('submitted_at', [$startDate, $endDate]);
        }

        return [
            'total' => $query->count(),
            'by_type' => $query->clone()
                ->select('notification_type', DB::raw('count(*) as count'))
                ->groupBy('notification_type')
                ->pluck('count', 'notification_type')
                ->toArray(),
            'by_delivery' => $query->clone()
                ->select('delivery_type', DB::raw('count(*) as count'))
                ->groupBy('delivery_type')
                ->pluck('count', 'delivery_type')
                ->toArray(),
            'unique_patrons' => $query->clone()->distinct('patron_barcode')->count('patron_barcode'),
        ];
    }
}
