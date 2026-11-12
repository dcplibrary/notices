<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Contracts\EmailIngestionInterface;
use Dcplibrary\Notices\Models\ActivityFile;
use Dcplibrary\Notices\Models\ActivityLog;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\DeliveryOption;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\NotificationType;
use Dcplibrary\Notices\Services\EmailReportParsers\PolarisSummaryReportParser;
use Dcplibrary\Notices\Services\EmailReportParsers\ShoutbombDailyInvalidPhoneParser;
use Dcplibrary\Notices\Services\EmailReportParsers\ShoutbombDailyVoiceFailureParser;
use Dcplibrary\Notices\Services\EmailReportParsers\ShoutbombMismatchedPreferenceParser;
use Dcplibrary\Notices\Services\EmailReportParsers\ShoutbombVoiceSystemErrorParser;
use Illuminate\Support\Facades\Http;

/**
 * Microsoft Graph based email ingestion for notification-related emails.
 *
 * Ingests both Shoutbomb failure reports and Polaris Summary Reports:
 * - "Voice notices that were not delivered ..." (Shoutbomb)
 * - "Invalid patron phone number ..." (Shoutbomb)
 * - "Calls that generated errors ..." (Shoutbomb)
 * - "Summary Report - Daviess County Public Library" (Polaris)
 */
class GraphEmailIngestionService implements EmailIngestionInterface
{
    protected const SHOUTBOMB_FAILURE_REPORT_TYPES = [
        'shoutbomb_voice_failure',
        'shoutbomb_invalid_phone',
        'shoutbomb_voice_system_error',
    ];

    public function ingestForDate(\DateTimeInterface $date): void
    {
        $config = config('notices.microsoft');
        if (!$config || !($config['tenant_id'] ?? null) || !($config['client_id'] ?? null) || !($config['client_secret'] ?? null)) {
            \Log::warning('GraphEmailIngestionService: Microsoft configuration missing, skipping ingestion');

            return;
        }

        $dayStart = Carbon::instance(new \DateTimeImmutable($date->format('Y-m-d')))->startOfDay();
        $dayEnd   = $dayStart->copy()->endOfDay();

        $accessToken = $this->getAccessToken(
            $config['tenant_id'],
            $config['client_id'],
            $config['client_secret'],
        );

        $mailbox = $config['mailbox'] ?? null;
        if (!$mailbox) {
            \Log::warning('GraphEmailIngestionService: MICROSOFT_MAILBOX not configured, skipping ingestion');

            return;
        }

        $folder = $config['folder_shoutbomb'] ?? 'Shoutbomb_Reports';

        $emails = $this->fetchEmailsForDate($accessToken, $mailbox, $folder, $dayStart, $dayEnd, (int) ($config['limit'] ?? 100));

        ActivityLog::create([
            'action'       => 'email.ingest.run',
            'description'  => 'Ingest Shoutbomb email reports via Microsoft Graph',
            'subject_type' => null,
            'subject_id'   => null,
            'meta'         => [
                'date'    => $dayStart->toDateString(),
                'mailbox' => $mailbox,
                'folder'  => $folder,
                'count'   => count($emails),
            ],
        ]);

        foreach ($emails as $message) {
            $this->processEmailMessage($message);
        }
    }

    protected function getAccessToken(string $tenantId, string $clientId, string $clientSecret): string
    {
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('GraphEmailIngestionService: failed to obtain access token: ' . $response->body());
        }

        return (string) $response->json('access_token');
    }

    protected function fetchEmailsForDate(string $accessToken, string $userPrincipalName, string $folder, Carbon $from, Carbon $to, int $limit = 100): array
    {
        $startIso = $from->toIso8601String();
        $endIso   = $to->toIso8601String();

        $filter = "receivedDateTime ge {$startIso} and receivedDateTime le {$endIso}";

        $configuredFrom = config('notices.microsoft.email_from');
        if (!empty($configuredFrom)) {
            $senders = array_filter(array_map('trim', explode(',', $configuredFrom)));

            if (!empty($senders)) {
                $senderFilters = array_map(
                    static function (string $sender): string {
                        $escaped = str_replace("'", "''", $sender);

                        return "from/emailAddress/address eq '{$escaped}'";
                    },
                    $senders
                );

                $filter .= ' and (' . implode(' or ', $senderFilters) . ')';
            }
        }

        $folderId = $this->resolveMailFolderId($accessToken, $userPrincipalName, $folder);

        $url = "https://graph.microsoft.com/v1.0/users/{$userPrincipalName}/mailFolders/{$folderId}/messages";

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get($url, [
                '$filter'  => $filter,
                '$select'  => 'id,subject,from,toRecipients,receivedDateTime,body,hasAttachments',
                '$orderby' => 'receivedDateTime desc',
                '$top'     => $limit,
            ])
        ;

        if (!$response->successful()) {
            throw new \RuntimeException('GraphEmailIngestionService: failed to fetch emails: ' . $response->body());
        }

        return $response->json('value', []);
    }

    protected function resolveMailFolderId(string $accessToken, string $userPrincipalName, string $folder): string
    {
        $folder = trim($folder);

        if ('' === $folder) {
            return 'Inbox';
        }

        $directResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->get("https://graph.microsoft.com/v1.0/users/{$userPrincipalName}/mailFolders/{$folder}", [
                '$select' => 'id,displayName',
            ])
        ;

        if ($directResponse->successful()) {
            return $directResponse->json('id', $folder);
        }

        $segments = array_filter(array_map('trim', explode('/', $folder)));
        if (empty($segments)) {
            throw new \RuntimeException("GraphEmailIngestionService: invalid mail folder '{$folder}'");
        }

        $currentFolderId = null;

        foreach ($segments as $segment) {
            $endpoint = $currentFolderId
                ? "https://graph.microsoft.com/v1.0/users/{$userPrincipalName}/mailFolders/{$currentFolderId}/childFolders"
                : "https://graph.microsoft.com/v1.0/users/{$userPrincipalName}/mailFolders";

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get($endpoint, [
                    '$select' => 'id,displayName',
                    '$top'    => 100,
                ])
            ;

            if (!$response->successful()) {
                throw new \RuntimeException("GraphEmailIngestionService: failed to resolve folder segment '{$segment}': " . $response->body());
            }

            $matched = null;
            foreach ($response->json('value', []) as $candidate) {
                if (!isset($candidate['displayName'])) {
                    continue;
                }

                if (0 === strcasecmp($candidate['displayName'], $segment)) {
                    $matched = $candidate;

                    break;
                }
            }

            if (!$matched) {
                throw new \RuntimeException("GraphEmailIngestionService: folder segment '{$segment}' not found while resolving '{$folder}'");
            }

            $currentFolderId = $matched['id'] ?? null;
        }

        if (!$currentFolderId) {
            throw new \RuntimeException("GraphEmailIngestionService: unable to resolve mail folder '{$folder}'");
        }

        return $currentFolderId;
    }

    protected function processEmailMessage(array $message): void
    {
        $messageId   = $message['id']                              ?? null;
        $subject     = $message['subject']                         ?? '';
        $fromAddress = $message['from']['emailAddress']['address'] ?? null;
        $toAddresses = array_map(
            static fn (array $r) => $r['emailAddress']['address'] ?? null,
            $message['toRecipients'] ?? []
        );
        $receivedAt     = isset($message['receivedDateTime']) ? Carbon::parse($message['receivedDateTime']) : Carbon::now();
        $bodyContent    = $message['body']['content']     ?? '';
        $bodyType       = $message['body']['contentType'] ?? 'text';
        $hasAttachments = (bool) ($message['hasAttachments'] ?? false);

        // Convert HTML to plain text if needed
        if ('html' === strtolower($bodyType) && !empty($bodyContent)) {
            $bodyContent = $this->convertHtmlToText($bodyContent);
        }

        // Classify email to determine report type and parse it if applicable
        $classification = $this->classifyReportBySubject($subject);

        // Parse email if it has a parser
        $parsed     = null;
        $reportType = null;
        if ($classification) {
            [$reportType, $parser] = $classification;
            $parsed                = $parser->parse($subject, $bodyContent);
        }

        // Shoutbomb reports arrive the morning after the export they cover. If the
        // subject date cannot be read, fall back to that convention rather than
        // storing NULL report/notice dates, which reconciliation never matches.
        if ($parsed && in_array($reportType, self::SHOUTBOMB_FAILURE_REPORT_TYPES, true) && empty($parsed['report_date'])) {
            $parsed['report_date'] = $receivedAt->copy()
                ->setTimezone(config('app.timezone'))
                ->startOfDay()
                ->subDay()
            ;

            \Log::warning('GraphEmailIngestionService: no date in Shoutbomb subject; using received date - 1 day', [
                'message_id'  => $messageId,
                'subject'     => $subject,
                'report_date' => $parsed['report_date']->toDateString(),
            ]);
        }

        // Determine report type code
        if ($classification) {
            $reportTypeCode = $classification[0];
        } elseif (str_contains(strtolower($subject), 'notice files')) {
            $reportTypeCode = 'notice_files';
        } else {
            $reportTypeCode = 'other';
        }

        // Get report type ID from lookup table
        $emailReportType = \Dcplibrary\Notices\Models\EmailReportType::where('code', $reportTypeCode)->first();

        // Idempotency:
        // - For structured reports (Summary / Shoutbomb), dedupe by logical report identity
        //   (report_type, report_date). Only one row per (type, date) is stored even
        //   if the underlying email body changes slightly.
        // - A Shoutbomb failure report row stored with zero parsed records is not
        //   useful coverage. If this pass actually parsed records, refill that row
        //   instead of skipping it. That recovers mail imported while a subject or
        //   body format was not recognized (e.g. the August 2026 call-error lines).
        // - For unstructured messages (notice files, other), fall back to message_id.
        $refillExisting = null;
        if ($classification && $parsed && isset($parsed['report_date']) && $emailReportType) {
            $reportDate = $parsed['report_date'];
            if ($reportDate instanceof \DateTimeInterface) {
                $reportDateKey = $reportDate->format('Y-m-d');
            } else {
                $reportDateKey = (string) $reportDate;
            }

            // For structured reports we only need one row per (type, report_date).
            // Different bodies for the same logical report day (e.g. re-sent failure
            // reports) are treated as duplicates and skipped.
            try {
                $reportDayStart     = Carbon::parse($reportDateKey)->startOfDay();
                $existingStructured = IngestedEmail::where('email_report_type_id', $emailReportType->id)
                    ->where('report_date', '>=', $reportDayStart)
                    ->where('report_date', '<', $reportDayStart->copy()->addDay())
                    ->first()
                ;
            } catch (\Throwable $e) {
                $existingStructured = IngestedEmail::where('email_report_type_id', $emailReportType->id)
                    ->where('report_date', $reportDateKey)
                    ->first()
                ;
            }

            if ($existingStructured) {
                $canRefill = in_array($reportType, self::SHOUTBOMB_FAILURE_REPORT_TYPES, true)
                    && 0 === (int) ($existingStructured->parsed_record_count ?? 0)
                    && $this->countParsedRecords($parsed) > 0;

                if (!$canRefill) {
                    return;
                }

                $refillExisting = $existingStructured;
            }
        } elseif ($messageId && IngestedEmail::where('message_id', $messageId)->exists()) {
            // Unstructured stuff (notice files, other): still dedupe by id.
            return;
        }

        // For structured reports, Graph may reuse message IDs that were previously used
        // for unrelated messages (e.g., notice_files). Because ingested_emails.message_id
        // has a UNIQUE constraint in MySQL, we need to synthesize a new message_id for the
        // structured report if the raw Graph ID is already present on another row.
        //
        // Rules:
        // - Structured reports dedupe by (report_type, report_date) above.
        // - If the raw Graph message_id collides with an existing row, we store the
        //   structured report as "<original-id>#YYYY-MM-DD" where YYYY-MM-DD is the
        //   logical report_date.
        // - Unstructured mail (notice_files / other) continues to dedupe purely by
        //   message_id and never creates a second row.
        $storedMessageId = $messageId ?? uniqid('graph_', true);

        if ($classification && $parsed && isset($parsed['report_date']) && $emailReportType && $messageId) {
            $messageIdExists = IngestedEmail::where('message_id', $messageId)->exists();

            if ($messageIdExists) {
                $reportDate = $parsed['report_date'];
                if ($reportDate instanceof \DateTimeInterface) {
                    $dateKey = $reportDate->format('Y-m-d');
                } else {
                    $dateKey = (string) $reportDate;
                }

                $storedMessageId = $messageId . '#' . $dateKey;
            } else {
                $storedMessageId = $messageId;
            }
        }

        $recordCount = $this->countParsedRecords($parsed);

        // For structured Summary Reports (polaris_summary_report), a parsed_record_count
        // of 0 indicates a parser/import problem and MUST NOT be treated as valid
        // coverage for that date. We still store the email for audit, but
        // reconciliation must ignore it when determining report(D) existence.
        if ($classification && 'polaris_summary_report' === $reportType && 0 === $recordCount) {
            \Log::warning('GraphEmailIngestionService: Polaris Summary Report parsed with 0 records; storing as invalid import', [
                'message_id'  => $messageId,
                'subject'     => $subject,
                'received_at' => $receivedAt->toIso8601String(),
            ]);
        }

        $emailAttributes = [
            'email_report_type_id' => $emailReportType?->id,
            'subject'              => $subject,
            'body_preview'         => substr($bodyContent, 0, 500),
            'body_full'            => $bodyContent,
            'received_at'          => $receivedAt,
            'imported_at'          => Carbon::now(),
            'sender_email'         => $fromAddress,
            'attachments_count'    => $hasAttachments ? ($message['attachments'] ?? 0) : 0,
            'raw_metadata'         => $message,
            'parsed_data'          => $parsed,
            'parsed_record_count'  => $recordCount,
            'processing_status'    => 'processed',
            'processed_at'         => Carbon::now(),
            'report_date'          => $parsed['report_date']    ?? null,
            'failures_count'       => $parsed['failures_count'] ?? $parsed['total_failed'] ?? null,
            'invalid_phones_count' => $parsed['invalid_count']  ?? null,
        ];

        if ($refillExisting) {
            $refillExisting->update($emailAttributes);
            $ingestedEmail   = $refillExisting->fresh();
            $storedMessageId = $ingestedEmail->message_id;

            DeliveryConfirmation::where('ingested_email_id', $ingestedEmail->id)
                ->where('confirmation_source', $reportType)
                ->delete()
            ;
        } else {
            // Final idempotency guard: if a row with this stored message_id already
            // exists (e.g. the same structured report was ingested by an older
            // version of the parser or with slightly different whitespace), skip
            // creating a new record to avoid UNIQUE(message_id) violations.
            if (IngestedEmail::where('message_id', $storedMessageId)->exists()) {
                return;
            }

            $ingestedEmail = IngestedEmail::create(array_merge(
                ['message_id' => $storedMessageId],
                $emailAttributes,
            ));
        }

        // Create an ActivityFile record so notification activity log entries can
        // link directly to this email report (subject shown as filename).
        if ($emailReportType) {
            $fileType = match ($reportTypeCode) {
                'polaris_summary_report' => 'summary_report_email',
                'shoutbomb_voice_failure',
                'shoutbomb_invalid_phone',
                'shoutbomb_voice_system_error',
                'shoutbomb_mismatched_preference' => 'shoutbomb_email',
                default                           => 'email_report',
            };

            // Determine a stable date component for the ActivityFile hash and
            // file_date. Prefer the parsed report_date when available, otherwise
            // fall back to the email's received date.
            $fileDate     = null;
            $hashDatePart = '';

            if (is_array($parsed) && array_key_exists('report_date', $parsed) && null !== $parsed['report_date']) {
                $reportDateValue = $parsed['report_date'];

                if ($reportDateValue instanceof \DateTimeInterface) {
                    $fileDate     = $reportDateValue;
                    $hashDatePart = $reportDateValue->format('Y-m-d');
                } else {
                    try {
                        $fileDate     = Carbon::parse((string) $reportDateValue)->startOfDay();
                        $hashDatePart = $fileDate->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $fileDate     = $receivedAt->copy()->startOfDay();
                        $hashDatePart = $fileDate->format('Y-m-d');
                    }
                }
            } else {
                $fileDate     = $receivedAt->copy()->startOfDay();
                $hashDatePart = $fileDate->format('Y-m-d');
            }

            ActivityFile::firstOrCreate(
                [
                    'type'          => $fileType,
                    'original_name' => $subject,
                    'hash'          => sha1($storedMessageId . '|' . $hashDatePart),
                ],
                [
                    'parsed_at' => Carbon::now(),
                    'file_date' => $fileDate,
                    'metadata'  => [
                        'ingested_email_id' => $ingestedEmail->id,
                        'report_type'       => $reportTypeCode,
                        'subject'           => $subject,
                    ],
                ],
            );
        }

        ActivityLog::create([
            'action'       => 'email.ingest.message',
            'description'  => 'Ingested notification email report',
            'subject_type' => IngestedEmail::class,
            'subject_id'   => $ingestedEmail->id,
            'meta'         => [
                'message_id'  => $ingestedEmail->message_id,
                'subject'     => $subject,
                'report_type' => $reportType,
                'received_at' => $ingestedEmail->received_at?->toIso8601String(),
            ],
        ]);

        if (!$classification) {
            // Not a parseable report - still stored for audit
            return;
        }

        // Store structured failures/invalids/system errors in DeliveryConfirmation
        // In a later step we will correlate these to Notification records
        if ('shoutbomb_voice_failure' === $reportType && !empty($parsed['failures'] ?? [])) {
            foreach ($parsed['failures'] as $failure) {
                DeliveryConfirmation::create([
                    'confirmation_source'    => 'shoutbomb_voice_failure',
                    'source_record_id'       => (string) $ingestedEmail->id,
                    'notification_id'        => null,
                    'notification_key'       => null,
                    'patron_id'              => null,
                    'item_record_id'         => null,
                    'notification_type_id'   => null,
                    'delivery_option_id'     => 3, // Voice
                    'reporting_org_id'       => null,
                    'notice_date'            => $parsed['report_date'] ?? null,
                    'notification_status_id' => null,
                    'delivery_string'        => $failure['phone'] ?? null,
                    'details'                => $failure['raw']   ?? null,
                    'holds_count'            => null,
                    'cancels_count'          => null,
                    'patron_barcode'         => $failure['barcode'] ?? null,
                    'ingested_email_id'      => $ingestedEmail->id,
                ]);
            }
        }

        if ('shoutbomb_invalid_phone' === $reportType) {
            foreach (['opted_out', 'invalid'] as $bucket) {
                foreach ($parsed[$bucket] ?? [] as $entry) {
                    // Convert delivery_option string ("SMS" or "Voice") to delivery_option_id (8 or 3)
                    $deliveryOptionId = null;
                    if (!empty($entry['delivery_option'])) {
                        $deliveryOptionLower = strtolower(trim($entry['delivery_option']));
                        // Map: SMS -> 8, Voice -> 3
                        if (in_array($deliveryOptionLower, ['sms', 'txt', 'txt messaging', 'text'], true)) {
                            $deliveryOptionId = 8; // SMS
                        } elseif (in_array($deliveryOptionLower, ['voice', 'phone', 'phone 1'], true)) {
                            $deliveryOptionId = 3; // Voice
                        }
                    }

                    DeliveryConfirmation::create([
                        'confirmation_source'    => 'shoutbomb_invalid_phone',
                        'source_record_id'       => (string) $ingestedEmail->id,
                        'notification_id'        => null,
                        'notification_key'       => null,
                        'patron_id'              => $entry['patron_id'] ?? null,
                        'item_record_id'         => null,
                        'notification_type_id'   => null,
                        'delivery_option_id'     => $deliveryOptionId,
                        'reporting_org_id'       => $entry['organization_id'] ?? null,
                        'notice_date'            => $parsed['report_date']    ?? null,
                        'notification_status_id' => null,
                        'delivery_string'        => $entry['phone'] ?? null,
                        'details'                => $entry['raw']   ?? null,
                        'holds_count'            => null,
                        'cancels_count'          => null,
                        'patron_barcode'         => $entry['barcode'] ?? null,
                        'ingested_email_id'      => $ingestedEmail->id,
                    ]);
                }
            }
        }

        if ('shoutbomb_voice_system_error' === $reportType && !empty($parsed['errors'] ?? [])) {
            foreach ($parsed['errors'] as $entry) {
                DeliveryConfirmation::create([
                    'confirmation_source'    => 'shoutbomb_voice_system_error',
                    'source_record_id'       => (string) $ingestedEmail->id,
                    'notification_id'        => null,
                    'notification_key'       => null,
                    'patron_id'              => null,
                    'item_record_id'         => null,
                    'notification_type_id'   => null,
                    'delivery_option_id'     => 3,
                    'reporting_org_id'       => null,
                    'notice_date'            => $parsed['report_date'] ?? null,
                    'notification_status_id' => null,
                    'delivery_string'        => $entry['patron_phone'] ?? null,
                    'details'                => $entry['raw']          ?? null,
                    'holds_count'            => null,
                    'cancels_count'          => null,
                    'patron_barcode'         => null,
                    'ingested_email_id'      => $ingestedEmail->id,
                ]);
            }
        }

        if ('shoutbomb_mismatched_preference' === $reportType && !empty($parsed['mismatches'] ?? [])) {
            foreach ($parsed['mismatches'] as $mismatch) {
                // Store one DeliveryConfirmation per mismatch for tracking
                // The old barcodes are the ones that need updating
                foreach ($mismatch['old_barcodes'] ?? [] as $oldBarcode) {
                    DeliveryConfirmation::create([
                        'confirmation_source'    => 'shoutbomb_mismatched_preference',
                        'source_record_id'       => (string) $ingestedEmail->id,
                        'notification_id'        => null,
                        'notification_key'       => null,
                        'patron_id'              => null,
                        'item_record_id'         => null,
                        'notification_type_id'   => null,
                        'delivery_option_id'     => $mismatch['new_delivery_option_id'], // The preference to match
                        'reporting_org_id'       => null,
                        'notice_date'            => $parsed['report_date'] ?? null,
                        'notification_status_id' => null,
                        'delivery_string'        => $mismatch['patron_phone'],
                        'details'                => json_encode($mismatch), // Store full mismatch data
                        'holds_count'            => null,
                        'cancels_count'          => null,
                        'patron_barcode'         => $oldBarcode, // The barcode that needs updating
                        'ingested_email_id'      => $ingestedEmail->id,
                    ]);
                }
            }
        }

        if ('polaris_summary_report' === $reportType) {
            // Process successful deliveries
            foreach ($parsed['successes'] ?? [] as $success) {
                $deliveryOptionId = $this->getDeliveryOptionIdFromMethod($success['delivery_method'] ?? 'email');

                // Look up notification_type_id from the description (e.g., "Hold", "1st Overdue")
                $notificationTypeId = $this->getNotificationTypeIdFromDescription($success['notification_type'] ?? null);

                // If there's a failure_reason, this is a Polaris system failure
                $confirmationSource = $success['failure_reason']
                    ? 'polaris_summary_report_system_failure'
                    : 'polaris_summary_report_success';

                // Guard against parser bugs where narrative text (e.g. "No Almost overdue/Auto-renew
                // reminder notices sent.") ends up in patron_barcode. The DB column is VARCHAR(50)
                // and should only contain actual barcodes. If the value is clearly not a barcode
                // (too long, contains whitespace, or is empty after trim), drop it to avoid
                // SQLSTATE[22001] errors and bogus matches.
                //
                // IMPORTANT: Do NOT require digits-only. Real-world barcodes at DCPL sometimes
                // include alphabetic prefixes/suffixes (e.g. "MO3", "TUW"), and those MUST be
                // preserved so Summary Reports can be matched back to notifications.
                $patronBarcode = $success['patron_barcode'] ?? null;
                if (null !== $patronBarcode) {
                    $patronBarcode = trim((string) $patronBarcode);

                    if ('' === $patronBarcode || strlen($patronBarcode) > 50 || preg_match('/\s/', $patronBarcode)) {
                        \Log::warning('Ignoring non-barcode patron_barcode from polaris_summary_report_success', [
                            'patron_barcode'    => $patronBarcode,
                            'ingested_email_id' => $ingestedEmail->id ?? null,
                        ]);
                        $patronBarcode = null;
                    }
                }

                DeliveryConfirmation::create([
                    'confirmation_source'    => $confirmationSource,
                    'source_record_id'       => (string) $ingestedEmail->id,
                    'notification_id'        => null,
                    'notification_key'       => null,
                    'patron_id'              => null,
                    'item_record_id'         => null,
                    'notification_type_id'   => $notificationTypeId,
                    'delivery_option_id'     => $deliveryOptionId,
                    'reporting_org_id'       => null,
                    'notice_date'            => $parsed['report_date'] ?? null,
                    'notification_status_id' => null,
                    'delivery_string'        => $success['email']          ?? null,
                    'details'                => $success['failure_reason'] ?? $success['raw'] ?? null,
                    'holds_count'            => null,
                    'cancels_count'          => null,
                    'patron_barcode'         => $patronBarcode,
                    'ingested_email_id'      => $ingestedEmail->id,
                ]);
            }

            // Process actual failures
            foreach ($parsed['failures'] ?? [] as $failure) {
                $deliveryOptionId = $this->getDeliveryOptionIdFromMethod($failure['delivery_method'] ?? 'email');

                // Look up notification_type_id from the description (e.g., "Hold", "1st Overdue")
                $notificationTypeId = $this->getNotificationTypeIdFromDescription($failure['notification_type'] ?? null);

                DeliveryConfirmation::create([
                    'confirmation_source'    => 'polaris_summary_report_failure',
                    'source_record_id'       => (string) $ingestedEmail->id,
                    'notification_id'        => null,
                    'notification_key'       => null,
                    'patron_id'              => null,
                    'item_record_id'         => null,
                    'notification_type_id'   => $notificationTypeId,
                    'delivery_option_id'     => $deliveryOptionId,
                    'reporting_org_id'       => null,
                    'notice_date'            => $parsed['report_date'] ?? null,
                    'notification_status_id' => null,
                    'delivery_string'        => $failure['email'] ?? null,
                    'details'                => $failure['raw']   ?? null,
                    'holds_count'            => null,
                    'cancels_count'          => null,
                    'patron_barcode'         => $failure['patron_barcode'] ?? null,
                    'ingested_email_id'      => $ingestedEmail->id,
                ]);
            }
        }
    }

    /**
     * Number of records a parsed report contributes (stored as parsed_record_count).
     */
    protected function countParsedRecords(?array $parsed): int
    {
        if (!$parsed) {
            return 0;
        }

        if (isset($parsed['total_sent'], $parsed['total_failed'])) {
            // Polaris Summary Report format
            return (int) ($parsed['total_sent'] ?? 0) + (int) ($parsed['total_failed'] ?? 0);
        }

        if (isset($parsed['mismatches_count'])) {
            return (int) $parsed['mismatches_count'];
        }

        if (isset($parsed['invalid_count']) || isset($parsed['opted_out_count'])) {
            return (int) ($parsed['invalid_count'] ?? 0) + (int) ($parsed['opted_out_count'] ?? 0);
        }

        return (int) ($parsed['failures_count'] ?? $parsed['errors_count'] ?? 0);
    }

    /**
     * Classify a report by its subject and return [report_type, parser_instance] or null.
     */
    protected function classifyReportBySubject(string $subject): ?array
    {
        $normalized = strtolower($subject);

        if (preg_match('/voice notices? that (?:were|was) not delivered/', $normalized)) {
            return ['shoutbomb_voice_failure', new ShoutbombDailyVoiceFailureParser()];
        }

        if (str_contains($normalized, 'invalid patron phone number')) {
            return ['shoutbomb_invalid_phone', new ShoutbombDailyInvalidPhoneParser()];
        }

        if (preg_match('/calls? that generated errors?/', $normalized)) {
            return ['shoutbomb_voice_system_error', new ShoutbombVoiceSystemErrorParser()];
        }

        if (str_contains($normalized, 'mismatched patron preference')) {
            return ['shoutbomb_mismatched_preference', new ShoutbombMismatchedPreferenceParser()];
        }

        if (str_contains($normalized, 'summary report')) {
            return ['polaris_summary_report', new PolarisSummaryReportParser()];
        }

        // Notice files status emails (not parsed, just stored)
        if (str_contains($normalized, 'notice files')) {
            return null; // No parser needed
        }

        return null;
    }

    /**
     * Get delivery option ID from method string using DeliveryOption model.
     * Only returns IDs for enabled delivery options.
     */
    protected function getDeliveryOptionIdFromMethod(string $method): ?int
    {
        $deliveryOptionId = DeliveryOption::getIdFromMethod($method);

        // If method not found or disabled, default to Email (2) if enabled
        if (!$deliveryOptionId) {
            $emailOption = DeliveryOption::find(2);
            if ($emailOption && $emailOption->isEnabled()) {
                return 2;
            }

            // Fallback: return first enabled option we can find
            $enabledIds = DeliveryOption::getEnabledIds();

            return !empty($enabledIds) ? $enabledIds[0] : 2;
        }

        return $deliveryOptionId;
    }

    /**
     * Get notification type ID from description string.
     *
     * Matches the description from Polaris Summary Reports (e.g., "Hold", "1st Overdue")
     * to the notification_types lookup table.
     *
     * @param null|string $description Notification type description from parser
     *
     * @return null|int Notification type ID or null if not found
     */
    protected function getNotificationTypeIdFromDescription(?string $description): ?int
    {
        if (null === $description || '' === trim($description)) {
            return null;
        }

        $type = NotificationType::where('description', $description)->first();

        return $type?->id;
    }

    /**
     * Convert HTML email content to plain text.
     *
     * @param string $html HTML content
     *
     * @return string Plain text content
     */
    protected function convertHtmlToText(string $html): string
    {
        // Remove script and style tags with their content
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Convert line breaks and paragraphs to newlines
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/div>/i', "\n", $html);
        $html = preg_replace('/<\/tr>/i', "\n", $html);
        $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html);

        // Strip remaining HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces to single space
        $text = preg_replace('/\n\s+/', "\n", $text);  // Remove leading spaces on lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // Max 2 consecutive newlines

        return trim($text);
    }
}
