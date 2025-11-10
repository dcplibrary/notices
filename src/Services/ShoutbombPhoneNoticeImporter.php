<?php

namespace Dcplibrary\Notifications\Services;

use Dcplibrary\Notifications\Models\ShoutbombPhoneNotice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ShoutbombPhoneNoticeImporter
{
    protected ShoutbombSubmissionParser $parser;
    protected ShoutbombFTPService $ftpService;

    public function __construct(ShoutbombSubmissionParser $parser, ShoutbombFTPService $ftpService)
    {
        $this->parser = $parser;
        $this->ftpService = $ftpService;
    }

    /**
     * Import PhoneNotices.csv from FTP.
     *
     * PhoneNotices.csv is a Polaris native export that serves as
     * VERIFICATION/CORROBORATION of the official SQL-generated submissions.
     */
    public function importFromFTP(?callable $progressCallback = null): array
    {
        Log::info("Starting PhoneNotices.csv import for verification/corroboration");

        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'file' => null,
        ];

        try {
            // Connect to FTP
            if (!$this->ftpService->connect()) {
                throw new \Exception('Failed to connect to FTP');
            }

            // Find PhoneNotices.csv file
            $files = $this->ftpService->listFiles('/');

            foreach ($files as $file) {
                if (str_contains(strtolower($file), 'phonenotices.csv')) {
                    $results['file'] = basename($file);
                    $localPath = $this->ftpService->downloadFile('/' . $file);

                    if ($localPath) {
                        $count = $this->importPhoneNoticesFile($localPath, basename($file), $progressCallback);
                        $results['imported'] = $count;

                        Log::info("Imported PhoneNotices.csv", [
                            'file' => basename($file),
                            'count' => $count,
                        ]);

                        // Only process first PhoneNotices.csv found
                        break;
                    }
                }
            }

            if (!$results['file']) {
                Log::warning("PhoneNotices.csv not found on FTP");
            }

            $this->ftpService->disconnect();

        } catch (\Exception $e) {
            Log::error("PhoneNotices.csv import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Import PhoneNotices.csv file.
     *
     * Note: Using individual inserts instead of bulk for reliable parameter binding
     * across all database drivers (SQLite, MySQL, etc.)
     */
    protected function importPhoneNoticesFile(string $filePath, string $filename, ?callable $progressCallback = null): int
    {
        $notices = $this->parser->parsePhoneNoticesCSV($filePath);
        $imported = 0;
        $timestamp = now();
        $total = count($notices);

        foreach ($notices as $index => $notice) {
            try {
                // Add metadata
                $notice['source_file'] = $filename;
                $notice['imported_at'] = $timestamp;
                $notice['created_at'] = $timestamp;
                $notice['updated_at'] = $timestamp;

                // Convert notice_date to proper format if it's a Carbon instance
                if (isset($notice['notice_date']) && $notice['notice_date'] instanceof \Carbon\Carbon) {
                    $notice['notice_date'] = $notice['notice_date']->format('Y-m-d');
                }

                // Insert individual record - this ensures proper PDO parameter binding
                ShoutbombPhoneNotice::create($notice);
                $imported++;

                // Call progress callback if provided
                if ($progressCallback) {
                    $progressCallback($index + 1, $total);
                }

            } catch (\Exception $e) {
                Log::error("Failed to import phone notice", [
                    'error' => $e->getMessage(),
                    'notice' => $notice,
                ]);
            }
        }

        return $imported;
    }

    /**
     * Import from local file (for testing).
     */
    public function importFromFile(string $filePath): array
    {
        $filename = basename($filePath);

        Log::info("Importing PhoneNotices.csv from local file", [
            'file' => $filename,
        ]);

        $imported = $this->importPhoneNoticesFile($filePath, $filename);

        return [
            'imported' => $imported,
            'file' => $filename,
        ];
    }

    /**
     * Get verification statistics.
     */
    public function getStats(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = ShoutbombPhoneNotice::query();

        if ($startDate && $endDate) {
            $query->whereBetween('notice_date', [$startDate, $endDate]);
        }

        return [
            'total' => $query->count(),
            'by_delivery_type' => $query->clone()
                ->select('delivery_type', DB::raw('count(*) as count'))
                ->groupBy('delivery_type')
                ->pluck('count', 'delivery_type')
                ->toArray(),
            'by_library' => $query->clone()
                ->select('library_code', 'library_name', DB::raw('count(*) as count'))
                ->groupBy('library_code', 'library_name')
                ->get()
                ->map(function ($item) {
                    return [
                        'code' => $item->library_code,
                        'name' => $item->library_name,
                        'count' => $item->count,
                    ];
                })
                ->toArray(),
            'unique_patrons' => $query->clone()->distinct('patron_barcode')->count('patron_barcode'),
            'unique_phones' => $query->clone()->distinct('phone_number')->count('phone_number'),
        ];
    }

    /**
     * Compare phone notices with submissions for verification.
     */
    public function compareWithSubmissions(Carbon $date): array
    {
        $phoneNotices = ShoutbombPhoneNotice::whereDate('notice_date', $date)->get();
        $submissions = \Dcplibrary\Notifications\Models\ShoutbombSubmission::whereDate('submitted_at', $date)->get();

        return [
            'date' => $date->format('Y-m-d'),
            'phone_notices_count' => $phoneNotices->count(),
            'submissions_count' => $submissions->count(),
            'difference' => abs($phoneNotices->count() - $submissions->count()),
            'phone_notices_by_type' => $phoneNotices->groupBy('delivery_type')->map->count(),
            'submissions_by_type' => $submissions->groupBy('notification_type')->map->count(),
        ];
    }
}
