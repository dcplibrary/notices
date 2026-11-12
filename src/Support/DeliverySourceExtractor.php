<?php

namespace Dcplibrary\Notices\Support;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Extracts and formats delivery confirmation source data for notifications.
 *
 * This class provides methods to retrieve and format the original source data
 * that determined a notification's delivery status.
 *
 * ## Supported Channels:
 *
 * **Email/Mail (1, 2):**
 * - NotificationLog: Authoritative status from Polaris
 * - Summary Report: Parsed email report with section headers and counts
 *
 * **Voice/SMS (3, 8):**
 * - Export File: Original holds_/overdue_/renew_ file that created the notification
 * - Shoutbomb Failure: Parsed failure report from Shoutbomb email (if failed)
 *
 * ## NotificationLog is God 🖍️
 *
 * The NotificationLog (from Polaris notification_log) is the authoritative source
 * for email delivery status. Summary Reports serve as verification only.
 *
 * @see docs/NOTIFICATIONS README.md - "NotificationLog is GOD" principle
 * @see docs/DELIVERY-CHANNEL-DATA-SOURCES.md - Data source separation by channel
 */
class DeliverySourceExtractor
{
    /**
     * Extract delivery confirmation sources for a notification.
     *
     * **For Email/Mail notifications (delivery_option_id = 1, 2):**
     * - NotificationLog: Authoritative status from Polaris
     * - Summary Report: Parsed email report with section headers and counts
     *
     * **For Voice/SMS notifications (delivery_option_id = 3, 8):**
     * - Export File: Original holds_/overdue_/renew_ file information
     * - Shoutbomb Failure: Failure report details (if notification failed)
     *
     * Email confirmations are matched within a +3 day window from the export date
     * to account for late-day exports that appear in the next day's report.
     *
     * @param Notification $notification The notification to extract sources for
     *
     * @return array Associative array with channel-specific source keys.
     *               Empty array if notification has no sources.
     *
     *               **Email/Mail sources:**
     *               - notification_log:
     *                 - type: "Notification Log"
     *                 - log_date: Date string (e.g., "Jan 2, 2026")
     *                 - status: Status description (e.g., "Email Failed")
     *                 - status_id: Numeric status ID
     *               - summary_report:
     *                 - type: "Summary Report"
     *                 - received_at: DateTime string in local timezone (e.g., "January 3, 2026 8:04 AM")
     *                 - category: "Success" or "Failed"
     *                 - section_header: Email section header (e.g., "13 Hold E-mail notices failed to:")
     *                 - section_count: Number of notifications in this section
     *                 - details_line: Full patron detail line from email
     *                 - export_date: Notification export date in Y-m-d format (for filtering)
     *
     *               **Voice/SMS sources:**
     *               - export_file:
     *                 - filename: Source filename (e.g., "holds_2026-01-04_08-05-00.txt")
     *                 - export_date: Export date string (e.g., "Jan 4, 2026")
     *                 - file_type: File type prefix (holds_, overdue_, renew_)
     *               - shoutbomb_failure:
     *                 - received_at: DateTime when failure report was received
     *                 - details_line: Full failure line from email
     *                 - failure_type: Type of failure (e.g., "Invalid Phone Number")
     *
     * @see self::extractNotificationLogSource()
     * @see self::extractSummaryReportSource()
     * @see self::extractExportFileSource()
     * @see self::extractShoutbombFailureSource()
     */
    public static function extractSources(Notification $notification): array
    {
        $sources = [];

        // Email/Mail: Extract NotificationLog and Summary Report sources
        if (in_array($notification->delivery_option_id, [1, 2], true)) {
            return self::extractEmailMailSources($notification);
        }

        // Voice/SMS: Extract Export File and Shoutbomb Failure sources
        if (in_array($notification->delivery_option_id, [3, 8], true)) {
            return self::extractVoiceSmsSources($notification);
        }

        return $sources;
    }

    /**
     * Build a filtered list URL for viewing all notifications in a summary report section.
     *
     * @param Notification $notification        The notification to build filter URL for
     * @param array        $summaryReportSource Summary report source data from extractSummaryReportSource()
     *
     * @return null|string URL to filtered notification list, or null if count <= 1
     */
    public static function buildSectionFilterUrl(Notification $notification, array $summaryReportSource): ?string
    {
        $count      = $summaryReportSource['section_count'] ?? 0;
        $exportDate = $summaryReportSource['export_date']   ?? null;

        // Only generate link if there are multiple notifications (count > 1)
        if ($count <= 1 || !$exportDate) {
            return null;
        }

        $params = [
            'status'             => $notification->status,
            'delivery_option_id' => 2, // Email
            'notification_type'  => $notification->notification_type,
            'date_from'          => $exportDate,
            'date_to'            => $exportDate,
        ];

        return '/notices/list?' . http_build_query($params);
    }

    /**
     * Attempt to derive patron display information (name, barcode, email) from
     * delivery sources when the core notification payload is missing it.
     *
     * This is primarily used as a fallback for edge cases where Notification
     * rows were created from NotificationLog/summary report data without the
     * usual rich raw_payload from Polaris FTP exports.
     *
     * Example summary report details line:
     *   "SAMPLE PATRON 20000700725388 patron4338@example.org"
     *
     * @return null|array{full_name: null|string, barcode: null|string, email: null|string}
     */
    public static function extractPatronFromSources(Notification $notification): ?array
    {
        $sources = self::extractSources($notification);

        $detailsLine = $sources['summary_report']['details_line']
            ?? $sources['shoutbomb_failure']['details_line']
            ?? null;

        if (!$detailsLine) {
            return null;
        }

        return self::parsePatronLineFromDetails($detailsLine, $notification->patron_barcode);
    }

    /**
     * Build an activity log for a notification combining all contributing sources.
     *
     * Entries include export files, delivery confirmations (NotificationLog,
     * summary reports, Shoutbomb failures), and user-facing status changes.
     *
     * @param array $sources Sources array from extractSources()
     */
    public static function buildActivityLog(Notification $notification, array $sources): array
    {
        $events = [];

        // Helper to push an event with a standardized shape
        $push = function (?string $timestamp = null, string $type, string $description, ?string $category = null, array $meta = []) use (&$events) {
            $events[] = array_merge([
                'timestamp'   => $timestamp,
                'type'        => $type,
                'description' => $description,
                'category'    => $category,
            ], $meta);
        };

        // 1. Core lifecycle timestamps from the notification itself
        if ($notification->generated_at) {
            $push(
                $notification->generated_at->format('M j, Y g:i A'),
                'Notification Generated',
                'Notification created in Polaris',
                'export'
            );
        }

        if ($notification->first_exported_at) {
            $push(
                $notification->first_exported_at->format('M j, Y g:i A'),
                'First Exported',
                'First appearance in export file',
                'export',
                [
                    'filename' => $sources['export_file']['filename']  ?? $notification->source_filename ?? null,
                    'source'   => $sources['export_file']['file_type'] ?? null,
                ]
            );
        }

        if ($notification->last_exported_at && $notification->last_exported_at?->ne($notification->first_exported_at)) {
            $push(
                $notification->last_exported_at->format('M j, Y g:i A'),
                'Last Exported',
                'Last appearance in export file',
                'export',
                [
                    'filename' => $notification->importBatch?->identifier,
                ]
            );
        }

        if ($notification->delivered_at) {
            $push(
                $notification->delivered_at->format('M j, Y g:i A'),
                'Marked Delivered',
                'Notification status set to delivered',
                'delivery'
            );
        }

        if ($notification->failed_at) {
            $push(
                $notification->failed_at->format('M j, Y g:i A'),
                'Marked Failed',
                $notification->failure_reason_formatted ?: 'Notification status set to failed',
                'failure'
            );
        }

        // 2. Delivery confirmations from summary reports / notification log
        if (!empty($sources['notification_log'])) {
            $log = $sources['notification_log'];
            $push(
                $log['log_date'] ?? null,
                'Notification Log Updated',
                'NotificationLog delivery confirmation imported',
                'delivery',
                [
                    'source'  => 'notification_log',
                    'details' => $log['status'] ?? null,
                ]
            );
        }

        if (!empty($sources['summary_report'])) {
            $sr = $sources['summary_report'];
            $push(
                $sr['received_at'] ?? null,
                'Summary Report Processed',
                'Email summary report ingested for this patron',
                'enhancement',
                [
                    'source'   => 'summary_report',
                    'filename' => $sr['email_subject']     ?? null,
                    'details'  => $sr['section_header']    ?? null,
                    'email_id' => $sr['ingested_email_id'] ?? null,
                ]
            );
        }

        // 3. Shoutbomb failure reports for Voice/SMS
        if (!empty($sources['shoutbomb_failure'])) {
            $sf = $sources['shoutbomb_failure'];
            $push(
                $sf['received_at'] ?? null,
                'Shoutbomb Failure Report',
                $sf['details_line'] ?? 'Failure report ingested from Shoutbomb',
                'failure',
                [
                    'source'  => 'shoutbomb_failure',
                    'details' => $sf['failure_type'] ?? null,
                ]
            );
        }

        // 4. User-facing issues and resolutions (PatronDeliveryIssue records)
        foreach ($notification->issues as $issue) {
            // Issue detected
            if ($issue->detected_at) {
                $push(
                    $issue->detected_at->format('M j, Y g:i A'),
                    'Issue Detected',
                    $issue->issue_details ?: $issue->issue_type,
                    'enhancement',
                    [
                        'source' => 'issue_detector',
                    ]
                );
            }

            // Issue resolved / user modification
            if ($issue->resolved_at) {
                $push(
                    $issue->resolved_at->format('M j, Y g:i A'),
                    'Issue Resolved',
                    $issue->resolution_notes ?: 'Issue marked as resolved',
                    'enhancement',
                    [
                        'source' => 'user',
                    ]
                );
            }
        }

        // Sort by timestamp ascending where possible, keep nulls last
        usort($events, function ($a, $b) {
            if (empty($a['timestamp']) && empty($b['timestamp'])) {
                return 0;
            }
            if (empty($a['timestamp'])) {
                return 1;
            }
            if (empty($b['timestamp'])) {
                return -1;
            }

            return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);
        });

        return $events;
    }

    /**
     * Parse a free-form details line to extract patron name, barcode, and email.
     *
     * Heuristic rules (kept intentionally conservative and only used as a
     * fallback for display-only enrichment):
     * - Last token containing "@" is treated as email.
     * - Known barcode (from notification) wins; otherwise the first all-numeric
     *   token of length >= 10 is used as the barcode.
     * - Everything before the barcode token is treated as the patron name.
     */
    protected static function parsePatronLineFromDetails(string $line, ?string $knownBarcode = null): ?array
    {
        $tokens = preg_split('/\s+/', trim($line));

        if (!$tokens || count($tokens) < 2) {
            return null;
        }

        $email   = null;
        $barcode = $knownBarcode ?: null;

        // 1) Find email (last token with "@")
        for ($i = count($tokens) - 1; $i >= 0; --$i) {
            if (str_contains($tokens[$i], '@')) {
                $email = $tokens[$i];
                unset($tokens[$i]);
                $tokens = array_values($tokens);

                break;
            }
        }

        // 2) Find barcode if we don't already know it
        if (!$barcode) {
            foreach ($tokens as $index => $token) {
                if (preg_match('/^\d{8,}$/', $token)) {
                    $barcode      = $token;
                    $barcodeIndex = $index;

                    break;
                }
            }
        } else {
            $barcodeIndex = null;
            foreach ($tokens as $index => $token) {
                if ($token === $barcode) {
                    $barcodeIndex = $index;

                    break;
                }
            }
        }

        // 3) Derive name from tokens before the barcode
        $fullName = null;
        if (isset($barcodeIndex) && $barcodeIndex > 0) {
            $nameTokens = array_slice($tokens, 0, $barcodeIndex);
            if (!empty($nameTokens)) {
                $fullName = trim(implode(' ', $nameTokens));
            }
        }

        if (null === $fullName && null === $barcode && null === $email) {
            return null;
        }

        return [
            'full_name' => $fullName,
            'barcode'   => $barcode,
            'email'     => $email,
        ];
    }

    /**
     * Extract sources for Email/Mail notifications.
     */
    protected static function extractEmailMailSources(Notification $notification): array
    {
        $sources = [];

        // Retrieve delivery confirmations within +3 day matching window
        // IMPORTANT: Use the notification's own delivery_option_id so that:
        // - Email (2) looks at email confirmations
        // - Mail (1) can still surface its NotificationLog status without
        //   accidentally picking up email confirmations.
        $confirmations = DB::table('delivery_confirmations as dc')
            ->leftJoin('notification_statuses as ns', 'dc.notification_status_id', '=', 'ns.id')
            ->leftJoin('ingested_emails as ie', 'dc.ingested_email_id', '=', 'ie.id')
            ->where('dc.patron_barcode', $notification->patron_barcode)
            ->where('dc.delivery_option_id', $notification->delivery_option_id)
            ->whereNotNull('dc.notice_date')
            ->where(
                DB::raw('DATE(dc.notice_date)'),
                '>=',
                DB::raw('DATE("' . $notification->first_exported_at->format('Y-m-d') . '")')
            )
            ->where(
                DB::raw('DATE(dc.notice_date)'),
                '<=',
                DB::raw('DATE("' . $notification->first_exported_at->copy()->addDays(3)->format('Y-m-d') . '")')
            )
            ->select(
                'dc.confirmation_source',
                'dc.notice_date',
                'dc.notification_status_id',
                'ns.description as status_description',
                'dc.details',
                'dc.ingested_email_id',
                'ie.received_at',
                'ie.subject as email_subject'
            )
            ->orderBy('dc.notice_date')
            ->get()
        ;

        // Extract NotificationLog source and collect Summary Report candidates
        $notificationLogSource = null;
        $summaryCandidates     = collect();

        foreach ($confirmations as $conf) {
            if ('notification_log' === $conf->confirmation_source) {
                // For NotificationLog we just keep the last (most recent) row
                $notificationLogSource = self::extractNotificationLogSource($conf);
            } elseif (in_array($conf->confirmation_source, ['polaris_summary_report_success', 'polaris_summary_report_failure'])) {
                $summaryCandidates->push($conf);
            }
        }

        if ($notificationLogSource) {
            $sources['notification_log'] = $notificationLogSource;
        }

        // Choose a Summary Report entry using the same anchor-date concept as the reconciler
        if ($summaryCandidates->isNotEmpty()) {
            $anchorDate = $notification->first_exported_at
                ? $notification->first_exported_at->format('Y-m-d')
                : null;

            $summaryForDay = collect();
            if ($anchorDate) {
                $summaryForDay = $summaryCandidates->filter(function ($row) use ($anchorDate) {
                    if (!$row->notice_date) {
                        return false;
                    }

                    $rowDate = (new \DateTime($row->notice_date))->format('Y-m-d');

                    return $rowDate === $anchorDate;
                });
            }

            if ($summaryForDay->isNotEmpty()) {
                $chosen = $summaryForDay->sortBy('notice_date')->first();
            } else {
                // Fallback: earliest summary for this patron+channel
                $chosen = $summaryCandidates->sortBy('notice_date')->first();
            }

            if ($chosen) {
                $sources['summary_report'] = self::extractSummaryReportSource($chosen, $notification);
            }
        }

        return $sources;
    }

    /**
     * Extract sources for Voice/SMS notifications.
     */
    protected static function extractVoiceSmsSources(Notification $notification): array
    {
        $sources = [];

        // Extract export file information
        $sources['export_file'] = self::extractExportFileSource($notification);

        // Extract Shoutbomb failure information (if failed)
        if ('failed' === $notification->status && $notification->failure_reason) {
            $sources['shoutbomb_failure'] = self::extractShoutbombFailureSource($notification);
        }

        return $sources;
    }

    /**
     * Extract export file source information for Voice/SMS notifications.
     */
    protected static function extractExportFileSource(Notification $notification): array
    {
        // Extract source filename from raw payload
        $raw     = $notification->raw_payload ? json_decode($notification->raw_payload, true) : null;
        $rawRows = is_array($raw) ? $raw : [];

        $sourceFilename = null;
        foreach ($rawRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = $row['source_filename'] ?? null;
            if (null !== $value && '' !== $value) {
                $sourceFilename = $value;

                break;
            }
        }

        // Determine file type from notification type
        $fileType = match ($notification->notification_type) {
            'Hold'                                                         => 'holds_',
            'Overdue', '1st Overdue', '2nd Overdue', '3rd Overdue', 'Bill' => 'overdue_',
            'Renewal'                                                      => 'renew_',
            default                                                        => 'unknown_',
        };

        return [
            'filename'    => $sourceFilename,
            'export_date' => $notification->first_exported_at?->format('M j, Y'),
            'file_type'   => $fileType,
        ];
    }

    /**
     * Extract Shoutbomb failure report information.
     */
    protected static function extractShoutbombFailureSource(Notification $notification): ?array
    {
        // Query for the Shoutbomb failure report. In some environments (e.g. tests
        // or partially-migrated installs) the ingested_email_lines table may not
        // exist yet. In that case we fall back to using the raw failure_reason
        // stored on the notification instead of throwing a hard 500.
        try {
            $failureReport = DB::table('ingested_emails as ie')
                ->join('ingested_email_lines as iel', 'ie.id', '=', 'iel.ingested_email_id')
                ->where('ie.subject', 'like', '%shoutbomb%')
                ->where('ie.subject', 'like', '%failure%')
                ->where(function ($query) use ($notification) {
                    // Match by patron barcode OR phone number in the details line
                    $query->where('iel.details', 'like', '%' . $notification->patron_barcode . '%');

                    // Also try to match by phone number from failure reason
                    if (preg_match('/\((\d{10})\)/', $notification->failure_reason, $matches)) {
                        $query->orWhere('iel.details', 'like', '%' . $matches[1] . '%');
                    }
                })
                ->where('ie.received_at', '>=', $notification->first_exported_at)
                ->where('ie.received_at', '<=', $notification->first_exported_at->copy()->addDays(7))
                ->select(
                    'ie.received_at',
                    'ie.subject',
                    'iel.details as details_line'
                )
                ->orderBy('ie.received_at')
                ->first()
            ;
        } catch (\Throwable $e) {
            // If the table is missing or the query fails for any reason, log a
            // warning and fall back to the failure_reason on the notification.
            \Log::warning('Failed to query Shoutbomb failure report for notification', [
                'notification_id' => $notification->id,
                'error'           => $e->getMessage(),
            ]);

            $failureReport = null;
        }

        if (!$failureReport) {
            return [
                'received_at'  => $notification->failed_at?->format('M j, Y g:i A'),
                'details_line' => $notification->failure_reason,
                'failure_type' => self::classifyShoutbombFailure($notification->failure_reason),
            ];
        }

        return [
            'received_at' => Carbon::parse($failureReport->received_at, 'UTC')
                ->setTimezone(config('app.timezone'))
                ->format('M j, Y g:i A'),
            'details_line' => $failureReport->details_line,
            'failure_type' => self::classifyShoutbombFailure($failureReport->details_line),
        ];
    }

    /**
     * Classify Shoutbomb failure type from failure message.
     */
    protected static function classifyShoutbombFailure(?string $failureMessage): string
    {
        if (!$failureMessage) {
            return 'Unknown';
        }

        if (str_contains(strtolower($failureMessage), 'opt')
            || str_contains(strtolower($failureMessage), 'opted-out')) {
            return 'Opted Out';
        }

        if (str_contains(strtolower($failureMessage), 'invalid')) {
            return 'Invalid Phone Number';
        }

        if (str_contains(strtolower($failureMessage), 'landline')) {
            return 'Landline';
        }

        if (str_contains(strtolower($failureMessage), 'carrier')) {
            return 'Carrier Issue';
        }

        return 'Delivery Failure';
    }

    /**
     * Extract and format NotificationLog source data.
     *
     * @param object $confirmation Confirmation record from database query
     *
     * @return array Formatted notification log source data
     */
    protected static function extractNotificationLogSource(object $confirmation): array
    {
        return [
            'type'      => 'Notification Log',
            'log_date'  => Carbon::parse($confirmation->notice_date)->format('M j, Y'),
            'status'    => $confirmation->status_description ?? 'Unknown',
            'status_id' => $confirmation->notification_status_id,
        ];
    }

    /**
     * Extract and format Summary Report source data.
     *
     * Parses the ingested email's parsed_data JSON to find the section header
     * and count for the patron's entry in the email report.
     *
     * @param object       $confirmation Confirmation record from database query
     * @param Notification $notification The notification being processed
     *
     * @return array Formatted summary report source data
     */
    protected static function extractSummaryReportSource(object $confirmation, Notification $notification): array
    {
        $category = str_contains($confirmation->confirmation_source, 'success') ? 'Success' : 'Failed';

        // Parse email data to find the section header and count
        $sectionHeader = null;
        $sectionCount  = null;
        $sectionDate   = null;

        if ($confirmation->ingested_email_id) {
            $emailData = DB::table('ingested_emails')
                ->where('id', $confirmation->ingested_email_id)
                ->first()
            ;

            if ($emailData && $emailData->parsed_data) {
                $parsedData  = json_decode($emailData->parsed_data, true);
                $sectionDate = $parsedData['report_date'] ?? null;

                // Find the matching section where this patron appears
                if (isset($parsedData['sections'])) {
                    foreach ($parsedData['sections'] as $section) {
                        if (isset($section['entries'])) {
                            foreach ($section['entries'] as $entry) {
                                if (isset($entry['patron_barcode'])
                                    && $entry['patron_barcode'] === $notification->patron_barcode) {
                                    $sectionCount   = $section['count'] ?? null;
                                    $notifType      = ucfirst($section['notification_type'] ?? 'notification');
                                    $deliveryMethod = ucfirst($section['delivery_method'] ?? 'delivery');
                                    $status         = ucfirst($section['status'] ?? 'status');

                                    // Build section header like "Hold E-mail notices failed to:"
                                    // Note: Count is omitted from header as it represents email report count,
                                    // not database count (which may differ due to retries/latest status logic)
                                    $sectionHeader = $notifType . ' '
                                        . ('Email' === $deliveryMethod ? 'E-mail' : $deliveryMethod)
                                        . ' notices ' . ('Failed' === $status ? 'failed to' : $status) . ':';

                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'type'        => 'Summary Report',
            'received_at' => $confirmation->received_at
                ? Carbon::parse($confirmation->received_at, 'UTC')
                    ->setTimezone(config('app.timezone'))
                    ->format('F j, Y g:i A')
                : null,
            'category'          => $category,
            'details_line'      => $confirmation->details,
            'email_subject'     => $confirmation->email_subject,
            'ingested_email_id' => $confirmation->ingested_email_id,
            'section_header'    => $sectionHeader,
            'section_count'     => $sectionCount,
            'section_date'      => $sectionDate ? Carbon::parse($sectionDate)->format('Y-m-d') : null,
            // Use the notification's export date for filtering, not the report date
            'export_date' => $notification->first_exported_at
                ? $notification->first_exported_at->format('Y-m-d')
                : null,
        ];
    }
}
