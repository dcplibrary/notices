<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ShoutbombSubmissionParser
{
    /**
     * Parse a holds submission file.
     */
    public function parseHoldsFile(string $filePath): array
    {
        $submissions = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $lineNumber => $line) {
            try {
                $data = $this->parseHoldsLine($line);
                if ($data) {
                    $submissions[] = $data;
                }
            } catch (\Exception $e) {
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
     * Note: PBarcode appears to be phone number in actual files
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
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $lineNumber => $line) {
            try {
                $data = $this->parseOverdueLine($line);
                if ($data) {
                    $submissions[] = $data;
                }
            } catch (\Exception $e) {
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
     * Format may be similar to holds or different - adjust as needed
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
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $lineNumber => $line) {
            try {
                $data = $this->parseRenewLine($line);
                if ($data) {
                    $submissions[] = $data;
                }
            } catch (\Exception $e) {
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
     * Note: PatronBarcode appears to be phone number in actual files
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
     * PhoneVoice1|Barcode (phone first, then barcode)
     */
    public function parsePatronList(string $filePath): array
    {
        $patrons = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $phone = $this->formatPhoneNumber($parts[0]);
                $barcode = trim($parts[1]);
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

        if (($handle = fopen($filePath, 'r')) === false) {
            return [];
        }

        $lineNumber = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;

            try {
                $parsed = $this->parsePhoneNoticesLine($data);
                if ($parsed) {
                    $submissions[] = $parsed;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to parse PhoneNotices.csv line {$lineNumber}", [
                    'error' => $e->getMessage(),
                    'line' => implode(',', $data),
                ]);
            }
        }

        fclose($handle);
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

        $deliveryType = strtoupper(trim($data[0]));

        return [
            // Map delivery type
            'delivery_type' => $deliveryType === 'V' ? 'voice' : ($deliveryType === 'T' ? 'text' : null),

            // All CSV fields
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
            'patron_id' => !empty($data[19]) ? (int) trim($data[19]) : null, // Field 20
            'item_record_id' => !empty($data[20]) ? (int) trim($data[20]) : null, // Field 21
            'bib_record_id' => !empty($data[21]) ? (int) trim($data[21]) : null, // Field 22
        ];
    }

    /**
     * Extract submission timestamp from filename.
     * Format: holds_submitted_2025-05-15_14-30-45.txt
     */
    public function extractTimestampFromFilename(string $filename): Carbon
    {
        if (preg_match('/_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.txt$/', $filename, $matches)) {
            return Carbon::createFromFormat('Y-m-d_H-i-s', $matches[1]);
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
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format phone number consistently.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-digits
        $phone = preg_replace('/\D/', '', $phone);

        // Return as-is (you can add more formatting if needed)
        return $phone;
    }
}
