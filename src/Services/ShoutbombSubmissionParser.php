<?php

namespace Dcplibrary\Notifications\Services;

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
     * Format: Title|PickupDate|PatronBarcode|ItemID|BranchID|ExpirationDate|PhoneNumber
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
            'patron_barcode' => trim($parts[2]),
            'item_id' => trim($parts[3]),
            'branch_id' => (int) trim($parts[4]),
            'expiration_date' => $this->parseDate($parts[5]),
            'phone_number' => $this->formatPhoneNumber($parts[6]),
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

        if (count($parts) < 3) {
            return null;
        }

        // Format: ?|ItemBarcode|Title|DueDate|PatronBarcode|||||BranchID|?|?|PhoneNumber
        return [
            'notification_type' => 'overdue',
            'title' => trim($parts[2]),
            'item_id' => trim($parts[1]),
            'patron_barcode' => trim($parts[4]),
            'branch_id' => !empty($parts[9]) ? (int) trim($parts[9]) : null,
            'expiration_date' => $this->parseDate($parts[3]),
            'phone_number' => $this->formatPhoneNumber($parts[12]),
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
     * Format: ?|ItemBarcode|Title|DueDate|PatronBarcode|||||BranchID|?|?|PhoneNumber
     */
    protected function parseRenewLine(string $line): ?array
    {
        $parts = explode('|', $line);

        if (count($parts) < 13) {
            return null;
        }

        return [
            'notification_type' => 'renew',
            'title' => trim($parts[2]),
            'item_id' => trim($parts[1]),
            'patron_barcode' => trim($parts[4]),
            'branch_id' => !empty($parts[9]) ? (int) trim($parts[9]) : null,
            'expiration_date' => $this->parseDate($parts[3]),
            'phone_number' => $this->formatPhoneNumber($parts[12]),
        ];
    }

    /**
     * Parse patron list files (voice/text).
     * Format: PatronBarcode|PhoneNumber
     */
    public function parsePatronList(string $filePath): array
    {
        $patrons = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $barcode = trim($parts[0]);
                $phone = $this->formatPhoneNumber($parts[1]);
                $patrons[$barcode] = $phone;
            }
        }

        return $patrons;
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
