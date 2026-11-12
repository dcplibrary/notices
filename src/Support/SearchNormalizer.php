<?php

namespace Dcplibrary\Notices\Support;

/**
 * Normalizes text values for search indexing and query matching.
 *
 * Both stored search columns and user search queries should be normalized
 * using the same methods to ensure consistent matching.
 *
 * ## Normalization Rules
 *
 * - **Names**: Lowercase, strip punctuation (apostrophes, hyphens, etc.)
 *   - `O'Brien` → `obrien`
 *   - `Mary-Jane` → `maryjane`
 *
 * - **Titles**: Lowercase, preserve diacritics and apostrophes
 *   - `Pokémon` → `pokémon`
 *   - `The Cat's Meow` → `the cat's meow`
 *
 * - **Phone numbers**: Digits only
 *   - `(555) 581-8536` → `5555818536`
 *
 * - **Email**: Lowercase only
 *   - `John.Doe@Example.COM` → `john.doe@example.com`
 *
 * - **Barcodes**: Lowercase (typically already alphanumeric)
 *
 * @see \Dcplibrary\Notices\Services\NotificationReconciler::buildNotificationSearchColumns()
 * @see \Dcplibrary\Notices\Services\NotificationQueryService::applyFilters()
 */
class SearchNormalizer
{
    /**
     * Normalize a name for search indexing/matching.
     *
     * Lowercases and strips punctuation (apostrophes, hyphens, periods, etc.)
     * so that "O'Brien", "OBrien", and "o brien" all match.
     *
     * @param null|string $value Raw name value
     * @param int         $max   Maximum length to return
     *
     * @return null|string Normalized name or null if empty
     */
    public static function normalizeName(?string $value, int $max = 100): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        // Lowercase for case-insensitive search
        $value = mb_strtolower($value, 'UTF-8');

        // Strip common name punctuation: apostrophes, hyphens, periods, underscores
        // Keep spaces and letters (including diacritics)
        $value = preg_replace("/[''`\\-\\._]/u", '', $value);

        // Collapse multiple spaces
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * Normalize a title for search indexing/matching.
     *
     * Lowercases but preserves punctuation and diacritics.
     *
     * @param null|string $value Raw title value
     * @param int         $max   Maximum length to return
     *
     * @return null|string Normalized title or null if empty
     */
    public static function normalizeTitle(?string $value, int $max = 191): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        // Lowercase for case-insensitive search
        $value = mb_strtolower($value, 'UTF-8');

        return mb_substr($value, 0, $max);
    }

    /**
     * Normalize a phone number for search indexing/matching.
     *
     * Strips all non-numeric characters.
     *
     * @param null|string $value Raw phone number
     * @param int         $max   Maximum length to return
     *
     * @return null|string Digits only or null if empty
     */
    public static function normalizePhone(?string $value, int $max = 20): ?string
    {
        if (null === $value) {
            return null;
        }

        // Remove everything except digits
        $value = preg_replace('/[^0-9]/', '', $value);

        if ('' === $value) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * Normalize an email address for search indexing/matching.
     *
     * Lowercases the entire email.
     *
     * @param null|string $value Raw email address
     * @param int         $max   Maximum length to return
     *
     * @return null|string Normalized email or null if empty
     */
    public static function normalizeEmail(?string $value, int $max = 255): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $value = mb_strtolower($value, 'UTF-8');

        return mb_substr($value, 0, $max);
    }

    /**
     * Normalize a barcode for search indexing/matching.
     *
     * Lowercases (for any alpha characters).
     *
     * @param null|string $value Raw barcode
     * @param int         $max   Maximum length to return
     *
     * @return null|string Normalized barcode or null if empty
     */
    public static function normalizeBarcode(?string $value, int $max = 50): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $value = mb_strtolower($value, 'UTF-8');

        return mb_substr($value, 0, $max);
    }

    /**
     * Normalize a general search term for query matching.
     *
     * This applies the most aggressive normalization (same as names)
     * to maximize match potential when the search field is unknown.
     *
     * @param null|string $value Raw search term
     *
     * @return null|string Normalized search term or null if empty
     */
    public static function normalizeSearchTerm(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        // Lowercase
        $value = mb_strtolower($value, 'UTF-8');

        // Strip punctuation for broader matching
        $value = preg_replace("/[''`\\-\\.]/u", '', $value);

        // Collapse multiple spaces
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value) ?: null;
    }
}
