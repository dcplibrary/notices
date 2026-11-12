<?php

namespace Dcplibrary\Notices\Services\EmailReportParsers;

use Carbon\Carbon;

/**
 * Extracts the sent date from a Shoutbomb report subject.
 *
 * Shoutbomb subjects are "<report phrase> [on] <date>", e.g.
 * "Voice notices that were not delivered on Mon, October 26th 2026" or
 * "Calls that generated errors Tue, October 27th 2026". The wording around the
 * date has drifted over time ("on" added/removed, forwarding prefixes, trailing
 * library names), and a missed date silently nulls report_date/notice_date,
 * which hides every failure in that email from reconciliation.
 */
class ShoutbombSubjectDate
{
    private const MONTH_DATE = '/(?:(?:mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?,?\s+)?'
        . '((?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4})/i';

    /**
     * @param string $phrasePattern regex fragment (no delimiters) for the report phrase
     */
    public static function extract(string $subject, string $phrasePattern): ?Carbon
    {
        if ('' === trim($subject)) {
            return null;
        }

        $normalized = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $subject);

        if (!preg_match('/' . $phrasePattern . '(.*)$/i', $normalized, $matches)) {
            return null;
        }

        $remainder = trim(preg_replace('/^\s*on\b/i', '', end($matches)));

        if (preg_match(self::MONTH_DATE, $remainder, $dateMatch)) {
            return self::parse($dateMatch[1]);
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4})\b/', $remainder, $dateMatch)) {
            return self::parse($dateMatch[1]);
        }

        return '' === $remainder ? null : self::parse($remainder);
    }

    private static function parse(string $value): ?Carbon
    {
        try {
            return Carbon::parse(str_replace(',', '', $value))->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
