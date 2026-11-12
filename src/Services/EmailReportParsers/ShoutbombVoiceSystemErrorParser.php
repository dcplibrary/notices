<?php

namespace Dcplibrary\Notices\Services\EmailReportParsers;

use Carbon\Carbon;

class ShoutbombVoiceSystemErrorParser
{
    /**
     * Parse "Calls that generated errors ..." emails.
     *
     * Current format (since 2026-08):
     *   Patron number:: {Phone} :: Error reason :: {Message} :: Library number {LibPhone} :: {YYYY-MM-DD} :: {HH:MM:SS} ::
     *
     * Legacy format:
     *   BANDWIDTH :: {CallID} :: Patron number {Phone} :: Home Library {Org} :: error :: disconnect ::: Service unavailable ::: {TxnID} :: Library number {LibPhone} :: {YYYY-MM-DD} :: {HH:MM:SS} ::
     */
    public function parse(string $subject, string $body): array
    {
        // CRITICAL: The date in the email subject is when the email was SENT (this morning)
        // But it's reporting errors from exports sent YESTERDAY
        // So we subtract 1 day to get the actual export date (notice_date)
        $emailSentDate = $this->extractDateFromSubject($subject);
        $exportDate    = $emailSentDate ? $emailSentDate->copy()->subDay() : null;

        $result = [
            'report_type'      => 'shoutbomb_voice_system_error',
            'report_date'      => $exportDate,
            'errors'           => [],
            'grouped_by_phone' => [],
        ];

        if ('' === $body) {
            return $result;
        }

        $lines = preg_split("/(\r\n|\n|\r)/", $body);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ('' === $trimmed) {
                continue;
            }

            if (0 === stripos($trimmed, 'hello')) {
                continue;
            }

            if (false === strpos($trimmed, '::')) {
                continue;
            }

            $current = $this->parseCurrentFormatLine($trimmed);
            if (null !== $current) {
                $result['errors'][] = $current;

                continue;
            }

            $parts = array_map('trim', preg_split('/::+/', $trimmed));

            if (count($parts) < 11) {
                continue;
            }

            $provider           = $parts[0];
            $callId             = $parts[1];
            $patronNumberField  = $parts[2];
            $homeLibraryField   = $parts[3];
            $errorType          = $parts[4];
            $disconnectType     = $parts[5];
            $errorMessageField  = $parts[6]  ?? '';
            $transactionId      = $parts[7]  ?? null;
            $libraryNumberField = $parts[8]  ?? '';
            $errorDate          = $parts[9]  ?? null;
            $errorTime          = $parts[10] ?? null;

            $patronPhone  = preg_replace('/^Patron number\s*/i', '', $patronNumberField);
            $libraryPhone = preg_replace('/^Library number\s*/i', '', $libraryNumberField);
            $homeLibrary  = preg_replace('/^Home Library\s*/i', '', $homeLibraryField);
            $errorMessage = trim($errorMessageField, ' :');

            $error = [
                'raw'             => $trimmed,
                'provider'        => $provider,
                'call_id'         => $callId,
                'patron_phone'    => $patronPhone,
                'home_library'    => $homeLibrary,
                'error_type'      => $errorType,
                'disconnect_type' => $disconnectType,
                'error_message'   => $errorMessage,
                'transaction_id'  => $transactionId,
                'library_phone'   => $libraryPhone,
                'error_date'      => $errorDate,
                'error_time'      => $errorTime,
                'parsed_at'       => Carbon::now(),
            ];

            $result['errors'][] = $error;
        }

        $grouped = [];
        foreach ($result['errors'] as $error) {
            $phone             = $error['patron_phone'] ?? '_missing_';
            $grouped[$phone][] = $error;
        }

        $result['grouped_by_phone'] = $grouped;
        $result['errors_count']     = count($result['errors']);

        return $result;
    }

    /**
     * Parse the shorter line Shoutbomb started sending in August 2026.
     *
     * Patron number:: {Phone} :: Error reason :: {Message} :: Library number {LibPhone} :: {YYYY-MM-DD} :: {HH:MM:SS} ::
     */
    protected function parseCurrentFormatLine(string $line): ?array
    {
        if (!preg_match(
            '/^Patron number\s*::\s*(.*?)\s*::\s*Error reason\s*::\s*(.*?)\s*::\s*Library number\s*(.*?)\s*::\s*(\d{4}-\d{2}-\d{2})\s*::\s*(\d{2}:\d{2}:\d{2})\s*::/i',
            $line,
            $matches
        )) {
            return null;
        }

        return [
            'raw'             => $line,
            'provider'        => null,
            'call_id'         => null,
            'patron_phone'    => trim($matches[1]),
            'home_library'    => null,
            'error_type'      => 'Error reason',
            'disconnect_type' => null,
            'error_message'   => trim($matches[2]),
            'transaction_id'  => null,
            'library_phone'   => trim($matches[3]),
            'error_date'      => $matches[4],
            'error_time'      => $matches[5],
            'parsed_at'       => Carbon::now(),
        ];
    }

    protected function extractDateFromSubject(string $subject): ?Carbon
    {
        return ShoutbombSubjectDate::extract($subject, 'calls? that generated errors?');
    }
}
