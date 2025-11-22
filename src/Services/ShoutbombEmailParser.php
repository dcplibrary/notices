<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Illuminate\Support\Facades\Log;

class ShoutbombEmailParser
{
    /**
     * Parse email report and import data
     */
    public function parseAndImport(array $emailReport): array
    {
        $reportType = $emailReport['report_type'];
        $body = $emailReport['body'];
        $reportDate = $emailReport['date'];

        $stats = [
            'report_type' => $reportType,
            'total_parsed' => 0,
            'opted_out' => 0,
            'invalid' => 0,
            'undelivered_voice' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        try {
            if ($reportType === 'email_invalid_optout') {
                $stats = $this->parseOptOutInvalidReport($body, $reportDate, $stats);
            } elseif ($reportType === 'email_undelivered_voice') {
                $stats = $this->parseUndeliveredVoiceReport($body, $reportDate, $stats);
            }

            Log::info('Email report parsed and imported', $stats);
        } catch (\Exception $e) {
            Log::error('Error parsing email report', [
                'type' => $reportType,
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Parse opt-out and invalid phone number report
     *
     * Format: phone_number :: patron_barcode :: patron_id :: status_code :: delivery_type
     * Example: 2705559546 :: 23307012346316 :: 1154 :: 3 :: SMS
     */
    protected function parseOptOutInvalidReport(string $body, string $reportDate, array $stats): array
    {
        // Split body into sections
        $lines = explode("\n", $body);
        $currentSection = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Detect sections
            if (stripos($line, 'OPTED-OUT from SMS') !== false) {
                $currentSection = 'opted_out';
                continue;
            } elseif (stripos($line, 'invalid based on multiple attempts') !== false) {
                $currentSection = 'invalid';
                continue;
            }

            // Skip empty lines and headers
            if (empty($line) || stripos($line, 'Hello') !== false ||
                stripos($line, 'Please verify') !== false) {
                continue;
            }

            // Parse data line (contains ::)
            if (strpos($line, '::') !== false) {
                $parts = array_map('trim', explode('::', $line));

                if (count($parts) === 5) {
                    $phoneNumber = $parts[0];
                    $patronBarcode = $parts[1];
                    $patronId = $parts[2];
                    $statusCode = $parts[3];
                    $deliveryType = $parts[4];

                    // Determine status based on section
                    $status = ($currentSection === 'opted_out') ? 'OptedOut' : 'Invalid';

                    // Create/update delivery record
                    $imported = $this->importDeliveryRecord([
                        'patron_barcode' => $patronBarcode,
                        'patron_id' => (int)$patronId,
                        'phone_number' => $this->normalizePhoneNumber($phoneNumber),
                        'delivery_type' => $deliveryType,
                        'status' => $status,
                        'status_code' => (int)$statusCode,
                        'sent_date' => Carbon::parse($reportDate),
                        'report_type' => ($currentSection === 'opted_out') ? 'email_optout' : 'email_invalid',
                    ]);

                    if ($imported) {
                        $stats['imported']++;
                        if ($currentSection === 'opted_out') {
                            $stats['opted_out']++;
                        } else {
                            $stats['invalid']++;
                        }
                    } else {
                        $stats['skipped']++;
                    }

                    $stats['total_parsed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Parse undelivered voice notices report
     *
     * Format: phone_number | patron_barcode | library_name | patron_name | message_type
     * Example: 2705551797 | 23307015330009 | Daviess County Public Library | GRIFFIE, DALE | Overdue item message
     */
    protected function parseUndeliveredVoiceReport(string $body, string $reportDate, array $stats): array
    {
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and non-data lines
            if (empty($line) || stripos($line, 'of 1') !== false) {
                continue;
            }

            // Parse data line (contains |)
            if (strpos($line, '|') !== false) {
                $parts = array_map('trim', explode('|', $line));

                if (count($parts) >= 5) {
                    $phoneNumber = $parts[0];
                    $patronBarcode = $parts[1];
                    $libraryName = $parts[2];
                    $patronName = $parts[3];
                    $messageType = $parts[4];

                    // Import as failed voice delivery
                    $imported = $this->importDeliveryRecord([
                        'patron_barcode' => $patronBarcode,
                        'phone_number' => $this->normalizePhoneNumber($phoneNumber),
                        'delivery_type' => 'Voice',
                        'message_type' => $messageType,
                        'status' => 'Failed',
                        'sent_date' => Carbon::parse($reportDate),
                        'patron_name' => $patronName,
                        'library_name' => $libraryName,
                        'report_type' => 'email_undelivered_voice',
                        'failure_reason' => 'Undelivered voice notice',
                    ]);

                    if ($imported) {
                        $stats['imported']++;
                        $stats['undelivered_voice']++;
                    } else {
                        $stats['skipped']++;
                    }

                    $stats['total_parsed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Import a delivery record into the database
     */
    protected function importDeliveryRecord(array $data): bool
    {
        try {
            // Check if record already exists (avoid duplicates)
            $exists = ShoutbombDelivery::where('patron_barcode', $data['patron_barcode'])
                ->where('phone_number', $data['phone_number'])
                ->where('sent_date', $data['sent_date'])
                ->where('report_type', $data['report_type'])
                ->exists();

            if ($exists) {
                Log::debug('Skipping duplicate delivery record', [
                    'barcode' => $data['patron_barcode'],
                    'phone' => $data['phone_number'],
                ]);
                return false;
            }

            // Create new record
            ShoutbombDelivery::create($data);

            return true;
        } catch (\Exception $e) {
            Log::error('Error importing delivery record', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Normalize phone number to consistent format
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Format as XXX-XXX-XXXX if 10 digits
        if (strlen($phone) === 10) {
            return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
        }

        return $phone;
    }

    /**
     * Extract report date from email subject
     *
     * Examples:
     * - "Invalid patron phone number Sat, November 8th 2025"
     * - "Voice notices that were not delivered on Fri, November 7th 2025"
     */
    public function extractReportDateFromSubject(string $subject): ?Carbon
    {
        // Try to extract date from subject line
        $pattern = '/(\w+,\s+\w+\s+\d{1,2}(?:st|nd|rd|th)\s+\d{4})/i';
        if (preg_match($pattern, $subject, $matches)) {
            try {
                // Clean up ordinal suffixes (1st, 2nd, 3rd, 4th)
                $dateString = preg_replace('/(\d)(st|nd|rd|th)/', '$1', $matches[1]);
                return Carbon::parse($dateString);
            } catch (\Exception $e) {
                Log::warning('Failed to parse date from subject', [
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
