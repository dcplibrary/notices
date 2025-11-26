<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class ShoutbombSubmissionParser
{
    /**
     * Parse a holds submission file.
     */
    public function parseHoldsFile(string $filePath): array
    {
        $submissions = [];
        $lines = $this->readFileWithEncoding($filePath);

        foreach ($lines as $lineNumber => $line) {
            try {
                $data = $this->parseHoldsLine($line);
                if ($data) {
                    $submissions[] = $data;
                }
            } catch (Exception $e) {
                Log::warning("Failed to parse line {$lineNumber} in holds file: {$e->getMessage()}", [
                    'file' => $filePath,
                    'line' => $line,
                ]);
            }
        }

        return $submissions;
    }

    /**
     * Parse a single holds line.
     * Based on holds.sql:
     * BTitle|CreationDate|SysHoldRequestID|PatronID|PickupOrganizationID|HoldTillDate|PBarcode
     * Note: PBarcode appears to be phone number in actual files.
     */
    protected function parseHoldsLine(string $line): ?array
    {
        $parts = explode('|', $line);

        if (count($parts) < 7) {
            return null;
        }

        return [
            'notification_type' => 'holds',
            'title' => trim($parts[0]),
            'pickup_date' => $this->parseDate($parts[1]),
            'item_id' => trim($parts[2]), // SysHoldRequestID
            'patron_barcode' => trim($parts[6]), // PBarcode
            'branch_id' => (int) trim($parts[4]),
            'expiration_date' => $this->parseDate($parts[5]),
            'item_record_id' => null,
            'renewals' => null,
            'bibliographic_record_id' => null,
            'renewal_limit' => null,
            'phone_number' => null,
        ];
    }

    /**
     * Parse an overdue submission file.
     */
    public function parseOverdueFile(string $filePath): array
    {
        $submissions = [];
        $lines = $this->readFileWithEncoding($filePath);

        foreach ($lines as $lineNumber => $line) {
            try {
                $data = $this->parseOverdueLine($line);
                if ($data) {
                    $submissions[] = $data;
                }
            } catch (Exception $e) {
                Log::warning("Failed to parse line {$lineNumber} in overdue file: {$e->getMessage()}", [
                    'file' => $filePath,
                    'line' => $line,
                ]);
            }
        }

        return $submissions;
    }

    /**
     * Parse a single overdue line.
     * Format may be similar to holds or different - adjust as needed.
     */
    protected function parseOverdueLine(string $line): ?array
    {
        $parts = explode('|', $line);

        if (count($parts) < 13) {
            return null;
        }

        // Based on overdue.sql:
        // PatronID|ItemBarcode|Title|DueDate|ItemRecordID|Dummy1|Dummy2|Dummy3|Dummy4|Renewals|BibRecordID|RenewalLimit|PatronBarcode
        return [
            'notification_type' => 'overdue',
            'patron_barcode' => trim($parts[12]), // PatronBarcode
            'item_id' => trim($parts[1]), // ItemBarcode
            'title' => trim($parts[2]),
            'expiration_date' => $this->parseDate($parts[3]), // DueDate
            'item_record_id' => (int) trim($parts[4]),
            'renewals' => (int) trim($parts[9]),
            'bibliographic_record_id' => (int) trim($parts[10]),
            'renewal_limit' => (int) trim($parts[11]),
            'branch_id' => null,
            'pickup_date' => null,
            'phone_number' => null,
        ];
    }

    /**
     * Parse a renewal submission file.
     */
    public function parseRenewFile(string $filePath): array
    {
        $submissions = [];
        $lines = $this->readFileWithEncoding($filePath);

        foreach ($lines as $lineNumber => $line) {
            try {
                $data = $this->parseRenewLine($line);
                if ($data) {
                    $submissions[] = $data;
                }
            } catch (Exception $e) {
                Log::warning("Failed to parse line {$lineNumber} in renew file: {$e->getMessage()}", [
                    'file' => $filePath,
                    'line' => $line,
                ]);
            }
        }

        return $submissions;
    }

    /**
     * Parse a single renewal line.
     * Based on renew.sql:
     * PatronID|ItemBarcode|Title|DueDate|ItemRecordID|Dummy1|Dummy2|Dummy3|Dummy4|Renewals|BibRecordID|RenewalLimit|PatronBarcode
     * Note: PatronBarcode appears to be phone number in actual files.
     */
    protected function parseRenewLine(string $line): ?array
    {
        $parts = explode('|', $line);

        if (count($parts) < 13) {
            return null;
        }

        return [
            'notification_type' => 'renew',
            'patron_barcode' => trim($parts[12]), // PatronBarcode
            'item_id' => trim($parts[1]), // ItemBarcode
            'title' => trim($parts[2]),
            'expiration_date' => $this->parseDate($parts[3]), // DueDate
            'item_record_id' => (int) trim($parts[4]),
            'renewals' => (int) trim($parts[9]),
            'bibliographic_record_id' => (int) trim($parts[10]),
            'renewal_limit' => (int) trim($parts[11]),
            'branch_id' => null,
            'pickup_date' => null,
            'phone_number' => null,
        ];
    }

    /**
     * Parse patron list files (voice/text).
     * Based on voice_patrons.sql:
     * PhoneVoice1|Barcode (phone first, then barcode).
     *
     * Returns an array keyed by patron barcode where each value is a
     * normalized 10-digit phone number. Any rows that cannot be
     * normalized to 10 digits (even after stripping punctuation and a
     * leading 1) are skipped.
     */
    public function parsePatronList(string $filePath): array
    {
        $patrons = [];
        $lines = $this->readFileWithEncoding($filePath);

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $phone = $this->formatPhoneNumber($parts[0]);
                if ($phone === null) {
                    // Skip entries that cannot be normalized to a valid
                    // 10-digit phone number.
                    continue;
                }

                $barcode = trim($parts[1]);
                if ($barcode === '') {
                    continue;
                }

                $patrons[$barcode] = $phone;
            }
        }

        return $patrons;
    }

    /**
     * Parse PhoneNotices.csv file (Polaris export).
     */
    public function parsePhoneNoticesCSV(string $filePath): array
    {
        $submissions = [];

        // Read file with proper UTF-8 encoding
        $content = $this->readFileContentWithEncoding($filePath);
        if ($content === false) {
            return [];
        }

        // Parse CSV from string
        $lines = str_getcsv($content, "\n");
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;

            try {
                $data = str_getcsv($line);
                if ($data === false || empty($data)) {
                    continue;
                }

                $parsed = $this->parsePhoneNoticesLine($data);
                if ($parsed) {
                    $submissions[] = $parsed;
                }
            } catch (Exception $e) {
                Log::warning("Failed to parse PhoneNotices.csv line {$lineNumber}", [
                    'error' => $e->getMessage(),
                    'line' => $line,
                ]);
            }
        }

        return $submissions;
    }

    /**
     * Parse a single PhoneNotices.csv line - FULL FIELDS for verification tracking.
     *
     * CSV Fields (1-based):
     * 1: Delivery type (V=Voice, T=Text)
     * 2: Language
     * 5: Patron barcode
     * 7: First name
     * 8: Last name
     * 9: Phone number
     * 10: Email
     * 11: Library code
     * 12: Library name
     * 13: Item barcode
     * 14: Date
     * 15: Title
     * 16: Organization code
     * 17: Language code
     * 20: Patron ID
     * 21: Item Record ID
     * 22: Bibliographic Record ID
     */
    protected function parsePhoneNoticesLine(array $data): ?array
    {
        // Need at least 22 fields
        if (count($data) < 22) {
            return null;
        }

        $rawDelivery = strtoupper(trim($data[0]));
        $deliveryType = $rawDelivery === 'V' ? 'voice' : ($rawDelivery === 'T' ? 'text' : null);

        return [
            // Map delivery type (normalized + raw)
            'delivery_type' => $deliveryType,

            // Core CSV fields
            'language' => !empty($data[1]) ? trim($data[1]) : null, // Field 2
            'patron_barcode' => trim($data[4]), // Field 5
            'first_name' => !empty($data[6]) ? trim($data[6]) : null, // Field 7
            'last_name' => !empty($data[7]) ? trim($data[7]) : null, // Field 8
            'phone_number' => $this->formatPhoneNumber($data[8]), // Field 9
            'email' => !empty($data[9]) ? trim($data[9]) : null, // Field 10
            'library_code' => !empty($data[10]) ? trim($data[10]) : null, // Field 11
            'library_name' => !empty($data[11]) ? trim($data[11]) : null, // Field 12
            'item_barcode' => !empty($data[12]) ? trim($data[12]) : null, // Field 13
            'notice_date' => $this->parseDate($data[13]), // Field 14
            'title' => !empty($data[14]) ? trim($data[14]) : null, // Field 15
            'organization_code' => !empty($data[15]) ? trim($data[15]) : null, // Field 16
            'language_code' => !empty($data[16]) ? trim($data[16]) : null, // Field 17

            // Enrichment fields used by NotificationImportService
            'notification_type_id' => !empty($data[17]) ? (int) trim($data[17]) : null, // Field 18
            'delivery_option_id' => !empty($data[18]) ? (int) trim($data[18]) : null, // Field 19
            'patron_id' => !empty($data[19]) ? (int) trim($data[19]) : null, // Field 20
            'item_record_id' => !empty($data[20]) ? (int) trim($data[20]) : null, // Field 21
            'sys_hold_request_id' => isset($data[21]) && $data[21] !== '' ? (int) trim($data[21]) : null, // Field 22
            'account_balance' => isset($data[24]) && $data[24] !== '' ? (float) trim($data[24]) : null, // Field 25
            'bib_record_id' => !empty($data[21]) ? (int) trim($data[21]) : null, // Field 22 (kept for backwards compatibility)
        ];
    }

    /**
     * Extract submission timestamp from filename.
     * Formats supported:
     *   - holds_submitted_2025-05-15_14-30-45.txt (dashes in date and time)
     *   - holds_submitted_2025-05-15_143045.txt (dashes in date, no dashes in time)
     *   - holds_submitted_2025-05-15_143045_.txt (trailing underscore)
     *   - holds_submitted_20250515_143045.txt (no dashes in date or time)
     *   - holds_submitted_20250515_143045_.txt (trailing underscore).
     */
    public function extractTimestampFromFilename(string $filename): Carbon
    {
        // Format with dashes in date and time: _YYYY-MM-DD_HH-MM-SS.txt
        if (preg_match('/_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})_?\.txt$/i', $filename, $matches)) {
            return Carbon::createFromFormat('Y-m-d H:i:s', "{$matches[1]} {$matches[2]}:{$matches[3]}:{$matches[4]}");
        }

        // Format with dashes in date only: _YYYY-MM-DD_HHMMSS.txt or _YYYY-MM-DD_HHMMSS_.txt
        if (preg_match('/_(\d{4}-\d{2}-\d{2})_(\d{6})_?\.txt$/i', $filename, $matches)) {
            $time = substr($matches[2], 0, 2) . ':' . substr($matches[2], 2, 2) . ':' . substr($matches[2], 4, 2);

            return Carbon::createFromFormat('Y-m-d H:i:s', "{$matches[1]} {$time}");
        }

        // Format with no dashes: _YYYYMMDD_HHMMSS.txt or _YYYYMMDD_HHMMSS_.txt
        if (preg_match('/_(\d{4})(\d{2})(\d{2})_(\d{6})_?\.txt$/i', $filename, $matches)) {
            $date = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
            $time = substr($matches[4], 0, 2) . ':' . substr($matches[4], 2, 2) . ':' . substr($matches[4], 4, 2);

            return Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}");
        }

        // Fallback to file modification time
        return now();
    }

    /**
     * Determine notification type from filename.
     */
    public function getNotificationTypeFromFilename(string $filename): ?string
    {
        if (str_contains($filename, 'holds_submitted')) {
            return 'holds';
        }
        if (str_contains($filename, 'overdue_submitted')) {
            return 'overdue';
        }
        if (str_contains($filename, 'renew_submitted')) {
            return 'renew';
        }

        return null;
    }

    /**
     * Parse date string.
     */
    protected function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Format phone number consistently using the shared normalizer.
     *
     * This ensures all ingested phone numbers converge on the same
     * 10-digit canonical form used throughout the package.
     *
     * @return string|null Normalized 10-digit phone, or null if invalid.
     */
    protected function formatPhoneNumber(string $phone): ?string
    {
        return NotificationImportService::normalizePhone($phone);
    }

    /**
     * Read file with proper encoding detection and conversion to UTF-8.
     * Handles Windows-1252, ISO-8859-1, and UTF-8 encoded files.
     * Returns an array of lines without newlines or empty lines.
     */
    protected function readFileWithEncoding(string $filePath): array
    {
        $content = $this->readFileContentWithEncoding($filePath);
        if ($content === false) {
            return [];
        }

        // Split into lines, remove empty lines
        return array_filter(
            array_map('trim', explode("\n", $content)),
            fn ($line) => $line !== ''
        );
    }

    /**
     * Read file content with proper encoding detection and conversion to UTF-8.
     * Handles BOM (Byte Order Mark) and common encodings.
     */
    protected function readFileContentWithEncoding(string $filePath): string|false
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Remove BOM if present (UTF-8 BOM: EF BB BF)
        $bom = pack('H*', 'EFBBBF');
        if (str_starts_with($content, $bom)) {
            $content = substr($content, 3);
        }

        // Detect encoding and convert to UTF-8
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            Log::debug("Converted file encoding from {$encoding} to UTF-8", ['file' => basename($filePath)]);
        }

        // Ensure valid UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            // Last resort: try to clean invalid UTF-8 sequences
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        return $content;
    }
}
