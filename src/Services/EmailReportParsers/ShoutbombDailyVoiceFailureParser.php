<?php

namespace Dcplibrary\Notices\Services\EmailReportParsers;

use Carbon\Carbon;

class ShoutbombDailyVoiceFailureParser
{
    /**
     * Parse "Voice notices that were not delivered ..." emails.
     *
     * Expected line format in the body (pipe-delimited with inconsistent spacing):
     *   Phone | Barcode | Library Name | Patron Name | Notification Type
     */
    public function parse(string $subject, string $body): array
    {
        // CRITICAL: The date in the email subject is when the email was SENT (this morning)
        // But it's reporting failures from exports sent YESTERDAY
        // So we subtract 1 day to get the actual export date (notice_date)
        $emailSentDate = $this->extractDateFromSubject($subject);
        $exportDate    = $emailSentDate ? $emailSentDate->copy()->subDay() : null;

        $result = [
            'report_type' => 'shoutbomb_voice_failure',
            'report_date' => $exportDate,
            'failures'    => [],
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

            if (0 === stripos($trimmed, 'subject:')) {
                continue;
            }

            if (false === strpos($trimmed, '|')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $trimmed));

            if (count($parts) < 5) {
                continue;
            }

            $result['failures'][] = [
                'raw'               => $trimmed,
                'phone'             => $parts[0],
                'barcode'           => $parts[1],
                'library_name'      => $parts[2],
                'patron_name'       => $parts[3],
                'notification_type' => $parts[4],
                'parsed_at'         => Carbon::now(),
            ];
        }

        $result['failures_count'] = count($result['failures']);

        return $result;
    }

    protected function extractDateFromSubject(string $subject): ?Carbon
    {
        return ShoutbombSubjectDate::extract($subject, 'voice notices? that (?:were|was) not delivered');
    }
}
