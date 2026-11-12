<?php

namespace Dcplibrary\Notices\Services\EmailReportParsers;

use Carbon\Carbon;

class ShoutbombDailyInvalidPhoneParser
{
    /**
     * Parse "Invalid patron phone number ..." emails.
     *
     * The body contains two logical sections:
     * - an "opted-out" section
     * - an "invalid" section
     *
     * Each data line is double-colon separated:
     *   Phone :: Barcode :: PatronID :: OrganizationID :: DeliveryOption
     */
    public function parse(string $subject, string $body): array
    {
        // CRITICAL: The date in the email subject is when the email was SENT (this morning)
        // But it's reporting invalid phones from exports sent YESTERDAY
        // So we subtract 1 day to get the actual export date (notice_date)
        $emailSentDate = $this->extractDateFromSubject($subject);
        $exportDate    = $emailSentDate ? $emailSentDate->copy()->subDay() : null;

        $result = [
            'report_type' => 'shoutbomb_invalid_phone',
            'report_date' => $exportDate,
            'opted_out'   => [],
            'invalid'     => [],
        ];

        $sections = $this->splitIntoSections($body);

        $result['opted_out'] = $this->parsePhoneSection($sections['opted_out'] ?? '');
        $result['invalid']   = $this->parsePhoneSection($sections['invalid'] ?? '');

        $result['opted_out_count'] = count($result['opted_out']);
        $result['invalid_count']   = count($result['invalid']);

        return $result;
    }

    protected function parsePhoneSection(string $section): array
    {
        $entries = [];

        if ('' === $section) {
            return $entries;
        }

        $lines = preg_split("/(\r\n|\n|\r)/", $section);

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

            $parts = array_map('trim', explode('::', $trimmed));

            if (count($parts) < 2) {
                continue;
            }

            $phone          = $parts[0];
            $barcode        = $parts[1] ?? null;
            $patronId       = $parts[2] ?? null;
            $organizationId = $parts[3] ?? null;
            $deliveryOption = $parts[4] ?? null;

            $isNoBarcode = 0 === strcasecmp((string) $barcode, 'No associated barcode');

            $entries[] = [
                'raw'             => $trimmed,
                'phone'           => $phone,
                'barcode'         => $isNoBarcode ? null : $barcode,
                'patron_id'       => ctype_digit((string) $patronId) ? (int) $patronId : null,
                'organization_id' => ctype_digit((string) $organizationId) ? (int) $organizationId : null,
                'delivery_option' => $deliveryOption,
                'no_barcode'      => $isNoBarcode,
            ];
        }

        return $entries;
    }

    protected function splitIntoSections(string $body): array
    {
        $sections = [
            'opted_out' => '',
            'invalid'   => '',
        ];

        if ('' === $body) {
            return $sections;
        }

        $lower = strtolower($body);

        $optedOutPos = strpos($lower, 'seem to have opted-out');
        $invalidPos  = strpos($lower, 'seem to be invalid');

        if (false === $optedOutPos && false === $invalidPos) {
            $sections['invalid'] = $body;

            return $sections;
        }

        if (false !== $optedOutPos && false !== $invalidPos && $optedOutPos > $invalidPos) {
            [$optedOutPos, $invalidPos] = [$invalidPos, $optedOutPos];
        }

        if (false !== $optedOutPos) {
            $optedOutEnd           = false !== $invalidPos ? $invalidPos : strlen($body);
            $sections['opted_out'] = substr($body, $optedOutPos, $optedOutEnd - $optedOutPos);
        }

        if (false !== $invalidPos) {
            $sections['invalid'] = substr($body, $invalidPos);
        }

        return $sections;
    }

    protected function extractDateFromSubject(string $subject): ?Carbon
    {
        return ShoutbombSubjectDate::extract($subject, 'invalid patron phone numbers?');
    }
}
