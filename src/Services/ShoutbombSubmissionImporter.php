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
     * Import all submission files from FTP for a single date.
     *
     * This imports the OFFICIAL SQL-generated submission files that are
     * sent to Shoutbomb (holds, overdue, renew).
     */
    public function importFromFTP(?Carbon $startDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(1);

        $config   = config('notices.shoutbomb_submissions');
        $root     = rtrim($config['root'] ?? '/', '/');
        $patterns = $config['patterns'] ?? [];

        Log::info("Starting Shoutbomb submission import (official system)", [
            'start_date' => $startDate->format('Y-m-d'),
            'root'       => $root === '' ? '/' : $root,
            'patterns'   => $patterns,
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
     * Import all available submission files from FTP across all dates.
     *
     * This scans the FTP root for any *_submitted_YYYY-MM-DD_*.txt or
     * patron list files and runs the single-date import for each distinct date.
     *
     * If $from or $to is provided, only dates within that inclusive range
     * will be imported.
     */
    public function importAllFromFTP(?Carbon $from = null, ?Carbon $to = null): array
    {
        $config   = config('notices.shoutbomb_submissions');
        $root     = rtrim($config['root'] ?? '/', '/');
        $patterns = $config['patterns'] ?? [];
        $directory = $root === '' ? '/' : $root;

        // We need a connection only long enough to list files here; each
        // per-date import will manage its own FTP lifecycle.
        $ftpConfig = config('notices.shoutbomb.ftp');
        if (! $this->ftpService->connect()) {
            Log::error('Shoutbomb submission import-all: failed to connect to FTP', [
                'host' => $ftpConfig['host'] ?? 'not set',
                'root' => $directory,
            ]);

            return [
                'dates' => [],
                'totals' => [
                    'holds' => 0,
                    'overdues' => 0,
                    'renewals' => 0,
                    'voice_patrons' => 0,
                    'text_patrons' => 0,
                    'errors' => 1,
                ],
                'debug' => [
                    'ftp_connected' => false,
                    'ftp_host' => $ftpConfig['host'] ?? 'not set',
                    'error' => 'Failed to connect to FTP server',
                ],
            ];
        }

        // Discover all dates present in submission/patron filenames
        $files = $this->ftpService->listFiles($directory);
        $this->ftpService->disconnect();

        $dates = [];

        foreach ($files as $file) {
            $basename = basename($file);

            // Match YYYY-MM-DD format (with dashes)
            if (preg_match('/(?:holds|overdue|renew)_submitted_(\d{4}-\d{2}-\d{2})_/', $basename, $m)) {
                $dates[$m[1]] = true;
            } elseif (preg_match('/(?:voice|text)_patrons_submitted_(\d{4}-\d{2}-\d{2})_/', $basename, $m)) {
                $dates[$m[1]] = true;
            }
            // Match YYYYMMDD format (no dashes)
            elseif (preg_match('/(?:holds|overdue|renew)_submitted_(\d{4})(\d{2})(\d{2})_/', $basename, $m)) {
                $dates["{$m[1]}-{$m[2]}-{$m[3]}"] = true;
            } elseif (preg_match('/(?:voice|text)_patrons_submitted_(\d{4})(\d{2})(\d{2})_/', $basename, $m)) {
                $dates["{$m[1]}-{$m[2]}-{$m[3]}"] = true;
            }
        }

        if (empty($dates)) {
            Log::warning('Shoutbomb submission import-all: no matching files found on FTP', [
                'total_files_on_ftp' => count($files),
                'sample_files' => array_slice(array_map('basename', $files), 0, 10),
            ]);
            return [
                'dates' => [],
                'totals' => [
                    'holds' => 0,
                    'overdues' => 0,
                    'renewals' => 0,
                    'voice_patrons' => 0,
                    'text_patrons' => 0,
                    'errors' => 0,
                ],
                'debug' => [
                    'ftp_connected' => true,
                    'files_found' => count($files),
                    'sample_files' => array_slice(array_map('basename', $files), 0, 20),
                ],
            ];
        }

        ksort($dates);

        // Filter by from/to range if provided
        if ($from || $to) {
            $dates = array_filter(array_keys($dates), function ($dateString) use ($from, $to) {
                $d = Carbon::parse($dateString)->startOfDay();
                if ($from && $d->lt($from->copy()->startOfDay())) {
                    return false;
                }
                if ($to && $d->gt($to->copy()->startOfDay())) {
                    return false;
                }
                return true;
            });

            if (empty($dates)) {
                Log::warning('Shoutbomb submission import-all: no dates within requested range', [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                ]);

                return [
                    'dates' => [],
                    'totals' => [
                        'holds' => 0,
                        'overdues' => 0,
                        'renewals' => 0,
                        'voice_patrons' => 0,
                        'text_patrons' => 0,
                        'errors' => 0,
                    ],
                ];
            }
        } else {
            $dates = array_keys($dates);
        }

        $aggregate = [
            'holds' => 0,
            'overdues' => 0,
            'renewals' => 0,
            'voice_patrons' => 0,
            'text_patrons' => 0,
            'errors' => 0,
        ];

        foreach ($dates as $dateString) {
            $date = Carbon::parse($dateString);

            Log::info('Shoutbomb submission import-all: importing date', [
                'date' => $dateString,
            ]);

            $results = $this->importFromFTP($date);

            foreach ($aggregate as $key => $_) {
                if (isset($results[$key])) {
                    $aggregate[$key] += (int) $results[$key];
                }
            }
        }

        return [
            'dates' => array_values($dates),
            'totals' => $aggregate,
        ];
    }

    /**
     * Download and parse patron list file.
     */
    protected function downloadAndParsePatronList(string $type, Carbon $date): array
    {
        try {
            $config   = config('notices.shoutbomb_submissions');
            $root     = rtrim($config['root'] ?? '/', '/');
            $directory = $root === '' ? '/' : $root;

            // Find patron list file for the date (support both YYYY-MM-DD and YYYYMMDD formats)
            $patternDashed = "{$type}_patrons_submitted_{$date->format('Y-m-d')}";
            $patternNoDash = "{$type}_patrons_submitted_{$date->format('Ymd')}";
            $files = $this->ftpService->listFiles($directory);

            Log::info("Looking for patron list", [
                'patterns' => [$patternDashed, $patternNoDash],
                'root' => $directory,
                'files_found' => count($files),
            ]);

            foreach ($files as $file) {
                $basename = basename($file);

                if (str_contains($basename, $patternDashed) || str_contains($basename, $patternNoDash)) {
                    // Use the full remote path returned by listFiles so we
                    // honor the configured root directory.
                    $localPath = $this->ftpService->downloadFile($file);
                    if ($localPath) {
                        $parsed = $this->parser->parsePatronList($localPath);

                        Log::info("Found and downloaded patron list", [
                            'type' => $type,
                            'file' => $basename,
                            'root' => $directory,
                            'records' => count($parsed),
                        ]);

                        return $parsed;
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
            $config   = config('notices.shoutbomb_submissions');
            $root     = rtrim($config['root'] ?? '/', '/');
            $directory = $root === '' ? '/' : $root;

            // Find submission files (support both YYYY-MM-DD and YYYYMMDD formats)
            $patternDashed = "{$type}_submitted_{$date->format('Y-m-d')}";
            $patternNoDash = "{$type}_submitted_{$date->format('Ymd')}";
            $files = $this->ftpService->listFiles($directory);

            Log::info("Looking for submission files", [
                'type' => $type,
                'patterns' => [$patternDashed, $patternNoDash],
                'root' => $directory,
                'files_found' => count($files),
            ]);

            foreach ($files as $file) {
                $basename = basename($file);

                if (str_contains($basename, $patternDashed) || str_contains($basename, $patternNoDash)) {
                    // Use the full remote path returned by listFiles so we
                    // honor the configured root directory.
                    $localPath = $this->ftpService->downloadFile($file);

                    if ($localPath) {
                        $count = $this->processSubmissionFile($localPath, $basename, $type, $voicePatrons, $textPatrons);
                        $imported += $count;

                        Log::info("Imported {$type} submissions", [
                            'file' => $basename,
                            'root' => $directory,
                            'count' => $count,
                        ]);
                    }
                }
            }

            if ($imported === 0) {
                Log::warning("No {$type} submission files found", [
                    'patterns' => [$patternDashed, $patternNoDash],
                    'root' => $directory,
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
