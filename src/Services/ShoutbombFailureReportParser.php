<?php

namespace Dcplibrary\Notices\Services;

use Illuminate\Support\Facades\Log;

class ShoutbombFailureReportParser
{
    protected array $config;

    public function __construct(array $parsingConfig = [])
    {
        // Use provided parsing config or fall back to notices integrations config
        $this->config = $parsingConfig ?: config('notices.integrations.shoutbomb_reports.parsing', []);
    }

    /**
     * Parse a Shoutbomb failure report email and extract all failures
     * Returns an array of failure records (one email can have multiple failures)
     */
    public function parse(array $message, ?string $bodyContent = null): array
    {
        if (!$bodyContent) {
            $bodyContent = $message['body']['content'] ?? '';
        }

        // Strip HTML tags if content is HTML
        if (($message['body']['contentType'] ?? '') === 'html') {
            $bodyContent = strip_tags($bodyContent);
        }

        $failures = [];

        // Check if this is a Shoutbomb report
        if (!$this->isShoutbombReport($message, $bodyContent)) {
            Log::warning('Email does not appear to be a Shoutbomb report', [
                'subject' => $message['subject'] ?? 'unknown',
            ]);
            return [];
        }

        // Extract common metadata
        $metadata = [
            'outlook_message_id' => $message['id'] ?? null,
            'subject' => $message['subject'] ?? null,
            'received_at' => $message['receivedDateTime'] ?? null,
            'from_address' => $message['from']['emailAddress']['address'] ?? null,
            'raw_content' => config('notices.integrations.shoutbomb_reports.storage.store_raw_content', false)
                ? $bodyContent
                : null,
        ];

        // Detect report type from subject
        $subject = $message['subject'] ?? '';

        if (stripos($subject, 'Voice notices that were not delivered') !== false) {
            // Voice failure report format
            $failures = $this->parseVoiceFailures($bodyContent, $metadata);
        } elseif (stripos($subject, 'Shoutbomb Rpt') !== false) {
            // Monthly report format - parse multiple sections

            // Invalid/removed patron barcodes (redacted format)
            $invalidBarcodes = $this->parseInvalidBarcodesSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidBarcodes);

            // Daily sections may also be present in monthly reports
            $optedOutFailures = $this->parseOptedOutSection($bodyContent, $metadata);
            $failures = array_merge($failures, $optedOutFailures);

            $invalidFailures = $this->parseInvalidSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidFailures);
        } else {
            // Daily SMS failure report format
            // Parse opted-out patrons
            $optedOutFailures = $this->parseOptedOutSection($bodyContent, $metadata);
            $failures = array_merge($failures, $optedOutFailures);

            // Parse invalid phone numbers
            $invalidFailures = $this->parseInvalidSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidFailures);
        }

        Log::info("Parsed Shoutbomb report: found " . count($failures) . " failures");

        return $failures;
    }

    /**
     * Check if this is a Shoutbomb report email
     */
    protected function isShoutbombReport(array $message, string $content): bool
    {
        $subject = $message['subject'] ?? '';
        $from = $message['from']['emailAddress']['address'] ?? '';

        // Check subject contains expected keywords
        if (stripos($subject, 'Invalid patron phone number') !== false ||
            stripos($subject, 'Voice notices that were not delivered') !== false ||
            stripos($subject, 'Shoutbomb Rpt') !== false) {
            return true;
        }

        // Check from Shoutbomb or DCPL Notifications
        if (stripos($from, 'shoutbomb') !== false ||
            stripos($from, 'DCPL Notifications') !== false) {
            return true;
        }

        // Check body contains expected sections
        if (stripos($content, 'opted-out from SMS or MMS') !== false ||
            stripos($content, 'seem to be invalid') !== false ||
            stripos($content, 'Daviess County Public Library') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Parse the "OPTED-OUT" section
     */
    protected function parseOptedOutSection(string $content, array $metadata): array
    {
        $failures = [];

        // Find the opted-out section
        if (preg_match('/OPTED-OUT from SMS or MMS.*?\n(.*?)(?=\n\s*Hello|\n\s*These patron|$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $failures = $this->parseFailureLines($section, $metadata, 'opted-out');
        }

        return $failures;
    }

    /**
     * Parse the "INVALID" section
     */
    protected function parseInvalidSection(string $content, array $metadata): array
    {
        $failures = [];

        // Find the invalid section
        if (preg_match('/seem to be invalid.*?\n(.*?)(?=\n\s*Hello|\n\s*$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $failures = $this->parseFailureLines($section, $metadata, 'invalid');
        }

        return $failures;
    }

    /**
     * Parse individual failure lines in format:
     * phone :: barcode :: patron_id :: branch_id :: notice_type
     * Example: 5555551234 :: 12345678901234 :: 567890 :: 3 :: SMS
     * Note: Some lines may have fewer parts indicating deleted/unavailable accounts
     */
    protected function parseFailureLines(string $section, array $metadata, string $failureType): array
    {
        $failures = [];
        $lines = explode("\n", $section);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Parse the line format: phone :: barcode :: patron_id :: branch_id :: notice_type
            $parts = array_map('trim', explode('::', $line));

            // Need at least phone number and one other field
            if (count($parts) >= 2) {
                $patronBarcode = $parts[1] ?? null;
                $patronId = null;
                $branchId = null;
                $noticeType = null;
                $accountStatus = 'active';

                // Handle "No associated barcode" case - account likely deleted
                if (stripos($patronBarcode, 'No associated barcode') !== false) {
                    $patronBarcode = null;
                    $accountStatus = 'deleted';
                } elseif (count($parts) == 3 && is_numeric($parts[2]) && $parts[2] <= 10) {
                    // Format: phone :: barcode :: branch_id (missing patron_id and notice_type)
                    // Example: 5555551234 :: 12345678901234 :: 3
                    // Indicates deleted/unavailable account
                    $branchId = (int)$parts[2];
                    $accountStatus = 'unavailable';
                    $noticeType = null; // Unknown
                } elseif (count($parts) >= 3) {
                    // Normal parsing: has patron_id
                    $patronId = $parts[2] ?? null;

                    if (count($parts) >= 4) {
                        $branchId = isset($parts[3]) && is_numeric($parts[3]) ? (int)$parts[3] : null;
                    }
                    if (count($parts) >= 5) {
                        $noticeType = $parts[4] ?? null;
                    }
                }

                $failure = array_merge($metadata, [
                    'patron_phone' => $parts[0] ?? null,
                    'patron_id' => $patronId,
                    'patron_barcode' => $patronBarcode,
                    'attempt_count' => $branchId,
                    'notice_type' => $noticeType,
                    'failure_reason' => $accountStatus === 'active'
                        ? $this->getFailureReason($failureType)
                        : $this->getFailureReason($failureType) . ' (account ' . $accountStatus . ')',
                    'failure_type' => $failureType,
                    'account_status' => $accountStatus,
                ]);

                $failures[] = $failure;
            } else {
                Log::debug("Could not parse Shoutbomb line: {$line}");
            }
        }

        return $failures;
    }

    /**
     * Parse Voice failure report (different format)
     * Format: phone | patron_barcode | library_name | patron_name | [notice_description]
     * Example: 5551234567 | 12345678901234 | Sample Library | DOE, JOHN | Overdue item message
     * Note: notice_description may be on the same line (after last pipe) or on the next line
     */
    protected function parseVoiceFailures(string $content, array $metadata): array
    {
        $failures = [];
        $lines = explode("\n", $content);
        $pendingFailure = null;

        foreach ($lines as $index => $line) {
            $line = trim($line);

            // Skip empty lines and header lines
            if (empty($line) || stripos($line, 'Hello') !== false ||
                stripos($line, 'Date:') !== false || stripos($line, 'Subject:') !== false ||
                stripos($line, 'From:') !== false || stripos($line, 'To:') !== false) {
                continue;
            }

            // Check if line contains pipe-delimited data
            if (strpos($line, '|') !== false) {
                // If we have a pending failure, save it before processing new line
                if ($pendingFailure !== null) {
                    $failures[] = $pendingFailure;
                    $pendingFailure = null;
                }

                // Parse the line format: phone | patron_barcode | library | patron_name | [notice_description]
                $parts = array_map('trim', explode('|', $line));

                if (count($parts) >= 4) {
                    $failure = array_merge($metadata, [
                        'patron_phone' => $parts[0] ?? null,
                        'patron_id' => null,
                        'patron_barcode' => $parts[1] ?? null,
                        'patron_name' => $parts[3] ?? null,
                        'notice_description' => !empty($parts[4]) ? $parts[4] : null,
                        'attempt_count' => null,
                        'notice_type' => 'Voice',
                        'failure_reason' => 'Voice notice not delivered',
                        'failure_type' => 'voice-not-delivered',
                    ]);

                    // If notice_description is empty, mark as pending to check next line
                    if (empty($failure['notice_description'])) {
                        $pendingFailure = $failure;
                    } else {
                        $failures[] = $failure;
                    }
                }
            } elseif ($pendingFailure !== null && !empty($line)) {
                // This line might be the notice description for the previous record
                // Only use it if it looks like a notice type (not an email header)
                $pendingFailure['notice_description'] = $line;
                $failures[] = $pendingFailure;
                $pendingFailure = null;
            }
        }

        // Add any remaining pending failure
        if ($pendingFailure !== null) {
            $failures[] = $pendingFailure;
        }

        return $failures;
    }

    /**
     * Parse invalid/removed patron barcodes section from monthly reports
     * Format: X's followed by partial barcode (digits or alphanumeric)
     * Examples: XXXXXXXXXX2144, XXXX2018, XX1719, XXXXX337E, XXXXXXDu3k
     */
    protected function parseInvalidBarcodesSection(string $content, array $metadata): array
    {
        $failures = [];

        // Find the invalid barcodes section
        // Look for: "patron barcodes found to no longer be valid and have been removed"
        if (preg_match('/patron barcodes.*?no longer be valid.*?removed.*?\n(.*?)(?=\n\s*\.{20,}|\n\s*The following are patrons|$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $lines = explode("\n", $section);

            foreach ($lines as $line) {
                $line = trim($line);

                // Skip empty lines and separators
                if (empty($line) || preg_match('/^[*.=-]+$/', $line)) {
                    continue;
                }

                // Match redacted barcode pattern: X+ followed by 2+ alphanumeric characters
                // Handles: XXXXXXXXXX2144, XXXX2018, XX1719, XXXXX337E, XXXXXXDu3k, etc.
                if (preg_match('/^(X+[A-Z0-9]{2,})$/i', $line, $matches)) {
                    $fullRedactedBarcode = $matches[1]; // Store full string with X's

                    $failure = array_merge($metadata, [
                        'patron_phone' => null,
                        'patron_id' => null,
                        'patron_barcode' => $fullRedactedBarcode, // Store full redacted barcode with X's
                        'barcode_partial' => true, // Flag as partial for fuzzy matching
                        'patron_name' => null,
                        'attempt_count' => null,
                        'notice_type' => null,
                        'notice_description' => null,
                        'failure_reason' => 'Patron barcode removed from system - no longer valid',
                        'failure_type' => 'invalid-barcode-removed',
                        'account_status' => 'deleted',
                    ]);

                    $failures[] = $failure;

                    Log::debug("Parsed invalid barcode (redacted): {$fullRedactedBarcode}");
                }
            }
        }

        return $failures;
    }

    /**
     * Get human-readable failure reason
     */
    protected function getFailureReason(string $failureType): string
    {
        return match($failureType) {
            'opted-out' => 'Patron opted-out from SMS/MMS messages',
            'invalid' => 'Invalid phone number',
            'voice-not-delivered' => 'Voice notice not delivered',
            'invalid-barcode-removed' => 'Patron barcode removed from system - no longer valid',
            default => 'Unknown failure',
        };
    }

    /**
     * Validate a single parsed failure
     */
    public function validate(array $parsedData): bool
    {
        // For partial barcodes, we only need the last 4 digits
        if (!empty($parsedData['barcode_partial']) && !empty($parsedData['patron_barcode'])) {
            return true;
        }

        // For regular failures, we need patron phone or patron ID
        if (empty($parsedData['patron_phone']) && empty($parsedData['patron_id'])) {
            Log::warning('Failed to parse critical data from failure line');
            return false;
        }

        return true;
    }

    /**
     * Parse monthly statistics from "Shoutbomb Rpt" emails
     * Returns array of monthly statistics or null if not a monthly report
     */
    public function parseMonthlyStats(array $message, ?string $bodyContent = null): ?array
    {
        $subject = $message['subject'] ?? '';

        // Only parse if this is a monthly report
        if (stripos($subject, 'Shoutbomb Rpt') === false) {
            return null;
        }

        if (!$bodyContent) {
            $bodyContent = $message['body']['content'] ?? '';
        }

        // Strip HTML tags if content is HTML
        if (($message['body']['contentType'] ?? '') === 'html') {
            $bodyContent = strip_tags($bodyContent);
        }

        $stats = [
            'outlook_message_id' => $message['id'] ?? null,
            'subject' => $subject,
            'received_at' => $message['receivedDateTime'] ?? null,
        ];

        // Extract report month from subject (e.g., "Shoutbomb Rpt October 2025")
        if (preg_match('/Shoutbomb Rpt\s+(\w+)\s+(\d{4})/i', $subject, $matches)) {
            $monthName = $matches[1];
            $year = $matches[2];
            try {
                $stats['report_month'] = date('Y-m-01', strtotime("$monthName $year"));
            } catch (\Exception $e) {
                Log::warning("Could not parse report month from subject: {$subject}");
            }
        }

        // Extract branch name
        if (preg_match('/Branch::\s*([^\n]+)/i', $bodyContent, $matches)) {
            $stats['branch_name'] = trim($matches[1]);
        }

        // Extract hold notices
        if (preg_match('/Hold text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['hold_text_notices'] = (int)$m[1];
        }
        if (preg_match('/Hold text reminders notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['hold_text_reminders'] = (int)$m[1];
        }
        if (preg_match('/Hold voice notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['hold_voice_notices'] = (int)$m[1];
        }
        if (preg_match('/Hold voice reminder notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['hold_voice_reminders'] = (int)$m[1];
        }

        // Extract overdue notices
        if (preg_match('/Overdue text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_text_notices'] = (int)$m[1];
        }
        if (preg_match('/Overdue items eligible for renewal, text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_text_eligible_renewal'] = (int)$m[1];
        }
        if (preg_match('/Overdue items ineligible for renewal, text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_text_ineligible_renewal'] = (int)$m[1];
        }
        if (preg_match('/Overdue \(text\) items renewed successfully by patrons for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_text_renewed_successfully'] = (int)$m[1];
        }
        if (preg_match('/Overdue \(text\) items unsuccessfully renewed by patrons for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_text_renewed_unsuccessfully'] = (int)$m[1];
        }
        if (preg_match('/Overdue voice notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_voice_notices'] = (int)$m[1];
        }
        if (preg_match('/Overdue items eligible for renewal, voice notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_voice_eligible_renewal'] = (int)$m[1];
        }
        if (preg_match('/Overdue items ineligible for renewal, voice notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['overdue_voice_ineligible_renewal'] = (int)$m[1];
        }

        // Extract renewal notices
        if (preg_match('/Renewal text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_text_notices'] = (int)$m[1];
        }
        if (preg_match('/Items eligible for renewal text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_text_eligible'] = (int)$m[1];
        }
        if (preg_match('/Items ineligible for renewal text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_text_ineligible'] = (int)$m[1];
        }
        if (preg_match('/Items \(text\) unsuccessfully renewed by patrons for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_text_unsuccessfully'] = (int)$m[1];
        }
        if (preg_match('/Renewal reminder text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_text_reminders'] = (int)$m[1];
        }
        if (preg_match('/Items eligible for renewal reminder text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_text_reminder_eligible'] = (int)$m[1];
        }
        if (preg_match('/Items ineligible for renewal reminder text notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_text_reminder_ineligible'] = (int)$m[1];
        }

        if (preg_match('/Renewal voice notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_voice_notices'] = (int)$m[1];
        }
        if (preg_match('/Voice items eligible for renewal notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_voice_eligible'] = (int)$m[1];
        }
        if (preg_match('/Voice items ineligible for renewal notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_voice_ineligible'] = (int)$m[1];
        }
        if (preg_match('/Renewal voice reminder notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_voice_reminders'] = (int)$m[1];
        }
        if (preg_match('/Voice items eligible for renewal reminder notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_voice_reminder_eligible'] = (int)$m[1];
        }
        if (preg_match('/Voice items ineligible for renewal reminder notices sent for the month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['renewal_voice_reminder_ineligible'] = (int)$m[1];
        }

        // Extract registration statistics
        if (preg_match('/Total registered users\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['total_registered_users'] = (int)$m[1];
        }
        if (preg_match('/Total registered barcodes\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['total_registered_barcodes'] = (int)$m[1];
        }
        if (preg_match('/Total registered users for text notices is\s*(\d+)/i', $bodyContent, $m)) {
            $stats['total_registered_text'] = (int)$m[1];
        }
        if (preg_match('/Total registered users for voice notices is\s*(\d+)/i', $bodyContent, $m)) {
            $stats['total_registered_voice'] = (int)$m[1];
        }
        if (preg_match('/Registered users the last month\s*=\s*(\d+)/i', $bodyContent, $m)) {
            $stats['new_registrations_month'] = (int)$m[1];
        }
        if (preg_match('/signed up\s+(\d+)\s+patron\(s\) for voice notices the last month/i', $bodyContent, $m)) {
            $stats['new_voice_signups'] = (int)$m[1];
        }
        if (preg_match('/signed up\s+(\d+)\s+patron\(s\) for text notices the last month/i', $bodyContent, $m)) {
            $stats['new_text_signups'] = (int)$m[1];
        }

        // Extract call statistics
        if (preg_match('/Average daily call volume is::\s*(\d+)/i', $bodyContent, $m)) {
            $stats['average_daily_calls'] = (int)$m[1];
        }

        // Extract keyword usage
        $keywords = [];
        if (preg_match_all('/^(\w+)\s+was used\s+(\d+)\s+times?\./im', $bodyContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $keywords[$match[1]] = (int)$match[2];
            }
        }
        if (!empty($keywords)) {
            $stats['keyword_usage'] = $keywords;
        }

        Log::info("Parsed monthly statistics for " . ($stats['report_month'] ?? 'unknown month'));

        return $stats;
    }
}
