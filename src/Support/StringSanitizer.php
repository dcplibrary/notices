<?php

namespace Dcplibrary\Notices\Support;

/**
 * String sanitization helper for dealing with potentially invalid/hostile
 * encodings when importing raw text from export files.
 *
 * This is a defensive layer for environments where MySQL is not fully
 * configured for utf8mb4. The preferred solution is still to run the
 * database and connection as utf8mb4, but this helper gives us a
 * package-level safety net.
 */
class StringSanitizer
{
    /**
     * Normalize a string to valid UTF-8 using the same strategy as the
     * legacy NotificationReconciler::sanitizeUtf8 helper:
     *
     * - if already valid UTF-8, return as-is
     * - otherwise, try converting from Windows-1252 to UTF-8
     * - if that still fails, fall back to best-effort UTF-8 conversion
     */
    public static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        // Already valid UTF-8?
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Try to convert from common Polaris encodings to UTF-8
        // Windows-1252 is most common for diacritics like "Pokémon".
        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        // Fallback: replace invalid UTF-8 sequences with replacement chars
        $fallback = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return is_string($fallback) ? $fallback : null;
    }

    /**
     * Aggressively transliterate to ASCII, stripping diacritics and
     * any remaining non-ASCII characters. This should only be used
     * when MySQL cannot be configured for utf8mb4.
     */
    public static function ascii(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        // Normalize to UTF-8 first so iconv behaves consistently.
        $value = self::normalize($value) ?? '';

        // Normalize common punctuation before transliteration so we don't end up
        // with "?" placeholders on some iconv implementations.
        $value = str_replace(
            ["\u{2013}", "\u{2014}", "\u{2212}"], // en dash, em dash, minus sign
            '-',
            $value
        );

        // Use ICU transliteration when available (more consistent across platforms),
        // otherwise fall back to iconv().
        if (class_exists(\Transliterator::class)) {
            /** @var null|string $ascii */
            $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        } else {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        }

        if (false === $ascii) {
            return null;
        }

        // Some transliteration libraries emit apostrophes to represent diacritics
        // (e.g., Pok'emon). For our "aggressive ASCII" mode, strip them.
        $ascii = str_replace(["'", '?'], '', $ascii);

        // Strip any remaining non-printable / non-ASCII bytes.
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $ascii) ?? '';
        $ascii = preg_replace('/\s+/', ' ', $ascii)         ?? '';

        return trim($ascii);
    }

    /**
     * Primary entry point: always return a valid UTF-8 string using the
     * normalize() logic. This mirrors the old sanitizeUtf8 behavior.
     */
    public static function sanitize(?string $value): ?string
    {
        return self::normalize($value);
    }
}
