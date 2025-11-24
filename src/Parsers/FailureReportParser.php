<?php

namespace Dcplibrary\Notices\Parsers;

use Illuminate\Support\Facades\Log;

class FailureReportParser
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: config('notices.shoutbomb_reports.parsing', []);
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
            'raw_content' => config('notices.shoutbomb_reports.storage.store_raw_content', false)
                ? $bodyContent
                : null,
        ];

        // Detect report type from subject
        $subject = $message['subject'] ?? '';

        if (stripos($subject, 'Voice notices that were not delivered') !== false) {
            $failures = $this->parseVoiceFailures($bodyContent, $metadata);
        } elseif (stripos($subject, 'Shoutbomb Rpt') !== false) {
            $invalidBarcodes = $this->parseInvalidBarcodesSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidBarcodes);

            $optedOutFailures = $this->parseOptedOutSection($bodyContent, $metadata);
            $failures = array_merge($failures, $optedOutFailures);

            $invalidFailures = $this->parseInvalidSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidFailures);
        } else {
            $optedOutFailures = $this->parseOptedOutSection($bodyContent, $metadata);
            $failures = array_merge($failures, $optedOutFailures);

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

        if (stripos($subject, 'Invalid patron phone number') !== false ||
            stripos($subject, 'Voice notices that were not delivered') !== false ||
            stripos($subject, 'Shoutbomb Rpt') !== false) {
            return true;
        }

        if (stripos($from, 'shoutbomb') !== false ||
            stripos($from, 'DCPL Notifications') !== false) {
            return true;
        }

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

        if (preg_match('/seem to be invalid.*?\n(.*?)(?=\n\s*Hello|\n\s*$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $failures = $this->parseFailureLines($section, $metadata, 'invalid');
        }

        return $failures;
    }

    /**
     * Parse individual failure lines
     */
    protected function parseFailureLines(string $section, array $metadata, string $failureType): array
    {
        $failures = [];
        $lines = explode("\n", $section);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            $parts = array_map('trim', explode('::', $line));

            if (count($parts) >= 2) {
                $patronBarcode = $parts[1] ?? null;
                $patronId = null;
                $branchId = null;
                $noticeType = null;
                $accountStatus = 'active';

                if (stripos($patronBarcode, 'No associated barcode') !== false) {
                    $patronBarcode = null;
                    $accountStatus = 'deleted';
                } elseif (count($parts) == 3 && is_numeric($parts[2]) && $parts[2] <= 10) {
                    $branchId = (int)$parts[2];
                    $accountStatus = 'unavailable';
                } elseif (count($parts) >= 3) {
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
            }
        }

        return $failures;
    }

    /**
     * Parse Voice failure report
     */
    protected function parseVoiceFailures(string $content, array $metadata): array
    {
        $failures = [];
        $lines = explode("\n", $content);
        $pendingFailure = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || stripos($line, 'Hello') !== false ||
                stripos($line, 'Date:') !== false || stripos($line, 'Subject:') !== false ||
                stripos($line, 'From:') !== false || stripos($line, 'To:') !== false) {
                continue;
            }

            if (strpos($line, '|') !== false) {
                if ($pendingFailure !== null) {
                    $failures[] = $pendingFailure;
                    $pendingFailure = null;
                }

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

                    if (empty($failure['notice_description'])) {
                        $pendingFailure = $failure;
                    } else {
                        $failures[] = $failure;
                    }
                }
            } elseif ($pendingFailure !== null && !empty($line)) {
                $pendingFailure['notice_description'] = $line;
                $failures[] = $pendingFailure;
                $pendingFailure = null;
            }
        }

        if ($pendingFailure !== null) {
            $failures[] = $pendingFailure;
        }

        return $failures;
    }

    /**
     * Parse invalid/removed patron barcodes section from monthly reports
     */
    protected function parseInvalidBarcodesSection(string $content, array $metadata): array
    {
        $failures = [];

        if (preg_match('/patron barcodes.*?no longer be valid.*?removed.*?\n(.*?)(?=\n\s*\.{20,}|\n\s*The following are patrons|$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $lines = explode("\n", $section);

            foreach ($lines as $line) {
                $line = trim($line);

                if (empty($line) || preg_match('/^[*.=-]+$/', $line)) {
                    continue;
                }

                if (preg_match('/^(X+[A-Z0-9]{2,})$/i', $line, $matches)) {
                    $fullRedactedBarcode = $matches[1];

                    $failure = array_merge($metadata, [
                        'patron_phone' => null,
                        'patron_id' => null,
                        'patron_barcode' => $fullRedactedBarcode,
                        'barcode_partial' => true,
                        'patron_name' => null,
                        'attempt_count' => null,
                        'notice_type' => null,
                        'notice_description' => null,
                        'failure_reason' => 'Patron barcode removed from system - no longer valid',
                        'failure_type' => 'invalid-barcode-removed',
                        'account_status' => 'deleted',
                    ]);

                    $failures[] = $failure;
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
        if (!empty($parsedData['barcode_partial']) && !empty($parsedData['patron_barcode'])) {
            return true;
        }

        if (empty($parsedData['patron_phone']) && empty($parsedData['patron_id'])) {
            Log::warning('Failed to parse critical data from failure line');
            return false;
        }

        return true;
    }

    /**
     * Parse monthly statistics from "Shoutbomb Rpt" emails
     */
    public function parseMonthlyStats(array $message, ?string $bodyContent = null): ?array
    {
        $subject = $message['subject'] ?? '';

        if (stripos($subject, 'Shoutbomb Rpt') === false) {
            return null;
        }

        if (!$bodyContent) {
            $bodyContent = $message['body']['content'] ?? '';
        }

        if (($message['body']['contentType'] ?? '') === 'html') {
            $bodyContent = strip_tags($bodyContent);
        }

        $stats = [
            'outlook_message_id' => $message['id'] ?? null,
            'subject' => $subject,
            'received_at' => $message['receivedDateTime'] ?? null,
        ];

        // Extract report month
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

        // Extract various statistics
        $this->extractStats($bodyContent, $stats);

        Log::info("Parsed monthly statistics for " . ($stats['report_month'] ?? 'unknown month'));

        return $stats;
    }

    /**
     * Extract statistics from body content
     */
    protected function extractStats(string $bodyContent, array &$stats): void
    {
        $patterns = [
            'hold_text_notices' => '/Hold text notices sent for the month\s*=\s*(\d+)/i',
            'hold_text_reminders' => '/Hold text reminders notices sent for the month\s*=\s*(\d+)/i',
            'hold_voice_notices' => '/Hold voice notices sent for the month\s*=\s*(\d+)/i',
            'hold_voice_reminders' => '/Hold voice reminder notices sent for the month\s*=\s*(\d+)/i',
            'overdue_text_notices' => '/Overdue text notices sent for the month\s*=\s*(\d+)/i',
            'overdue_voice_notices' => '/Overdue voice notices sent for the month\s*=\s*(\d+)/i',
            'total_registered_users' => '/Total registered users\s*=\s*(\d+)/i',
            'total_registered_text' => '/Total registered users for text notices is\s*(\d+)/i',
            'total_registered_voice' => '/Total registered users for voice notices is\s*(\d+)/i',
            'new_registrations' => '/Registered users the last month\s*=\s*(\d+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $bodyContent, $m)) {
                $stats[$key] = (int)$m[1];
            }
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
    }
}
