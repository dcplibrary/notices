<?php

namespace Dcplibrary\Notices\Services\EmailReportParsers;

use Carbon\Carbon;

/**
 * Parse Polaris "Summary Report - Daviess County Public Library" daily emails.
 *
 * Format:
 *   The following email notices were sent out from Daviess County Public Library.
 *   Start time: 12/3/2025 9:01:45 AM
 *   End time: 12/4/2025 8:01:09 AM
 *
 *   X Type notices sent out to:
 *   PATRON NAME    BARCODE    email@address.com
 *   ...
 *
 *   X Type notices failed to:
 *   PATRON NAME    BARCODE    Email Failed    <-- NO EMAIL MEANS POLARIS EXPORT/DELIVERY FAILURE
 *
 * This report is the SOURCE OF TRUTH for email delivery confirmations.
 * It arrives daily at ~8am for the previous day's notifications.
 */
class PolarisSummaryReportParser
{
    /**
     * Parse the Summary Report email.
     *
     * @param string $subject Email subject line
     * @param string $body    Email body text
     *
     * @return array Parsed data structure
     */
    public function parse(string $subject, string $body): array
    {
        $result = [
            'report_type'  => 'polaris_summary_report',
            'report_date'  => $this->extractReportDate($body),
            'start_time'   => null,
            'end_time'     => null,
            'sections'     => [],
            'successes'    => [],
            'failures'     => [],
            'total_sent'   => 0,
            'total_failed' => 0,
        ];

        if ('' === $body) {
            return $result;
        }

        // Extract start and end times
        if (preg_match('/Start time:\s*(.+)$/mi', $body, $matches)) {
            try {
                $result['start_time'] = Carbon::parse($matches[1]);
            } catch (\Throwable $e) {
                // Ignore parse errors
            }
        }

        if (preg_match('/End time:\s*(.+)$/mi', $body, $matches)) {
            try {
                $result['end_time'] = Carbon::parse($matches[1]);
            } catch (\Throwable $e) {
                // Ignore parse errors
            }
        }

        // Parse sections (e.g., "35 Hold E-mail notices sent out to:")
        $sections           = $this->parseSections($body);
        $result['sections'] = $sections;

        // Extract individual notifications
        foreach ($sections as $section) {
            $status = $section['status']; // 'sent' or 'failed'

            foreach ($section['entries'] as $entry) {
                $parsed = [
                    'raw'               => $entry['raw'],
                    'patron_name'       => $entry['patron_name']    ?? null,
                    'patron_barcode'    => $entry['patron_barcode'] ?? null,
                    'email'             => $entry['email']          ?? null,
                    'notification_type' => $section['notification_type'],
                    'delivery_method'   => $section['delivery_method'],
                    'status'            => $status,
                    'failure_reason'    => null,
                    'parsed_at'         => Carbon::now(),
                ];

                // If email is "Email Failed", this is a Polaris delivery system failure
                // The patron likely HAS an email in Polaris, but it wasn't exported/sent
                if ('Email Failed' === ($entry['email'] ?? '')) {
                    $parsed['failure_reason'] = 'Polaris email delivery system failure - email not exported/sent';
                    $parsed['email']          = null; // No email was provided by Polaris
                }

                if ('sent' === $status) {
                    $result['successes'][] = $parsed;
                    ++$result['total_sent'];
                } else {
                    $result['failures'][] = $parsed;
                    ++$result['total_failed'];
                }
            }
        }

        return $result;
    }

    /**
     * Extract the report date from start/end times.
     *
     * CRITICAL: Uses END time, not START time!
     *
     * Reports cover ~24 hour periods from previous day to current day.
     * Example:
     *   Start time: 12/29/2025 9:01 AM
     *   End time: 12/30/2025 8:01 AM
     *
     * The notifications in this report were sent on 12/30, NOT 12/29.
     * Using Start time would cause date mismatches in reconciliation!
     *
     * @param string $body Email body
     *
     * @return null|Carbon The date this report covers (end date)
     */
    protected function extractReportDate(string $body): ?Carbon
    {
        // Use END time as the report date (when the report period closed)
        if (preg_match('/End time:\s*(.+)$/mi', $body, $matches)) {
            try {
                return Carbon::parse($matches[1])->startOfDay();
            } catch (\Throwable $e) {
                // Fall through to try start time
            }
        }

        // Fallback to start time if end time not found
        if (preg_match('/Start time:\s*(.+)$/mi', $body, $matches)) {
            try {
                return Carbon::parse($matches[1])->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Parse all sections in the report.
     *
     * Sections look like:
     *   "35 Hold E-mail notices sent out to:"
     *   "1 Hold E-mail notices failed to:"
     *   "8 Inactive Reminder TXT message notices sent out to:"
     *
     * @param string $body Email body
     *
     * @return array Array of section data
     */
    protected function parseSections(string $body): array
    {
        $sections = [];
        $lines    = preg_split("/(\r\n|\n|\r)/", $body);

        $currentSection = null;
        $pendingEntry   = []; // For multi-line entries from HTML

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ('' === $trimmed) {
                continue;
            }

            // Check if this is a section header
            // Pattern: "X Type E-mail notices sent out to:" or "X Type TXT message notices failed to:"
            if (preg_match('/^(\d+|No)\s+(.+?)\s+(E-mail|TXT message)\s+notices\s+(sent out to|failed to):$/i', $trimmed, $matches)) {
                // Save any pending entry from previous section
                if (!empty($pendingEntry) && $currentSection) {
                    $currentSection['entries'][] = $this->assemblePendingEntry($pendingEntry);
                    $pendingEntry                = [];
                }

                // Save previous section
                if ($currentSection) {
                    $sections[] = $currentSection;
                }

                $count            = ('No' === $matches[1]) ? 0 : (int) $matches[1];
                $notificationType = trim($matches[2]);
                $deliveryMethod   = ('E-mail' === $matches[3]) ? 'email' : 'sms';
                $status           = ('sent out to' === $matches[4]) ? 'sent' : 'failed';

                $currentSection = [
                    'count'             => $count,
                    'notification_type' => $notificationType,
                    'delivery_method'   => $deliveryMethod,
                    'status'            => $status,
                    'entries'           => [],
                ];

                continue;
            }

            // If we're in a section and count > 0, parse entries
            if ($currentSection && $currentSection['count'] > 0) {
                // Skip summary lines like "No X notices sent." which are not patron entries
                if (preg_match('/^no\s+.+notices\s+sent\.?$/i', $trimmed)) {
                    continue;
                }

                // Try to parse as single-line entry first (PDF/plain text format)
                $entry = $this->parseEntry($trimmed);
                if ($entry) {
                    // Save any previous pending entry
                    if (!empty($pendingEntry)) {
                        $currentSection['entries'][] = $this->assemblePendingEntry($pendingEntry);
                        $pendingEntry                = [];
                    }
                    $currentSection['entries'][] = $entry;
                } else {
                    // This might be part of a multi-line entry (HTML format)
                    // Lines appear as: PATRON PATRON, SAMPLE, EMAIL on separate lines
                    if (empty($pendingEntry)) {
                        // First line = patron name
                        $pendingEntry['patron_name'] = $trimmed;
                    } elseif (!isset($pendingEntry['patron_barcode'])) {
                        // Second line = barcode
                        $pendingEntry['patron_barcode'] = $trimmed;
                    } elseif (!isset($pendingEntry['email'])) {
                        // Third line = email
                        $pendingEntry['email'] = $trimmed;
                        // Entry complete, add it
                        $currentSection['entries'][] = $this->assemblePendingEntry($pendingEntry);
                        $pendingEntry                = [];
                    }
                }
            }
        }

        // Save any final pending entry
        if (!empty($pendingEntry) && $currentSection) {
            $currentSection['entries'][] = $this->assemblePendingEntry($pendingEntry);
        }

        // Save last section
        if ($currentSection) {
            $sections[] = $currentSection;
        }

        return $sections;
    }

    /**
     * Assemble a pending multi-line entry into a proper entry array.
     *
     * @param array $pending Pending entry parts
     *
     * @return array Complete entry
     */
    protected function assemblePendingEntry(array $pending): array
    {
        $raw = implode('  ', array_filter([
            $pending['patron_name']    ?? null,
            $pending['patron_barcode'] ?? null,
            $pending['email']          ?? null,
        ]));

        return [
            'raw'            => $raw,
            'patron_name'    => $pending['patron_name']    ?? null,
            'patron_barcode' => $pending['patron_barcode'] ?? null,
            'email'          => $pending['email']          ?? null,
        ];
    }

    /**
     * Parse a single entry line.
     *
     * Format: "PATRON NAME    BARCODE    email@address.com"
     *         "PATRON NAME    BARCODE    Email Failed"
     *
     * @param string $line Single line from section
     *
     * @return null|array Parsed entry or null if not parseable
     */
    protected function parseEntry(string $line): ?array
    {
        // Skip lines that don't look like entries
        if (str_starts_with(strtolower($line), 'no ')) {
            return null;
        }

        // Split by multiple spaces (typically 2+ spaces between fields)
        $parts = preg_split('/\s{2,}/', $line);

        if (count($parts) < 2) {
            return null;
        }

        return [
            'raw'            => $line,
            'patron_name'    => $parts[0] ?? null,
            'patron_barcode' => $parts[1] ?? null,
            'email'          => $parts[2] ?? null,
        ];
    }
}
