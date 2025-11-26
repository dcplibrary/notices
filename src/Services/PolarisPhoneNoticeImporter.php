<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\PatronProfile;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Dcplibrary\Notices\Models\ShoutbombSubmission;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PolarisPhoneNoticeImporter.
 *
 * Imports PhoneNotices.csv - a Polaris-generated export file used for
 * VERIFICATION/CORROBORATION of notices sent to Shoutbomb.
 */
class PolarisPhoneNoticeImporter
{
    protected ShoutbombSubmissionParser $parser;

    protected ShoutbombFTPService $ftpService;

    public function __construct(ShoutbombSubmissionParser $parser, ShoutbombFTPService $ftpService)
    {
        $this->parser = $parser;
        $this->ftpService = $ftpService;
    }

    /**
     * Import PhoneNotices files from FTP.
     *
     * Supports two filename patterns:
     * - PhoneNotices.csv (undated - uses current date with 09:00:00 time)
     * - PhoneNotices_YYYY-MM-DD_HH-MM-SS.txt (dated files with timestamp in name)
     *
     * PhoneNotices is a Polaris native export that serves as
     * VERIFICATION/CORROBORATION of the official SQL-generated submissions.
     */
    public function importFromFTP(?callable $progressCallback = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        Log::info("Starting PhoneNotices import for verification/corroboration");

        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'file' => null,
            'files_processed' => [],
        ];

        try {
            // Use default date window if none provided to align with Polaris import
            if (!$startDate || !$endDate) {
                $days = config('notices.import.default_days', 1);
                $endDate = now()->endOfDay();
                $startDate = now()->subDays($days)->startOfDay();
            }

            // Connect to FTP
            if (!$this->ftpService->connect()) {
                throw new Exception('Failed to connect to FTP');
            }

            // Find PhoneNotices files (both .csv and dated .txt patterns)
            $files = $this->ftpService->listFiles('/');
            $phoneNoticesFiles = [];

            foreach ($files as $file) {
                $basename = strtolower(basename($file));

                // Match PhoneNotices.csv (undated)
                if ($basename === 'phonenotices.csv') {
                    $phoneNoticesFiles[] = [
                        'path' => $file,
                        'basename' => basename($file),
                        'dated' => false,
                        // Default logical file date to today at 09:00, but the
                        // per-row import will still rely on notice_date.
                        'file_date' => now()->setTime(9, 0, 0),
                    ];
                }
                // Match PhoneNotices_YYYY-MM-DD_HH-MM-SS.txt
                elseif (preg_match('/^phonenotices_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.txt$/i', basename($file), $matches)) {
                    $fileDate = Carbon::createFromFormat(
                        'Y-m-d H-i-s',
                        $matches[1] . ' ' . $matches[2]
                    );

                    // Only include files within the date range
                    if ($fileDate->startOfDay()->gte($startDate->startOfDay()) &&
                        $fileDate->startOfDay()->lte($endDate->endOfDay())) {
                        $phoneNoticesFiles[] = [
                            'path' => $file,
                            'basename' => basename($file),
                            'dated' => true,
                            'file_date' => $fileDate,
                        ];
                    }
                }
            }

            // Process all matching files
            $totalImported = 0;
            foreach ($phoneNoticesFiles as $fileInfo) {
                $localPath = $this->ftpService->downloadFile('/' . ltrim($fileInfo['path'], '/'));

                if ($localPath) {
                    // Notify callback that we're starting a new file
                    if ($progressCallback) {
                        $progressCallback(0, 0, $fileInfo['basename'], true);
                    }

                    $count = $this->importPhoneNoticesFile(
                        $localPath,
                        $fileInfo['basename'],
                        $progressCallback,
                        $startDate,
                        $endDate,
                        $fileInfo['file_date']
                    );
                    $totalImported += $count;
                    $results['files_processed'][] = $fileInfo['basename'];

                    Log::info("Imported PhoneNotices file", [
                        'file' => $fileInfo['basename'],
                        'dated' => $fileInfo['dated'],
                        'file_date' => $fileInfo['file_date']->format('Y-m-d H:i:s'),
                        'count' => $count,
                    ]);
                }
            }

            $results['imported'] = $totalImported;
            $results['file'] = implode(', ', $results['files_processed']);

            if (empty($results['files_processed'])) {
                Log::warning("No PhoneNotices files found on FTP");
            }

            $this->ftpService->disconnect();

        } catch (Exception $e) {
            Log::error("PhoneNotices import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Import PhoneNotices file.
     *
     * Note: Using individual upserts instead of bulk for reliable parameter
     * binding across all database drivers (SQLite, MySQL, etc.) and to keep
     * the import idempotent. Re-importing the same file will update existing
     * rows rather than creating duplicates.
     *
     * @param string $filePath Local path to the downloaded file
     * @param string $filename Original filename
     * @param callable|null $progressCallback Progress callback (current, total, filename, isNewFile)
     * @param Carbon|null $startDate Start of date range filter
     * @param Carbon|null $endDate End of date range filter
     * @param Carbon|null $fileDate Date extracted from filename (for dated files) or default date
     */
    protected function importPhoneNoticesFile(string $filePath, string $filename, ?callable $progressCallback = null, ?Carbon $startDate = null, ?Carbon $endDate = null, ?Carbon $fileDate = null): int
    {
        $notices = $this->parser->parsePhoneNoticesCSV($filePath);
        $imported = 0;
        $timestamp = now();
        $total = count($notices);

        foreach ($notices as $index => $notice) {
            try {
                // Filter by date range if provided
                if ($startDate && $endDate && !empty($notice['notice_date'])) {
                    try {
                        $nd = Carbon::parse($notice['notice_date'])->startOfDay();
                        if ($nd->lt($startDate->copy()->startOfDay()) || $nd->gt($endDate->copy()->endOfDay())) {
                            // Outside desired window; skip
                            if ($progressCallback) {
                                $progressCallback($index + 1, $total, $filename, false);
                            }
                            continue;
                        }
                    } catch (Exception $e) {
                        // If date parsing fails, skip record
                        if ($progressCallback) {
                            $progressCallback($index + 1, $total, $filename, false);
                        }
                        continue;
                    }
                }

                // Add metadata
                $notice['source_file'] = $filename;
                $notice['imported_at'] = $timestamp;

                // Convert notice_date to proper format if it's a Carbon instance
                if (isset($notice['notice_date']) && $notice['notice_date'] instanceof Carbon) {
                    $notice['notice_date'] = $notice['notice_date']->format('Y-m-d');
                }

                // Set import_date from notice_date or fall back to today
                if (!empty($notice['notice_date'])) {
                    try {
                        $notice['import_date'] = Carbon::parse($notice['notice_date'])->format('Y-m-d');
                    } catch (Exception $e) {
                        $notice['import_date'] = $timestamp->format('Y-m-d');
                    }
                } else {
                    // If notice_date is missing, fall back to fileDate if provided
                    // or to the current timestamp's date.
                    $notice['import_date'] = $fileDate
                        ? $fileDate->toDateString()
                        : $timestamp->format('Y-m-d');
                }

                // Define a logical uniqueness key so that re-importing the same
                // PhoneNotices file updates existing rows instead of creating
                // duplicates. This matches the idea of one record per
                // patron+item+delivery_type+notice_date.
                $uniqueKey = [
                    'patron_barcode' => $notice['patron_barcode'] ?? null,
                    'item_barcode'   => $notice['item_barcode'] ?? null,
                    'delivery_type'  => $notice['delivery_type'] ?? null,
                    'notice_date'    => $notice['notice_date'] ?? $notice['import_date'],
                ];

                PolarisPhoneNotice::updateOrCreate(
                    $uniqueKey,
                    $notice
                );

                // Upsert patron profile based on this PhoneNotices row.
                $barcode = $notice['patron_barcode'] ?? null;
                if ($barcode) {
                    $profile = PatronProfile::firstOrNew(['patron_barcode' => $barcode]);

                    // Basic identity
                    if (!empty($notice['patron_id'])) {
                        $profile->patron_id = (int) $notice['patron_id'];
                    }

                    // Snapshot fields
                    if (!empty($notice['first_name'])) {
                        $profile->name_first = $notice['first_name'];
                    }
                    if (!empty($notice['last_name'])) {
                        $profile->name_last = $notice['last_name'];
                    }
                    if (!empty($notice['phone_number'])) {
                        $profile->primary_phone = $notice['phone_number'];
                    }
                    if (!empty($notice['email'])) {
                        $profile->email_address = $notice['email'];
                    }

                    // Language / org context (when available)
                    if (!empty($notice['language_code'])) {
                        $profile->language_code = $notice['language_code'];
                    }
                    if (!empty($notice['language_id'])) {
                        $profile->language_id = (int) $notice['language_id'];
                    }
                    if (!empty($notice['reporting_org_id'])) {
                        $profile->reporting_org_id = (int) $notice['reporting_org_id'];
                    }

                    // Derive delivery_option_id from delivery_type (V/T → 3/8) if present.
                    $newOption = null;
                    if (!empty($notice['delivery_type'])) {
                        if ($notice['delivery_type'] === 'voice') {
                            $newOption = 3; // Phone 1 – Voice
                        } elseif ($notice['delivery_type'] === 'text') {
                            $newOption = 8; // TXT Messaging
                        }
                    }

                    if ($newOption !== null && $profile->delivery_option_id !== $newOption) {
                        $profile->former_delivery_option_id = $profile->delivery_option_id;
                        $profile->delivery_option_id = $newOption;
                        $profile->delivery_option_changed_at = $timestamp;
                    }

                    // Update last_seen_in_phonenotices_at if this row is newer.
                    $seenAt = null;
                    if (!empty($notice['notice_date'])) {
                        try {
                            $seenAt = Carbon::parse($notice['notice_date']);
                        } catch (Exception $e) {
                            $seenAt = null;
                        }
                    }
                    if (!$seenAt) {
                        $seenAt = $timestamp;
                    }
                    if (!$profile->last_seen_in_phonenotices_at || $seenAt->gt($profile->last_seen_in_phonenotices_at)) {
                        $profile->last_seen_in_phonenotices_at = $seenAt;
                    }

                    $profile->save();
                }

                $imported++;

                // Call progress callback if provided
                if ($progressCallback) {
                    $progressCallback($index + 1, $total, $filename, false);
                }

            } catch (Exception $e) {
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
    public function importFromFile(string $filePath, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $filename = basename($filePath);

        Log::info("Importing PhoneNotices.csv from local file", [
            'file' => $filename,
            'start' => $startDate?->format('Y-m-d'),
            'end' => $endDate?->format('Y-m-d'),
        ]);

        $imported = $this->importPhoneNoticesFile($filePath, $filename, null, $startDate, $endDate);

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
        $query = PolarisPhoneNotice::query();

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
        $phoneNotices = PolarisPhoneNotice::whereDate('notice_date', $date)->get();
        $submissions = ShoutbombSubmission::whereDate('submitted_at', $date)->get();

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
