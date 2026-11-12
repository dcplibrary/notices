<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Contracts\NotificationImporterInterface;
use Dcplibrary\Notices\Models\ActivityFile;
use Dcplibrary\Notices\Services\Import\FtpImportService;
use Dcplibrary\Notices\Support\StringSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Batch size for bulk inserts. Larger values use more memory but fewer queries.
 * 500 rows is a good balance for typical notification files.
 */
const IMPORT_BATCH_SIZE = 500;

/**
 * Default implementation of NotificationImporterInterface.
 *
 * This service coordinates importing Polaris/Shoutbomb exports into the
 * raw `polaris_hold_notifications` table. It prefers a locally mirrored
 * import path when configured, otherwise it will:
 *
 * - connect to the Polaris FTP server via FtpImportService and download
 *   only the holds files for the requested date into local storage; and
 * - fall back to the example export files under docs/exports in
 *   development environments when no real files are found.
 *
 * The imported rows are stored exactly as exported by Polaris so higher
 * level services (deduplication, reconciliation, failure detection) can
 * reason about them without mutating the source data.
 */
class FtpNotificationImporter implements NotificationImporterInterface
{
    public function __construct(
        protected ?FtpImportService $ftp = null,
    ) {}

    /**
     * Import Shoutbomb holds/renew/overdue exports for a given date.
     *
     * @param \DateTimeInterface $date date encoded in the example file names
     */
    public function importShoutbombExports(\DateTimeInterface $date): void
    {
        // Import holds files
        $this->importFilesByType('holds', $date);

        // Import renewal files
        $this->importFilesByType('renew', $date);

        // Import overdue files
        $this->importFilesByType('overdue', $date);
    }

    public function importPolarisPhoneNotices(\DateTimeInterface $date): void
    {
        $files = [];

        // 1) Prefer local import path if configured
        $importPath = trim((string) config('notices.polaris.import_path', ''));
        if ('' !== $importPath) {
            $resolved  = null;
            $candidate = base_path(ltrim($importPath, '/'));
            if (is_dir($candidate)) {
                $resolved = $candidate;
            } elseif (is_dir($importPath)) {
                $resolved = $importPath;
            }

            if (null !== $resolved) {
                $baseDir = rtrim($resolved, '/');
                // Check both the base directory and the phone-notices subdirectory
                $pattern = sprintf('%s/PhoneNotices_%s_*.csv', $baseDir, $date->format('Y-m-d'));
                $files   = glob($pattern) ?: [];

                // Also check in phone-notices subdirectory
                if (empty($files)) {
                    $subPattern = sprintf('%s/phone-notices/PhoneNotices_%s_*.csv', $baseDir, $date->format('Y-m-d'));
                    $files      = glob($subPattern) ?: [];
                }

                \Log::info('FtpNotificationImporter: local PhoneNotices scan', [
                    'date'               => $date->format('Y-m-d'),
                    'import_path_config' => $importPath,
                    'resolved_path'      => $resolved,
                    'pattern'            => $pattern,
                    'file_count'         => count($files),
                ]);
            }
        }

        // 2) Try FTP if nothing found locally
        $useFtp = (bool) config('notices.polaris.use_ftp');
        if (empty($files) && $useFtp && $this->ftp) {
            $localBase = rtrim((string) config('notices.import.storage', storage_path('app/notices-exports')), '/');
            $localDir  = $localBase . '/phone-notices';

            $datePattern = sprintf('PhoneNotices_%s_*.csv', $date->format('Y-m-d'));

            \Log::info('FtpNotificationImporter: attempting FTP download (PhoneNotices)', [
                'date'            => $date->format('Y-m-d'),
                'ftp_pattern'     => $datePattern,
                'local_directory' => $localDir,
            ]);

            $downloaded = $this->ftp->downloadFilesByPattern($datePattern, $localDir);

            \Log::info('FtpNotificationImporter: FTP download result (PhoneNotices)', [
                'date'             => $date->format('Y-m-d'),
                'downloaded_count' => count($downloaded),
                'files'            => array_column($downloaded, 'local'),
            ]);

            foreach ($downloaded as $info) {
                $files[] = $info['local'];
            }
        }

        // 3) Fallback to bundled docs (development only)
        if (empty($files)) {
            $basePath = base_path('vendor/dcplibrary/notices/docs/exports');
            if (!is_dir($basePath)) {
                $fallback = base_path('../notices/docs/exports');
                if (is_dir($fallback)) {
                    $basePath = $fallback;
                }
            }

            $pattern = sprintf('%s/PhoneNotices_%s_*.csv', $basePath, $date->format('Y-m-d'));
            $files   = glob($pattern) ?: [];

            \Log::info('FtpNotificationImporter: docs fallback scan (PhoneNotices)', [
                'date'       => $date->format('Y-m-d'),
                'base_path'  => $basePath,
                'pattern'    => $pattern,
                'file_count' => count($files),
            ]);
        }

        foreach ($files as $file) {
            \Log::info('FtpNotificationImporter: importing PhoneNotices file', [
                'date' => $date->format('Y-m-d'),
                'file' => $file,
            ]);

            $this->importPhoneNoticesFile($file);
        }
    }

    public function importNotificationLog(\DateTimeInterface $date): void
    {
        $files = [];

        $importPath = trim((string) config('notices.polaris.import_path', ''));
        if ('' !== $importPath) {
            $resolved  = null;
            $candidate = base_path(ltrim($importPath, '/'));
            if (is_dir($candidate)) {
                $resolved = $candidate;
            } elseif (is_dir($importPath)) {
                $resolved = $importPath;
            }

            if (null !== $resolved) {
                $baseDir = rtrim($resolved, '/');
                // Check both the base directory and the notification-log subdirectory
                $pattern = sprintf('%s/notification_log_%s_*.txt', $baseDir, $date->format('Y-m-d'));
                $files   = glob($pattern) ?: [];

                // Also check in notification-log subdirectory
                if (empty($files)) {
                    $subPattern = sprintf('%s/notification-log/notification_log_%s_*.txt', $baseDir, $date->format('Y-m-d'));
                    $files      = glob($subPattern) ?: [];
                }

                \Log::info('FtpNotificationImporter: local notification_log scan', [
                    'date'               => $date->format('Y-m-d'),
                    'import_path_config' => $importPath,
                    'resolved_path'      => $resolved,
                    'pattern'            => $pattern,
                    'file_count'         => count($files),
                ]);
            }
        }

        $useFtp = (bool) config('notices.polaris.use_ftp');
        if (empty($files) && $useFtp && $this->ftp) {
            $localBase = rtrim((string) config('notices.import.storage', storage_path('app/notices-exports')), '/');
            $localDir  = $localBase . '/notification-log';

            $datePattern = sprintf('notification_log_%s_*.txt', $date->format('Y-m-d'));

            \Log::info('FtpNotificationImporter: attempting FTP download (notification_log)', [
                'date'            => $date->format('Y-m-d'),
                'ftp_pattern'     => $datePattern,
                'local_directory' => $localDir,
            ]);

            $downloaded = $this->ftp->downloadFilesByPattern($datePattern, $localDir);

            \Log::info('FtpNotificationImporter: FTP download result (notification_log)', [
                'date'             => $date->format('Y-m-d'),
                'downloaded_count' => count($downloaded),
                'files'            => array_column($downloaded, 'local'),
            ]);

            foreach ($downloaded as $info) {
                $files[] = $info['local'];
            }
        }

        if (empty($files)) {
            // Fallback to bundled docs (development only)
            $basePath = base_path('vendor/dcplibrary/notices/docs/exports');
            if (!is_dir($basePath)) {
                $fallback = base_path('../notices/docs/exports');
                if (is_dir($fallback)) {
                    $basePath = $fallback;
                }
            }

            $pattern = sprintf('%s/notification_log_%s_*.txt', $basePath, $date->format('Y-m-d'));
            $files   = glob($pattern) ?: [];

            \Log::info('FtpNotificationImporter: docs fallback scan (notification_log)', [
                'date'       => $date->format('Y-m-d'),
                'base_path'  => $basePath,
                'pattern'    => $pattern,
                'file_count' => count($files),
            ]);
        }

        foreach ($files as $file) {
            \Log::info('FtpNotificationImporter: importing notification_log file', [
                'date' => $date->format('Y-m-d'),
                'file' => $file,
            ]);

            $this->importNotificationLogFile($file);
        }
    }

    public function importNotificationHistory(\DateTimeInterface $date): void
    {
        $files = [];

        // 1) Prefer local import path if configured
        $importPath = trim((string) config('notices.polaris.import_path', ''));
        if ('' !== $importPath) {
            $resolved  = null;
            $candidate = base_path(ltrim($importPath, '/'));
            if (is_dir($candidate)) {
                $resolved = $candidate;
            } elseif (is_dir($importPath)) {
                $resolved = $importPath;
            }

            if (null !== $resolved) {
                $baseDir = rtrim($resolved, '/');
                // Check both the base directory and the notification-history subdirectory
                $pattern = sprintf('%s/notification_history_%s_*.txt', $baseDir, $date->format('Y-m-d'));
                $files   = glob($pattern) ?: [];

                // Also check in notification-history subdirectory
                if (empty($files)) {
                    $subPattern = sprintf('%s/notification-history/notification_history_%s_*.txt', $baseDir, $date->format('Y-m-d'));
                    $files      = glob($subPattern) ?: [];
                }

                \Log::info('FtpNotificationImporter: local notification_history scan', [
                    'date'               => $date->format('Y-m-d'),
                    'import_path_config' => $importPath,
                    'resolved_path'      => $resolved,
                    'pattern'            => $pattern,
                    'file_count'         => count($files),
                ]);
            }
        }

        // 2) Try FTP if nothing found locally
        $useFtp = (bool) config('notices.polaris.use_ftp');
        if (empty($files) && $useFtp && $this->ftp) {
            $localBase = rtrim((string) config('notices.import.storage', storage_path('app/notices-exports')), '/');
            $localDir  = $localBase . '/notification-history';

            $datePattern = sprintf('notification_history_%s_*.txt', $date->format('Y-m-d'));

            \Log::info('FtpNotificationImporter: attempting FTP download (notification_history)', [
                'date'            => $date->format('Y-m-d'),
                'ftp_pattern'     => $datePattern,
                'local_directory' => $localDir,
            ]);

            $downloaded = $this->ftp->downloadFilesByPattern($datePattern, $localDir);

            \Log::info('FtpNotificationImporter: FTP download result (notification_history)', [
                'date'             => $date->format('Y-m-d'),
                'downloaded_count' => count($downloaded),
                'files'            => array_column($downloaded, 'local'),
            ]);

            foreach ($downloaded as $info) {
                $files[] = $info['local'];
            }
        }

        // 3) Fallback to bundled docs (development only)
        if (empty($files)) {
            $basePath = base_path('vendor/dcplibrary/notices/docs/exports');
            if (!is_dir($basePath)) {
                $fallback = base_path('../notices/docs/exports');
                if (is_dir($fallback)) {
                    $basePath = $fallback;
                }
            }

            $pattern = sprintf('%s/notification_history_%s_*.txt', $basePath, $date->format('Y-m-d'));
            $files   = glob($pattern) ?: [];

            \Log::info('FtpNotificationImporter: docs fallback scan (notification_history)', [
                'date'       => $date->format('Y-m-d'),
                'base_path'  => $basePath,
                'pattern'    => $pattern,
                'file_count' => count($files),
            ]);
        }

        foreach ($files as $file) {
            \Log::info('FtpNotificationImporter: importing notification_history file', [
                'date' => $date->format('Y-m-d'),
                'file' => $file,
            ]);

            $this->importNotificationHistoryFile($file);
        }
    }

    /**
     * Import files of a specific type (holds, renew, overdue) for a given date.
     *
     * @param string             $type 'holds', 'renew', or 'overdue'
     * @param \DateTimeInterface $date date encoded in the example file names
     */
    protected function importFilesByType(string $type, \DateTimeInterface $date): void
    {
        $files = [];

        // 1) Prefer a local import path if one is configured (POLARIS_FTP_IMPORT_PATH).
        $importPath = trim((string) config('notices.polaris.import_path', ''));
        if ('' !== $importPath) {
            $resolved = null;

            // First, treat it as relative to the Laravel base path (so values like
            // "storage/logs" or "/storage/logs" resolve to base_path('storage/logs')).
            $candidate = base_path(ltrim($importPath, '/'));
            if (is_dir($candidate)) {
                $resolved = $candidate;
            } elseif (is_dir($importPath)) {
                // Fallback: use as-is if it is already an absolute, valid directory.
                $resolved = $importPath;
            }

            if (null !== $resolved) {
                $baseDir = rtrim($resolved, '/');
                // Check both the base directory and the type-specific subdirectory
                $pattern = sprintf('%s/%s_%s_*.txt', $baseDir, $type, $date->format('Y-m-d'));
                $files   = glob($pattern) ?: [];

                // Also check in type-specific subdirectory (e.g., /holds/, /renew/, /overdue/)
                if (empty($files)) {
                    $subPattern = sprintf('%s/%s/%s_%s_*.txt', $baseDir, $type, $type, $date->format('Y-m-d'));
                    $files      = glob($subPattern) ?: [];
                }

                \Log::info("FtpNotificationImporter: local import path scan ({$type})", [
                    'date'               => $date->format('Y-m-d'),
                    'type'               => $type,
                    'import_path_config' => $importPath,
                    'resolved_path'      => $resolved,
                    'pattern'            => $pattern,
                    'file_count'         => count($files),
                ]);
            } else {
                \Log::info("FtpNotificationImporter: import_path configured but not found ({$type})", [
                    'date'               => $date->format('Y-m-d'),
                    'type'               => $type,
                    'import_path_config' => $importPath,
                ]);
            }
        } else {
            \Log::info("FtpNotificationImporter: no import_path configured ({$type})", [
                'date' => $date->format('Y-m-d'),
                'type' => $type,
            ]);
        }

        // 2) If nothing found locally, try real FTP imports when enabled and the client is available.
        $useFtp = (bool) config('notices.polaris.use_ftp');
        \Log::info("FtpNotificationImporter: FTP configuration ({$type})", [
            'date'            => $date->format('Y-m-d'),
            'type'            => $type,
            'use_ftp'         => $useFtp,
            'has_ftp_service' => (bool) $this->ftp,
        ]);

        if (empty($files) && $useFtp && $this->ftp) {
            $localBase = rtrim((string) config('notices.import.storage', storage_path('app/notices-exports')), '/');
            $localDir  = $localBase . '/' . $type;

            // Constrain to the specific date we are importing, so we do not
            // re-import every file on each run.
            $datePattern = sprintf('%s_%s_*.txt', $type, $date->format('Y-m-d'));

            \Log::info("FtpNotificationImporter: attempting FTP download ({$type})", [
                'date'            => $date->format('Y-m-d'),
                'type'            => $type,
                'ftp_pattern'     => $datePattern,
                'local_directory' => $localDir,
            ]);

            $downloaded = $this->ftp->downloadFilesByPattern($datePattern, $localDir);

            \Log::info("FtpNotificationImporter: FTP download result ({$type})", [
                'date'             => $date->format('Y-m-d'),
                'type'             => $type,
                'downloaded_count' => count($downloaded),
                'files'            => array_column($downloaded, 'local'),
            ]);

            foreach ($downloaded as $info) {
                $files[] = $info['local'];
            }
        }

        // 3) Fallback to example exports bundled with the package if nothing was found.
        if (empty($files)) {
            $basePath = base_path('vendor/dcplibrary/notices/docs/exports');
            if (!is_dir($basePath)) {
                // Support path-repo development installs (../notices).
                $fallback = base_path('../notices/docs/exports');
                if (is_dir($fallback)) {
                    $basePath = $fallback;
                }
            }

            $pattern = sprintf('%s/%s_%s_*.txt', $basePath, $type, $date->format('Y-m-d'));
            $files   = glob($pattern) ?: [];

            \Log::info("FtpNotificationImporter: docs fallback scan ({$type})", [
                'date'       => $date->format('Y-m-d'),
                'type'       => $type,
                'base_path'  => $basePath,
                'pattern'    => $pattern,
                'file_count' => count($files),
            ]);
        }

        foreach ($files as $file) {
            \Log::info("FtpNotificationImporter: importing {$type} file", [
                'date' => $date->format('Y-m-d'),
                'type' => $type,
                'file' => $file,
            ]);

            if ('holds' === $type) {
                $this->importHoldsFile($file);
            } elseif ('renew' === $type) {
                $this->importRenewalFile($file);
            } elseif ('overdue' === $type) {
                $this->importOverdueFile($file);
            }
        }
    }

    /**
     * Import a notification_log export file into delivery_confirmations.
     */
    protected function importNotificationLogFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        // Ensure we have an ActivityFile record for this NotificationLog file
        $filename = basename($path);
        $fileDate = null;
        if (preg_match('/notification_log_(\d{4}-\d{2}-\d{2})_/', $filename, $m)) {
            try {
                $fileDate = new \DateTimeImmutable($m[1]);
            } catch (\Throwable $e) {
                $fileDate = null;
            }
        }

        $activityFile = ActivityFile::firstOrCreate(
            [
                'type'          => 'notification_log',
                'original_name' => $filename,
            ],
            [
                'disk'      => null,
                'path'      => $path,
                'file_date' => $fileDate,
                'parsed_at' => now(),
            ]
        );

        $batch      = [];
        $lineNumber = 0;
        $now        = now();

        while (($line = fgets($handle)) !== false) {
            ++$lineNumber;
            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }

            $columns = explode('|', $line);
            if (count($columns) < 23) {
                \Log::warning('NotificationLog import: invalid column count', [
                    'filename' => $filename,
                    'line'     => $lineNumber,
                    'count'    => count($columns),
                ]);

                continue;
            }

            $patronId             = (int) ($columns[0] ?? 0);
            $notificationTypeId   = (int) ($columns[1] ?? 0);
            $deliveryOptionId     = (int) ($columns[2] ?? 0);
            $reportingOrgId       = (int) ($columns[3] ?? 0);
            $noticeDateRaw        = $columns[4] ?? null;
            $deliveryString       = $columns[5] ?? null;
            $notificationStatusId = (int) ($columns[6] ?? 0);
            $details              = $columns[7] ?? null;
            $holdsCount           = (int) ($columns[11] ?? 0);
            $cancelsCount         = (int) ($columns[12] ?? 0);
            $patronBarcode        = $columns[17] ?? null;
            $notificationLogId    = $columns[21] ?? null;

            $noticeDate = null;
            if ($noticeDateRaw) {
                try {
                    $noticeDate = (new \DateTimeImmutable($noticeDateRaw))->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    // leave null
                }
            }

            $batch[] = [
                'confirmation_source'    => 'notification_log',
                'source_filename'        => $filename,
                'source_record_id'       => $notificationLogId,
                'notification_id'        => null,
                'notification_key'       => null,
                'patron_id'              => $patronId ?: null,
                'item_record_id'         => null,
                'notification_type_id'   => $notificationTypeId ?: null,
                'delivery_option_id'     => $deliveryOptionId ?: null,
                'reporting_org_id'       => $reportingOrgId ?: null,
                'notice_date'            => $noticeDate,
                'notification_status_id' => $notificationStatusId ?: null,
                'delivery_string'        => $deliveryString,
                'details'                => $details,
                'holds_count'            => 0 !== $holdsCount ? ($holdsCount ?: null) : 0,
                'cancels_count'          => 0 !== $cancelsCount ? ($cancelsCount ?: null) : 0,
                'patron_barcode'         => $patronBarcode ?: null,
                'ingested_email_id'      => null,
                'created_at'             => $now,
                'updated_at'             => $now,
            ];

            // Flush batch when it reaches the threshold
            if (count($batch) >= IMPORT_BATCH_SIZE) {
                DB::table('delivery_confirmations')->insert($batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            DB::table('delivery_confirmations')->insert($batch);
        }

        fclose($handle);
    }

    /**
     * Import a single holds export file into polaris_hold_notifications.
     *
     * Data is stored as-is from Polaris, without transformation, using
     * the column order defined in the Polaris holds export query.
     *
     * @param string $path absolute path to the export file to import
     */
    protected function importHoldsFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $filename   = basename($path);
        $lineNumber = 0;

        $batch = [];
        $now   = now();

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }

            ++$lineNumber;

            $fields = $this->parsePipeFields($line);

            if (count($fields) < 29) {
                \Log::warning('Holds import: invalid column count', [
                    'filename' => $filename,
                    'line'     => $lineNumber,
                    'count'    => count($fields),
                ]);

                continue;
            }

            if ($this->looksLikeLegacyCompactHoldRow($fields)) {
                $data = $this->mapLegacyCompactHoldRow($fields);
            } else {
                // Standard order from the Polaris holds export query
                $data = [
                    'patron_id'                  => $this->toIntOrNull($fields[0] ?? null),
                    'item_record_id'             => $this->toIntOrNull($fields[1] ?? null),
                    'notification_type_id'       => $this->toIntOrNull($fields[2] ?? null),
                    'delivery_option_id'         => $this->toIntOrNull($fields[3] ?? null),
                    'notification_queue_id'      => $this->toIntOrNull($fields[4] ?? null),
                    'processed'                  => $this->toBoolOrNull($fields[5] ?? null),
                    'notification_creation_date' => $this->toDateTimeOrNull($fields[6] ?? null),
                    'patron_barcode'             => $fields[7] ?? null,
                    'item_barcode'               => $fields[8] ?? null,
                    'title'                      => StringSanitizer::sanitize($fields[9] ?? null),
                    'bibliographic_record_id'    => $this->toIntOrNull($fields[10] ?? null),
                    'patron_first_name'          => StringSanitizer::sanitize($fields[11] ?? null),
                    'patron_last_name'           => StringSanitizer::sanitize($fields[12] ?? null),
                    'patron_email'               => $fields[13] ?? null,
                    'patron_phone'               => $fields[14] ?? null,
                    'patron_record_status_id'    => $this->toIntOrNull($fields[15] ?? null),
                    'patron_expiration_date'     => $this->toDateOrNull($fields[16] ?? null),
                    'due_date'                   => $this->toDateOrNull($fields[17] ?? null),
                    'days_until_due'             => $this->toIntOrNull($fields[18] ?? null),
                    'renewals'                   => $this->toIntOrNull($fields[19] ?? null),
                    'renewal_limit'              => $this->toIntOrNull($fields[20] ?? null),
                    'material_type_id'           => $this->toIntOrNull($fields[21] ?? null),
                    'checkout_branch_id'         => $this->toIntOrNull($fields[22] ?? null),
                    'loan_units'                 => $this->toIntOrNull($fields[23] ?? null),
                    'sys_hold_request_id'        => $this->toIntOrNull($fields[24] ?? null),
                    'sys_hold_status_id'         => $this->toIntOrNull($fields[25] ?? null),
                    'activation_date'            => $this->toDateOrNull($fields[26] ?? null),
                    'hold_notification_date'     => $this->toDateOrNull($fields[27] ?? null),
                    'hold_till_date'             => $this->toDateOrNull($fields[28] ?? null),
                    'pickup_organization_id'     => $this->toIntOrNull($fields[29] ?? null),
                    'site_code'                  => $fields[30] ?? null,
                    'site_name'                  => $fields[31] ?? null,
                    'hold_pickup_area_id'        => $this->toIntOrNull($fields[32] ?? null),
                    'suspended'                  => $this->toBoolOrNull($fields[33] ?? null),
                    'system_blocks'              => $this->toIntOrNull($fields[34] ?? null),
                    'ovd_notice_count'           => $this->toIntOrNull($fields[35] ?? null),
                    'ovd_notice_date'            => $this->toDateOrNull($fields[36] ?? null),
                    'billing_notice_sent'        => $this->toBoolOrNull($fields[37] ?? null),
                    // Keep original export precision (milliseconds) as string.
                    'export_datetime' => $fields[38] ?? null,
                ];
            }

            $data += [
                'source_filename'    => $filename,
                'source_line_number' => $lineNumber,
                'source_raw_line'    => StringSanitizer::sanitize($line),
                'imported_at'        => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];

            $batch[] = $data;

            // Flush batch when it reaches the threshold
            if (count($batch) >= IMPORT_BATCH_SIZE) {
                DB::table('polaris_hold_notifications')->insert($batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            DB::table('polaris_hold_notifications')->insert($batch);
        }

        fclose($handle);
    }

    /**
     * Import a single renewal export file into polaris_renewal_notifications.
     *
     * Data is stored as-is from Polaris, without transformation, using
     * the column order defined in the Polaris renew export query.
     *
     * @param string $path absolute path to the export file to import
     */
    protected function importRenewalFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $filename   = basename($path);
        $lineNumber = 0;

        $batch = [];
        $now   = now();

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }

            ++$lineNumber;

            $fields = $this->parsePipeFields($line);

            // Expecting the order defined in renew_full.sql (same structure as holds)
            $data = [
                'patron_id'                  => $fields[0] ?? null,
                'item_record_id'             => $fields[1] ?? null,
                'notification_type_id'       => $fields[2] ?? null,
                'delivery_option_id'         => $fields[3] ?? null,
                'notification_queue_id'      => $fields[4] ?? null,
                'processed'                  => $fields[5] ?? null,
                'notification_creation_date' => $fields[6] ?? null,
                'patron_barcode'             => $fields[7] ?? null,
                'item_barcode'               => $fields[8] ?? null,
                'title'                      => StringSanitizer::sanitize($fields[9] ?? null),
                'bibliographic_record_id'    => $fields[10] ?? null,
                'patron_first_name'          => StringSanitizer::sanitize($fields[11] ?? null),
                'patron_last_name'           => StringSanitizer::sanitize($fields[12] ?? null),
                'patron_email'               => $fields[13] ?? null,
                'patron_phone'               => $fields[14] ?? null,
                'patron_record_status_id'    => $fields[15] ?? null,
                'patron_expiration_date'     => $fields[16] ?? null,
                'due_date'                   => $fields[17] ?? null,
                'days_until_due'             => $fields[18] ?? null,
                'renewals'                   => $fields[19] ?? null,
                'renewal_limit'              => $fields[20] ?? null,
                'material_type_id'           => $fields[21] ?? null,
                'checkout_branch_id'         => $fields[22] ?? null,
                'loan_units'                 => $fields[23] ?? null,
                'sys_hold_request_id'        => $fields[24] ?? null,
                'sys_hold_status_id'         => $fields[25] ?? null,
                'activation_date'            => $fields[26] ?? null,
                'hold_notification_date'     => $fields[27] ?? null,
                'hold_till_date'             => $fields[28] ?? null,
                'pickup_organization_id'     => $fields[29] ?? null,
                'site_code'                  => $fields[30] ?? null,
                'site_name'                  => $fields[31] ?? null,
                'hold_pickup_area_id'        => $fields[32] ?? null,
                'suspended'                  => $fields[33] ?? null,
                'system_blocks'              => $fields[34] ?? null,
                'ovd_notice_count'           => $fields[35] ?? null,
                'ovd_notice_date'            => $fields[36] ?? null,
                'billing_notice_sent'        => $fields[37] ?? null,
                'export_datetime'            => $fields[38] ?? null,
                'source_filename'            => $filename,
                'source_line_number'         => $lineNumber,
                'source_raw_line'            => StringSanitizer::sanitize($line),
                'imported_at'                => $now,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ];

            $batch[] = $data;

            // Flush batch when it reaches the threshold
            if (count($batch) >= IMPORT_BATCH_SIZE) {
                DB::table('polaris_renewal_notifications')->insert($batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            DB::table('polaris_renewal_notifications')->insert($batch);
        }

        fclose($handle);
    }

    /**
     * Import a single overdue export file into polaris_overdue_notifications.
     *
     * Each row is a notification from the Polaris NotificationQueue for overdue items.
     * The file structure matches overdue_full.sql.
     *
     * @param string $path absolute path to the overdue export file
     */
    protected function importOverdueFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $filename   = basename($path);
        $lineNumber = 0;

        $batch = [];
        $now   = now();

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }

            ++$lineNumber;

            $fields = $this->parsePipeFields($line);

            // Expecting the order defined in overdue_full.sql
            $data = [
                'patron_id'                  => $fields[0] ?? null,
                'item_record_id'             => $fields[1] ?? null,
                'notification_type_id'       => $fields[2] ?? null,
                'delivery_option_id'         => $fields[3] ?? null,
                'notification_queue_id'      => $fields[4] ?? null,
                'processed'                  => $fields[5] ?? null,
                'notification_creation_date' => $fields[6] ?? null,
                'patron_barcode'             => $fields[7] ?? null,
                'item_barcode'               => $fields[8] ?? null,
                'title'                      => StringSanitizer::sanitize($fields[9] ?? null),
                'bibliographic_record_id'    => $fields[10] ?? null,
                'patron_first_name'          => $fields[11] ?? null,
                'patron_last_name'           => $fields[12] ?? null,
                'patron_email'               => $fields[13] ?? null,
                'patron_phone'               => $fields[14] ?? null,
                'patron_record_status_id'    => $fields[15] ?? null,
                'patron_expiration_date'     => $fields[16] ?? null,
                'due_date'                   => $fields[17] ?? null,
                'days_until_due'             => $fields[18] ?? null,
                'renewals'                   => $fields[19] ?? null,
                'renewal_limit'              => $fields[20] ?? null,
                'material_type_id'           => $fields[21] ?? null,
                'checkout_branch_id'         => $fields[22] ?? null,
                'loan_units'                 => $fields[23] ?? null,
                'sys_hold_request_id'        => $fields[24] ?? null,
                'sys_hold_status_id'         => $fields[25] ?? null,
                'activation_date'            => $fields[26] ?? null,
                'hold_notification_date'     => $fields[27] ?? null,
                'hold_till_date'             => $fields[28] ?? null,
                'pickup_organization_id'     => $fields[29] ?? null,
                'site_code'                  => $fields[30] ?? null,
                'site_name'                  => $fields[31] ?? null,
                'hold_pickup_area_id'        => $fields[32] ?? null,
                'suspended'                  => $fields[33] ?? null,
                'system_blocks'              => $fields[34] ?? null,
                'ovd_notice_count'           => $fields[35] ?? null,
                'ovd_notice_date'            => $fields[36] ?? null,
                'billing_notice_sent'        => $fields[37] ?? null,
                'export_datetime'            => $fields[38] ?? null,
                'source_filename'            => $filename,
                'source_line_number'         => $lineNumber,
                'source_raw_line'            => StringSanitizer::sanitize($line),
                'imported_at'                => $now,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ];

            $batch[] = $data;

            // Flush batch when it reaches the threshold
            if (count($batch) >= IMPORT_BATCH_SIZE) {
                DB::table('polaris_overdue_notifications')->insert($batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            DB::table('polaris_overdue_notifications')->insert($batch);
        }

        fclose($handle);
    }

    /**
     * Import a notification_history export file into polaris_notification_history.
     *
     * Data structure from notification_history.sql:
     * PatronID|ItemRecordID|NotificationTypeID|DeliveryOptionID|ReportingOrgID|NoticeDate|Title|Amount|NotificationStatusID|TxnID|ExportDateTime
     *
     * @param string $path absolute path to the export file to import
     */
    protected function importNotificationHistoryFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $filename   = basename($path);
        $lineNumber = 0;

        $batch = [];
        $now   = now();

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }

            ++$lineNumber;

            $fields = str_getcsv($line, '|');

            // Convert string "NULL" to actual null values
            $fields = array_map(fn ($v) => ('NULL' === $v || '' === $v) ? null : $v, $fields);

            // Expecting 11 fields from notification_history.sql
            if (count($fields) < 11) {
                \Log::warning('NotificationHistory import: invalid column count', [
                    'filename' => $filename,
                    'line'     => $lineNumber,
                    'count'    => count($fields),
                ]);

                continue;
            }

            $batch[] = [
                'patron_id'              => $fields[0] ?? null,
                'item_record_id'         => $fields[1] ?? null,
                'notification_type_id'   => $fields[2] ?? null,
                'delivery_option_id'     => $fields[3] ?? null,
                'reporting_org_id'       => $fields[4] ?? null,
                'notice_date'            => $fields[5] ?? null,
                'title'                  => StringSanitizer::sanitize($fields[6] ?? null),
                'amount'                 => $fields[7]  ?? null,
                'notification_status_id' => $fields[8]  ?? null,
                'txn_id'                 => $fields[9]  ?? null,
                'export_datetime'        => $fields[10] ?? null,
                'created_at'             => $now,
                'updated_at'             => $now,
            ];

            // Flush batch when it reaches the threshold
            if (count($batch) >= IMPORT_BATCH_SIZE) {
                DB::table('polaris_notification_history')->insert($batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            DB::table('polaris_notification_history')->insert($batch);
        }

        fclose($handle);
    }

    /**
     * Import a PhoneNotices CSV export into polaris_phone_notices.
     *
     * PhoneNotices exports are CSV files with quoted fields.
     * Used for verification of voice/SMS notifications exported to Shoutbomb.
     *
     * @param string $path absolute path to the CSV file to import
     */
    protected function importPhoneNoticesFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $filename   = basename($path);
        $lineNumber = 0;

        // Pre-load lookup tables for FK validation (avoids per-row queries)
        $languageMap       = DB::table('languages')->pluck('id', 'admin_language_id')->toArray();
        $notificationTypes = DB::table('notification_types')->pluck('id')->flip()->toArray();
        $deliveryOptions   = DB::table('delivery_options')->pluck('id')->flip()->toArray();

        // Extract export datetime from filename once
        $exportDatetime = null;
        if (preg_match('/PhoneNotices_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})/', $filename, $matches)) {
            $exportDatetime = sprintf('%s %s:%s:%s', $matches[1], $matches[2], $matches[3], $matches[4]);
        }

        $batch = [];
        $now   = now();

        while (($line = fgets($handle)) !== false) {
            // Remove BOM if present (first line)
            if (0 === $lineNumber) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }

            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }

            ++$lineNumber;

            // Parse CSV (fields are quoted)
            $fields = str_getcsv($line, ',');

            // Normalize blank/NULL-like values to actual null
            $fields = array_map(function ($v) {
                $v = is_string($v) ? trim($v) : $v;

                return ('' === $v || 'NULL' === strtoupper((string) $v)) ? null : $v;
            }, $fields);

            // Skip if not enough fields
            if (count($fields) < 22) {
                \Log::warning('PhoneNotices import: invalid column count', [
                    'filename' => $filename,
                    'line'     => $lineNumber,
                    'count'    => count($fields),
                ]);

                continue;
            }

            // Determine delivery_option_id from media_type (V=Voice=3, T=SMS=8)
            $mediaType        = $fields[0] ?? null;
            $deliveryOptionId = match ($mediaType) {
                'V'     => 3,
                'T'     => 8,
                default => null,
            };

            $adminLanguageId          = $fields[16] ?? null;
            $notificationTypeId       = $fields[17] ?? null;
            $deliveryOptionFromExport = $fields[15] ?? null;
            $deliveryOptionDuplicate  = $fields[18] ?? null;

            // Use cached lookups instead of per-row queries
            $languageId               = $adminLanguageId ? ($languageMap[$adminLanguageId] ?? null) : null;
            $notificationTypeId       = ($notificationTypeId && isset($notificationTypes[$notificationTypeId])) ? $notificationTypeId : null;
            $deliveryOptionFromExport = ($deliveryOptionFromExport && isset($deliveryOptions[$deliveryOptionFromExport])) ? $deliveryOptionFromExport : null;
            $deliveryOptionDuplicate  = ($deliveryOptionDuplicate && isset($deliveryOptions[$deliveryOptionDuplicate])) ? $deliveryOptionDuplicate : null;

            $batch[] = [
                'media_type'                     => $fields[0]  ?? null,
                'language_code'                  => $fields[1]  ?? null,
                'notice_type'                    => $fields[2]  ?? null,
                'notification_level'             => $fields[3]  ?? null,
                'patron_barcode'                 => $fields[4]  ?? null,
                'patron_title'                   => $fields[5]  ?? null,
                'patron_first_name'              => $fields[6]  ?? null,
                'patron_last_name'               => $fields[7]  ?? null,
                'patron_phone'                   => $fields[8]  ?? null,
                'patron_email'                   => $fields[9]  ?? null,
                'library_code'                   => $fields[10] ?? null,
                'site_name'                      => StringSanitizer::sanitize($fields[11] ?? null),
                'item_barcode'                   => $fields[12] ?? null,
                'hold_till_date'                 => $this->toDateOrNull($fields[13] ?? null),
                'item_title'                     => StringSanitizer::sanitize($fields[14] ?? null),
                'delivery_option_id_from_export' => $deliveryOptionFromExport,
                'language_id'                    => $languageId,
                'notification_type_id'           => $notificationTypeId,
                'delivery_option_id_duplicate'   => $deliveryOptionDuplicate,
                'patron_id'                      => $fields[19] ?? null,
                'item_record_id'                 => $fields[20] ?? null,
                'sys_hold_request_id'            => $fields[21] ?? null,
                'delivery_option_id'             => $deliveryOptionId,
                'export_datetime'                => $exportDatetime,
                'created_at'                     => $now,
                'updated_at'                     => $now,
            ];

            // Flush batch when it reaches the threshold
            if (count($batch) >= IMPORT_BATCH_SIZE) {
                DB::table('polaris_phone_notices')->insert($batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            DB::table('polaris_phone_notices')->insert($batch);
        }

        fclose($handle);
    }

    /**
     * Parse pipe-delimited export rows and normalize NULL-like tokens.
     */
    protected function parsePipeFields(string $line): array
    {
        $fields = str_getcsv($line, '|');

        return array_map(function ($v) {
            $v = is_string($v) ? trim($v) : $v;

            return ('NULL' === strtoupper((string) $v) || '' === (string) $v) ? null : $v;
        }, $fields);
    }

    /**
     * Detect legacy compact holds rows (older export format with fewer columns).
     */
    protected function looksLikeLegacyCompactHoldRow(array $fields): bool
    {
        return 29 === count($fields)
            && !$this->isDateTimeLike($fields[6] ?? null)
            && $this->isDateTimeLike($fields[12] ?? null)
            && !empty($fields[7] ?? null);
    }

    /**
     * Map legacy compact holds row into current hold schema fields.
     */
    protected function mapLegacyCompactHoldRow(array $fields): array
    {
        return [
            // Legacy compact rows observed in production imports:
            // item_record_id|patron_id|sys_hold_request_id|notification_type_id|delivery_option_id|pickup_org|queue_id|title|activation_date|sys_hold_status_id|hold_till_date|patron_barcode|export_datetime|item_barcode|site_code|site_name|first|last|phone|email|processed|system_blocks|suspended|patron_expiration_date|hold_notification_date|patron_record_status_id|ovd_notice_count|ovd_notice_date|billing_notice_sent
            'patron_id'                  => $this->toIntOrNull($fields[1] ?? null),
            'item_record_id'             => $this->toIntOrNull($fields[0] ?? null),
            'notification_type_id'       => $this->toIntOrNull($fields[3] ?? null),
            'delivery_option_id'         => $this->toIntOrNull($fields[4] ?? null),
            'notification_queue_id'      => $this->toIntOrNull($fields[6] ?? null),
            'processed'                  => $this->toBoolOrNull($fields[20] ?? null),
            'notification_creation_date' => null,
            'patron_barcode'             => $fields[11] ?? null,
            'item_barcode'               => $fields[13] ?? null,
            'title'                      => StringSanitizer::sanitize($fields[7] ?? null),
            'bibliographic_record_id'    => null,
            'patron_first_name'          => StringSanitizer::sanitize($fields[16] ?? null),
            'patron_last_name'           => StringSanitizer::sanitize($fields[17] ?? null),
            'patron_email'               => $fields[19] ?? null,
            'patron_phone'               => $fields[18] ?? null,
            'patron_record_status_id'    => $this->toIntOrNull($fields[25] ?? null),
            'patron_expiration_date'     => $this->toDateOrNull($fields[23] ?? null),
            'due_date'                   => null,
            'days_until_due'             => null,
            'renewals'                   => null,
            'renewal_limit'              => null,
            'material_type_id'           => null,
            'checkout_branch_id'         => null,
            'loan_units'                 => null,
            'sys_hold_request_id'        => $this->toIntOrNull($fields[2] ?? null),
            'sys_hold_status_id'         => $this->toIntOrNull($fields[9] ?? null),
            'activation_date'            => $this->toDateOrNull($fields[8] ?? null),
            'hold_notification_date'     => $this->toDateOrNull($fields[24] ?? null),
            'hold_till_date'             => $this->toDateOrNull($fields[10] ?? null),
            'pickup_organization_id'     => $this->toIntOrNull($fields[5] ?? null),
            'site_code'                  => $fields[14] ?? null,
            'site_name'                  => StringSanitizer::sanitize($fields[15] ?? null),
            'hold_pickup_area_id'        => null,
            'suspended'                  => $this->toBoolOrNull($fields[22] ?? null),
            'system_blocks'              => $this->toIntOrNull($fields[21] ?? null),
            'ovd_notice_count'           => $this->toIntOrNull($fields[26] ?? null),
            'ovd_notice_date'            => $this->toDateOrNull($fields[27] ?? null),
            'billing_notice_sent'        => $this->toBoolOrNull($fields[28] ?? null),
            // Keep original export precision (milliseconds) as string.
            'export_datetime' => $fields[12] ?? null,
        ];
    }

    protected function toIntOrNull($value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value) && preg_match('/^-?\d+$/', (string) $value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Convert a value to an integer boolean (1/0) for MySQL compatibility.
     * Returns null if the value cannot be interpreted as boolean.
     *
     * @param mixed $value
     */
    protected function toBoolOrNull($value): ?int
    {
        if (null === $value) {
            return null;
        }

        $normalized = strtolower((string) $value);

        if (in_array($normalized, ['1', 'true', 't', 'yes', 'y'], true)) {
            return 1;
        }

        if (in_array($normalized, ['0', 'false', 'f', 'no', 'n'], true)) {
            return 0;
        }

        return null;
    }

    protected function toDateOrNull($value): ?string
    {
        if (null === $value) {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function toDateTimeOrNull($value): ?string
    {
        if (null === $value) {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function isDateTimeLike($value): bool
    {
        if (null === $value) {
            return false;
        }

        try {
            new \DateTimeImmutable((string) $value);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
