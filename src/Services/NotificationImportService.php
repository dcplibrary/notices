<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\NoticeFailureReport;
use Dcplibrary\Notices\Models\NotificationHold;
use Dcplibrary\Notices\Models\NotificationOverdue;
use Dcplibrary\Notices\Models\NotificationRenewal;
use Dcplibrary\Notices\Models\PatronNotificationPreference;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified notification import service.
 *
 * Handles importing from FTP exports with proper phone normalization
 * and enrichment of delivery_option_id and notification_type_id.
 */
class NotificationImportService
{
    protected ShoutbombFTPService $ftpService;

    public function __construct(ShoutbombFTPService $ftpService)
    {
        $this->ftpService = $ftpService;
    }

    /**
     * Normalize phone number to 10 digits.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-digits
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // If 11 digits starting with 1, remove the 1
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        // Return only if exactly 10 digits
        return strlen($digits) === 10 ? $digits : null;
    }

    /**
     * Get the available date range from FTP files.
     */
    public function getAvailableDateRange(): ?array
    {
        if (!$this->ftpService->connect()) {
            return null;
        }

        $files = $this->ftpService->listFiles('/');
        $dates = [];

        foreach ($files as $file) {
            if (preg_match('/_(\d{4}-\d{2}-\d{2})/', basename($file), $matches)) {
                $dates[] = Carbon::parse($matches[1]);
            }
        }

        $this->ftpService->disconnect();

        if (empty($dates)) {
            return null;
        }

        return [
            'start' => min($dates),
            'end' => max($dates),
        ];
    }

    /**
     * List available patron list files for date range.
     */
    public function listPatronListFiles(Carbon $startDate, Carbon $endDate): array
    {
        if (!$this->ftpService->connect()) {
            return ['voice_count' => 0, 'text_count' => 0];
        }

        $files = $this->ftpService->listFiles('/');
        $voiceCount = 0;
        $textCount = 0;

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/_(\d{4}-\d{2}-\d{2})/', $basename, $matches)) {
                $fileDate = Carbon::parse($matches[1]);
                if ($fileDate->between($startDate, $endDate)) {
                    if (str_contains($basename, 'voice_patrons')) {
                        $voiceCount++;
                    } elseif (str_contains($basename, 'text_patrons')) {
                        $textCount++;
                    }
                }
            }
        }

        $this->ftpService->disconnect();

        return ['voice_count' => $voiceCount, 'text_count' => $textCount];
    }

    /**
     * Import patron lists from FTP.
     */
    public function importPatronLists(Carbon $startDate, Carbon $endDate): array
    {
        $results = ['voice' => 0, 'text' => 0];

        if (!$this->ftpService->connect()) {
            throw new Exception('Failed to connect to FTP');
        }

        $files = $this->ftpService->listFiles('/');

        foreach ($files as $file) {
            $basename = basename($file);
            if (!preg_match('/_(\d{4}-\d{2}-\d{2})/', $basename, $matches)) {
                continue;
            }

            $fileDate = Carbon::parse($matches[1]);
            if (!$fileDate->between($startDate, $endDate)) {
                continue;
            }

            // Determine delivery method from filename
            $deliveryMethod = null;
            if (str_contains($basename, 'voice_patrons')) {
                $deliveryMethod = 'voice';
            } elseif (str_contains($basename, 'text_patrons')) {
                $deliveryMethod = 'text';
            } else {
                continue;
            }

            // Download and parse
            $localPath = $this->ftpService->downloadFile('/' . $basename);
            if (!$localPath) {
                continue;
            }

            $imported = $this->parseAndImportPatronList($localPath, $basename, $deliveryMethod, $fileDate);
            $results[$deliveryMethod] += $imported;

            Log::info("Imported patron list", [
                'file' => $basename,
                'type' => $deliveryMethod,
                'count' => $imported,
            ]);
        }

        $this->ftpService->disconnect();

        return $results;
    }

    /**
     * Parse and import a patron list file.
     */
    protected function parseAndImportPatronList(
        string $filePath,
        string $filename,
        string $deliveryMethod,
        Carbon $importDate
    ): int {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $deliveryOptionId = $deliveryMethod === 'voice' ? 3 : 8;
        $batch = [];
        $imported = 0;

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) < 2) {
                continue;
            }

            $phone = self::normalizePhone($parts[0]);
            $barcode = trim($parts[1]);

            if (!$phone || !$barcode) {
                continue;
            }

            $batch[] = [
                'patron_barcode' => $barcode,
                'phone_voice1' => $phone,
                'delivery_method' => $deliveryMethod,
                'delivery_option_id' => $deliveryOptionId,
                'import_date' => $importDate->format('Y-m-d'),
                'source_file' => $filename,
                'imported_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= 500) {
                PatronNotificationPreference::upsert(
                    $batch,
                    ['patron_barcode', 'import_date'],
                    ['phone_voice1', 'delivery_method', 'delivery_option_id', 'source_file', 'updated_at']
                );
                $imported += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            PatronNotificationPreference::upsert(
                $batch,
                ['patron_barcode', 'import_date'],
                ['phone_voice1', 'delivery_method', 'delivery_option_id', 'source_file', 'updated_at']
            );
            $imported += count($batch);
        }

        return $imported;
    }

    /**
     * List PhoneNotices files for date range.
     */
    public function listPhoneNoticeFiles(Carbon $startDate, Carbon $endDate): array
    {
        if (!$this->ftpService->connect()) {
            return ['count' => 0];
        }

        $files = $this->ftpService->listFiles('/');
        $count = 0;

        foreach ($files as $file) {
            $basename = basename($file);
            if (str_contains($basename, 'PhoneNotices') && str_ends_with($basename, '.csv')) {
                if (preg_match('/_(\d{4}-\d{2}-\d{2})/', $basename, $matches)) {
                    $fileDate = Carbon::parse($matches[1]);
                    if ($fileDate->between($startDate, $endDate)) {
                        $count++;
                    }
                }
            }
        }

        $this->ftpService->disconnect();

        return ['count' => $count];
    }

    /**
     * Import PhoneNotices.csv files from FTP.
     */
    public function importPhoneNotices(Carbon $startDate, Carbon $endDate): int
    {
        // Delegate to the dedicated PolarisPhoneNoticeImporter so that
        // all PhoneNotices ingestion goes through a single, enriched path.
        /** @var PolarisPhoneNoticeImporter $importer */
        $importer = app(PolarisPhoneNoticeImporter::class);

        $results = $importer->importFromFTP(null, $startDate, $endDate);

        return $results['imported'] ?? 0;
    }

    /**
     * Parse and import PhoneNotices.csv with all 25 fields.
     */
    protected function parseAndImportPhoneNotices(
        string $filePath,
        string $filename,
        Carbon $importDate
    ): int {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return 0;
        }

        $batch = [];
        $imported = 0;

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 22) {
                continue;
            }

            $batch[] = [
                'delivery_method' => strtoupper(trim($data[0])) ?: null,
                'language' => !empty($data[1]) ? trim($data[1]) : null,
                'notice_type' => !empty($data[2]) ? (int) trim($data[2]) : null,
                'notification_level' => !empty($data[3]) ? (int) trim($data[3]) : null,
                'patron_barcode' => trim($data[4]),
                'patron_title' => !empty($data[5]) ? trim($data[5]) : null,
                'name_first' => !empty($data[6]) ? trim($data[6]) : null,
                'name_last' => !empty($data[7]) ? trim($data[7]) : null,
                'phone_number' => self::normalizePhone($data[8]),
                'email_address' => !empty($data[9]) ? trim($data[9]) : null,
                'site_code' => !empty($data[10]) ? trim($data[10]) : null,
                'site_name' => !empty($data[11]) ? trim($data[11]) : null,
                'item_barcode' => !empty($data[12]) ? trim($data[12]) : null,
                'due_date' => $this->parseDate($data[13] ?? null),
                'browse_title' => !empty($data[14]) ? trim($data[14]) : null,
                'reporting_org_id' => !empty($data[15]) ? (int) trim($data[15]) : null,
                'language_id' => !empty($data[16]) ? (int) trim($data[16]) : null,
                'notification_type_id' => !empty($data[17]) ? (int) trim($data[17]) : null,
                'delivery_option_id' => !empty($data[18]) ? (int) trim($data[18]) : null,
                'patron_id' => !empty($data[19]) ? (int) trim($data[19]) : null,
                'item_record_id' => !empty($data[20]) ? (int) trim($data[20]) : null,
                'sys_hold_request_id' => isset($data[21]) && !empty($data[21]) ? (int) trim($data[21]) : null,
                'pickup_area_description' => isset($data[22]) && !empty($data[22]) ? trim($data[22]) : null,
                'txn_id' => isset($data[23]) && !empty($data[23]) ? (int) trim($data[23]) : null,
                'account_balance' => isset($data[24]) && !empty($data[24]) ? (float) trim($data[24]) : null,
                'import_date' => $importDate->format('Y-m-d'),
                'source_file' => $filename,
                'imported_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= 500) {
                PolarisPhoneNotice::insert($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            PolarisPhoneNotice::insert($batch);
            $imported += count($batch);
        }

        return $imported;
    }

    /**
     * List notification export files for date range.
     */
    public function listNotificationFiles(string $type, Carbon $startDate, Carbon $endDate): array
    {
        if (!$this->ftpService->connect()) {
            return ['count' => 0];
        }

        $files = $this->ftpService->listFiles('/');
        $pattern = "{$type}_submitted_";
        $count = 0;

        foreach ($files as $file) {
            $basename = basename($file);
            if (str_contains($basename, $pattern)) {
                if (preg_match('/_(\d{4}-\d{2}-\d{2})/', $basename, $matches)) {
                    $fileDate = Carbon::parse($matches[1]);
                    if ($fileDate->between($startDate, $endDate)) {
                        $count++;
                    }
                }
            }
        }

        $this->ftpService->disconnect();

        return ['count' => $count];
    }

    /**
     * Import notification exports (holds, overdue, renew).
     */
    public function importNotifications(string $type, Carbon $startDate, Carbon $endDate): int
    {
        if (!$this->ftpService->connect()) {
            throw new Exception('Failed to connect to FTP');
        }

        $files = $this->ftpService->listFiles('/');
        $pattern = "{$type}_submitted_";
        $totalImported = 0;

        foreach ($files as $file) {
            $basename = basename($file);
            if (!str_contains($basename, $pattern)) {
                continue;
            }

            if (!preg_match('/_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})/', $basename, $matches)) {
                continue;
            }

            $fileDate = Carbon::parse($matches[1]);
            if (!$fileDate->between($startDate, $endDate)) {
                continue;
            }

            $exportTimestamp = Carbon::createFromFormat(
                'Y-m-d H-i-s',
                "{$matches[1]} {$matches[2]}-{$matches[3]}-{$matches[4]}"
            );

            $localPath = $this->ftpService->downloadFile('/' . $basename);
            if (!$localPath) {
                continue;
            }

            $imported = $this->parseAndImportNotifications($localPath, $basename, $type, $exportTimestamp);
            $totalImported += $imported;

            Log::info("Imported {$type} notifications", [
                'file' => $basename,
                'count' => $imported,
            ]);
        }

        $this->ftpService->disconnect();

        return $totalImported;
    }

    /**
     * Parse and import notification files.
     */
    protected function parseAndImportNotifications(
        string $filePath,
        string $filename,
        string $type,
        Carbon $exportTimestamp
    ): int {
        // Use encoding-safe reader to handle Windows-1252 / ISO-8859-1 exports
        // that include smart quotes and other non-UTF-8 characters.
        $lines = $this->readFileWithEncoding($filePath);
        $imported = 0;
        $batch = [];

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            $record = $this->parseNotificationLine($parts, $type, $filename, $exportTimestamp);

            if (!$record) {
                continue;
            }

            $batch[] = $record;

            if (count($batch) >= 500) {
                $this->insertNotificationBatch($type, $batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->insertNotificationBatch($type, $batch);
            $imported += count($batch);
        }

        return $imported;
    }

    /**
     * Parse a single notification line based on type.
     */
    protected function parseNotificationLine(
        array $parts,
        string $type,
        string $filename,
        Carbon $exportTimestamp
    ): ?array {
        return match ($type) {
            'holds' => $this->parseHoldsLine($parts, $filename, $exportTimestamp),
            'overdue' => $this->parseOverdueLine($parts, $filename, $exportTimestamp),
            'renew' => $this->parseRenewLine($parts, $filename, $exportTimestamp),
            default => null,
        };
    }

    /**
     * Parse holds line: BTitle|CreationDate|SysHoldRequestID|PatronID|PickupOrgID|HoldTillDate|PatronBarcode.
     */
    protected function parseHoldsLine(array $parts, string $filename, Carbon $exportTimestamp): ?array
    {
        if (count($parts) < 7) {
            return null;
        }

        return [
            'browse_title' => trim($parts[0]),
            'creation_date' => $this->parseDate($parts[1]),
            'sys_hold_request_id' => (int) trim($parts[2]),
            'patron_id' => (int) trim($parts[3]),
            'pickup_organization_id' => (int) trim($parts[4]),
            'hold_till_date' => $this->parseDate($parts[5]),
            'patron_barcode' => trim($parts[6]),
            'notification_type_id' => 2, // Always 2 for holds
            'delivery_option_id' => null, // Will be enriched
            'export_timestamp' => $exportTimestamp,
            'source_file' => $filename,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Parse overdue/renew line: PatronID|ItemBarcode|Title|DueDate|ItemRecordID|...|Renewals|BibRecordID|RenewalLimit|PatronBarcode.
     */
    protected function parseOverdueLine(array $parts, string $filename, Carbon $exportTimestamp): ?array
    {
        if (count($parts) < 13) {
            return null;
        }

        return [
            'patron_id' => (int) trim($parts[0]),
            'item_barcode' => trim($parts[1]),
            'title' => trim($parts[2]),
            'due_date' => $this->parseDate($parts[3]),
            'item_record_id' => (int) trim($parts[4]),
            'renewals' => (int) trim($parts[9]),
            'bibliographic_record_id' => (int) trim($parts[10]),
            'renewal_limit' => (int) trim($parts[11]),
            'patron_barcode' => trim($parts[12]),
            'notification_type_id' => null, // Must be enriched from PhoneNotices
            'delivery_option_id' => null, // Will be enriched
            'export_timestamp' => $exportTimestamp,
            'source_file' => $filename,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Parse renew line (same format as overdue).
     */
    protected function parseRenewLine(array $parts, string $filename, Carbon $exportTimestamp): ?array
    {
        $record = $this->parseOverdueLine($parts, $filename, $exportTimestamp);
        if ($record) {
            $record['notification_type_id'] = 7; // Always 7 for renewals
        }

        return $record;
    }

    /**
     * Insert batch of notifications.
     */
    protected function insertNotificationBatch(string $type, array $batch): void
    {
        match ($type) {
            'holds' => NotificationHold::insert($batch),
            'overdue' => NotificationOverdue::insert($batch),
            'renew' => NotificationRenewal::insert($batch),
        };
    }

    /**
     * Import failure reports from email.
     */
    public function importFailureReports(Carbon $startDate, Carbon $endDate): int
    {
        // This would integrate with email parsing - stub for now
        return 0;
    }

    /**
     * Run enrichment process.
     */
    public function runEnrichment(): array
    {
        $notificationsEnriched = 0;
        $failuresEnriched = 0;

        // Enrich notifications with delivery_option_id from patron lists
        $notificationsEnriched += $this->enrichNotificationsWithDeliveryOption();

        // Enrich overdue notifications with notification_type_id from PhoneNotices
        $notificationsEnriched += $this->enrichOverdueWithNotificationType();

        // Enrich failures with PhoneNotices data
        $failuresEnriched = $this->enrichFailuresWithPhoneNotices();

        return [
            'notifications' => $notificationsEnriched,
            'failures' => $failuresEnriched,
        ];
    }

    /**
     * Enrich notifications with delivery_option_id from patron lists.
     */
    protected function enrichNotificationsWithDeliveryOption(): int
    {
        $total = 0;

        // Enrich holds
        $total += DB::update("
            UPDATE notifications_holds h
            INNER JOIN patrons_notification_preferences pp
                ON h.patron_barcode = pp.patron_barcode
               AND DATE(h.export_timestamp) = pp.import_date
            SET h.delivery_option_id = pp.delivery_option_id,
                h.updated_at = NOW()
            WHERE h.delivery_option_id IS NULL
        ");

        // Enrich overdue
        $total += DB::update("
            UPDATE notifications_overdue o
            INNER JOIN patrons_notification_preferences pp
                ON o.patron_barcode = pp.patron_barcode
               AND DATE(o.export_timestamp) = pp.import_date
            SET o.delivery_option_id = pp.delivery_option_id,
                o.updated_at = NOW()
            WHERE o.delivery_option_id IS NULL
        ");

        // Enrich renewals
        $total += DB::update("
            UPDATE notifications_renewal r
            INNER JOIN patrons_notification_preferences pp
                ON r.patron_barcode = pp.patron_barcode
               AND DATE(r.export_timestamp) = pp.import_date
            SET r.delivery_option_id = pp.delivery_option_id,
                r.updated_at = NOW()
            WHERE r.delivery_option_id IS NULL
        ");

        return $total;
    }

    /**
     * Enrich overdue notifications with notification_type_id from PhoneNotices.
     */
    protected function enrichOverdueWithNotificationType(): int
    {
        return DB::update("
            UPDATE notifications_overdue o
            INNER JOIN polaris_phone_notices pn
                ON o.patron_id = pn.patron_id
               AND o.item_record_id = pn.item_record_id
               AND DATE(o.export_timestamp) = pn.import_date
            SET o.notification_type_id = pn.notification_type_id,
                o.updated_at = NOW()
            WHERE o.notification_type_id IS NULL
              AND pn.notification_type_id IN (1, 7, 8, 11, 12, 13)
        ");
    }

    /**
     * Enrich failures with PhoneNotices data.
     */
    protected function enrichFailuresWithPhoneNotices(): int
    {
        $failureTable = (new NoticeFailureReport())->getTable();

        return DB::update("
            UPDATE {$failureTable} nfr
            INNER JOIN polaris_phone_notices pn
                ON nfr.patron_id = pn.patron_id
               AND DATE(nfr.received_at) = pn.import_date
            SET nfr.notification_type_id = pn.notification_type_id,
                nfr.phone_notices_import_id = pn.id,
                nfr.item_record_id = pn.item_record_id,
                nfr.sys_hold_request_id = pn.sys_hold_request_id,
                nfr.notification_queued_at = pn.import_date,
                nfr.updated_at = NOW()
            WHERE nfr.notification_type_id IS NULL
        ");
    }

    /**
     * Parse date string safely.
     */
    protected function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            // Handle MM/DD/YYYY format
            if (preg_match('#^\\d{1,2}/\\d{1,2}/\\d{4}$#', $date)) {
                return Carbon::createFromFormat('m/d/Y', $date)->format('Y-m-d');
            }

            return Carbon::parse($date)->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Read a text file and return UTF-8 lines, handling common Polaris
     * encodings (UTF-8, Windows-1252, ISO-8859-1) and BOM.
     */
    protected function readFileWithEncoding(string $filePath): array
    {
        $content = $this->readFileContentWithEncoding($filePath);
        if ($content === false) {
            return [];
        }

        // Split into lines and trim, removing empties
        return array_filter(
            array_map('trim', preg_split("#\\r\\n|\\n|\\r#", $content)),
            static fn ($line) => $line !== ''
        );
    }

    /**
     * Read raw file content, detect encoding, and normalize to UTF-8.
     */
    protected function readFileContentWithEncoding(string $filePath): string|false
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Strip UTF-8 BOM if present
        $bom = pack('H*', 'EFBBBF');
        if (str_starts_with($content, $bom)) {
            $content = substr($content, 3);
        }

        // Detect likely encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Ensure valid UTF-8; as a last resort, scrub invalid sequences
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        return $content;
    }
}
