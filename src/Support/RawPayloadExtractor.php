<?php

namespace Dcplibrary\Notices\Support;

/**
 * Utility for extracting data from notification raw_payload JSON.
 *
 * Polaris notifications can contain multiple items in a single notification
 * (e.g., a patron with 3 holds ready would have 3 rows in the raw_payload).
 * This class provides safe, consistent methods for extracting data from
 * the raw_payload JSON structure.
 *
 * ## Raw Payload Structure
 *
 * The raw_payload field contains a JSON array of objects, each representing
 * one item in the notification:
 *
 * ```json
 * [
 *   {
 *     "patron_last_name": "Smith",
 *     "patron_first_name": "John",
 *     "title": "The Great Gatsby",
 *     "item_barcode": "31234567890123",
 *     "source_filename": "holds_2025-01-01.txt"
 *   },
 *   {
 *     "patron_last_name": "Smith",
 *     "patron_first_name": "John",
 *     "title": "To Kill a Mockingbird",
 *     "item_barcode": "31234567890456",
 *     "source_filename": "holds_2025-01-01.txt"
 *   }
 * ]
 * ```
 *
 * ## Purpose
 *
 * Before this refactoring, raw_payload extraction logic was duplicated
 * in multiple controller methods using inline closures:
 * - NotificationController::show()
 * - NotificationController::showIssue()
 *
 * This class provides a single source of truth for payload parsing.
 *
 * ## Usage Example
 *
 * ```php
 * $rawRows = RawPayloadExtractor::parsePayload($notification->raw_payload);
 * $patronName = RawPayloadExtractor::getPatronName($rawRows);
 * $itemTitle = RawPayloadExtractor::getItemTitle($rawRows);
 * ```
 *
 * @author DCPL Development Team
 *
 * @since 1.0.0
 * @see \Dcplibrary\Notices\Http\Controllers\NotificationController
 */
class RawPayloadExtractor
{
    /**
     * Pick the first non-empty value for a key from an array of rows.
     *
     * Polaris notifications can have multiple items in one notification
     * (e.g., a combined hold notification for 3 items). Patron information
     * is typically the same across all rows, but item information differs.
     *
     * This method iterates through rows and returns the first non-null,
     * non-empty value found for the specified key. This is useful for
     * extracting patron information or getting the first item's details.
     *
     * @param array  $rows Array of rows from raw_payload JSON (decoded)
     * @param string $key  Field name to extract (e.g., 'patron_last_name', 'title')
     *
     * @return null|string First non-empty value, or null if not found
     *
     * @example
     * ```php
     * $rows = [
     *   ['patron_last_name' => '', 'title' => 'Book 1'],
     *   ['patron_last_name' => 'Smith', 'title' => 'Book 2']
     * ];
     *
     * RawPayloadExtractor::pickFirstNonEmpty($rows, 'patron_last_name'); // "Smith"
     * RawPayloadExtractor::pickFirstNonEmpty($rows, 'title'); // "Book 1"
     * ```
     */
    public static function pickFirstNonEmpty(array $rows, string $key): ?string
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $value = $row[$key] ?? null;

            if (null !== $value && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Extract patron full name from raw payload.
     *
     * Extracts and formats the patron's name in "Last, First" format
     * commonly used in library systems.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     *
     * @return null|string Formatted name like "Smith, John" or null if no name found
     *
     * @example
     * ```php
     * $rows = [['patron_last_name' => 'Smith', 'patron_first_name' => 'John']];
     * RawPayloadExtractor::getPatronName($rows); // "Smith, John"
     * ```
     * @example
     * ```php
     * // Handles missing first or last name gracefully
     * $rows = [['patron_last_name' => 'Smith', 'patron_first_name' => '']];
     * RawPayloadExtractor::getPatronName($rows); // "Smith"
     * ```
     */
    public static function getPatronName(array $rows): ?string
    {
        $patronLast  = self::pickFirstNonEmpty($rows, 'patron_last_name');
        $patronFirst = self::pickFirstNonEmpty($rows, 'patron_first_name');

        if (!$patronLast && !$patronFirst) {
            return null;
        }

        $name = trim(($patronLast ?? '') . ($patronLast && $patronFirst ? ', ' : '') . ($patronFirst ?? ''));

        return $name ?: null;
    }

    /**
     * Extract patron first name from raw payload.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     */
    public static function getPatronFirstName(array $rows): ?string
    {
        return self::pickFirstNonEmpty($rows, 'patron_first_name');
    }

    /**
     * Extract patron last name from raw payload.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     */
    public static function getPatronLastName(array $rows): ?string
    {
        return self::pickFirstNonEmpty($rows, 'patron_last_name');
    }

    /**
     * Extract item title from raw payload.
     *
     * Gets the title of the first item in a notification. For combined
     * notifications (multiple items), this returns only the first title.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     *
     * @return null|string Item title or null if not found
     *
     * @example
     * ```php
     * $rows = [
     *   ['title' => 'The Great Gatsby'],
     *   ['title' => 'To Kill a Mockingbird']
     * ];
     * RawPayloadExtractor::getItemTitle($rows); // "The Great Gatsby"
     * ```
     */
    public static function getItemTitle(array $rows): ?string
    {
        return self::pickFirstNonEmpty($rows, 'title');
    }

    /**
     * Extract item barcode from raw payload.
     *
     * Gets the barcode of the first item in a notification. For combined
     * notifications (multiple items), this returns only the first barcode.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     *
     * @return null|string Item barcode or null if not found
     *
     * @example
     * ```php
     * $rows = [['item_barcode' => '31234567890123']];
     * RawPayloadExtractor::getItemBarcode($rows); // "31234567890123"
     * ```
     */
    public static function getItemBarcode(array $rows): ?string
    {
        return self::pickFirstNonEmpty($rows, 'item_barcode');
    }

    /**
     * Extract source filename from raw payload.
     *
     * Gets the Polaris export filename that this notification came from.
     * Useful for debugging and tracing notification origins.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     *
     * @return null|string Source filename like "holds_2025-01-01.txt" or null
     *
     * @example
     * ```php
     * $rows = [['source_filename' => 'holds_2025-01-01.txt']];
     * RawPayloadExtractor::getSourceFilename($rows); // "holds_2025-01-01.txt"
     * ```
     */
    public static function getSourceFilename(array $rows): ?string
    {
        return self::pickFirstNonEmpty($rows, 'source_filename');
    }

    /**
     * Extract patron phone number from raw payload.
     *
     * Gets the phone number for voice/SMS delivery from the raw payload.
     * Checks patron_phone first, then delivery_string if it looks like a phone number.
     * Used to populate indexed_patron_phone for fast lookups.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     *
     * @return null|string Patron phone number or null if not found
     *
     * @example
     * ```php
     * $rows = [['patron_phone' => '5555818536']];
     * RawPayloadExtractor::getPatronPhone($rows); // "5555818536"
     * ```
     */
    public static function getPatronPhone(array $rows): ?string
    {
        // Try patron_phone first
        $phone = self::pickFirstNonEmpty($rows, 'patron_phone');
        if ($phone) {
            return $phone;
        }

        // Fallback: check delivery_string if it looks like a phone number
        $deliveryString = self::pickFirstNonEmpty($rows, 'delivery_string');
        if ($deliveryString && self::looksLikePhoneNumber($deliveryString)) {
            return $deliveryString;
        }

        return null;
    }

    /**
     * Extract patron email address from raw payload.
     *
     * Gets the email address for email delivery from the raw payload.
     * Checks patron_email first, then delivery_string if it looks like an email.
     * Used to populate indexed_patron_email for fast lookups.
     *
     * @param array $rows Raw payload rows (decoded JSON array)
     *
     * @return null|string Patron email address or null if not found
     *
     * @example
     * ```php
     * $rows = [['patron_email' => 'patron@example.com']];
     * RawPayloadExtractor::getPatronEmail($rows); // "patron@example.com"
     * ```
     */
    public static function getPatronEmail(array $rows): ?string
    {
        // Try patron_email first
        $email = self::pickFirstNonEmpty($rows, 'patron_email');
        if ($email) {
            return $email;
        }

        // Fallback: check delivery_string if it looks like an email
        $deliveryString = self::pickFirstNonEmpty($rows, 'delivery_string');
        if ($deliveryString && self::looksLikeEmail($deliveryString)) {
            return $deliveryString;
        }

        return null;
    }

    /**
     * Parse raw_payload JSON string to array.
     *
     * Safely decodes the JSON string stored in the notification's raw_payload
     * field. Handles null/empty values and invalid JSON gracefully.
     *
     * @param null|string $rawPayload JSON string from notification's raw_payload field
     *
     * @return array Array of rows (each row is an associative array), or empty array if invalid/null
     *
     * @example
     * ```php
     * $json = '[{"patron_last_name":"Smith","title":"Book 1"}]';
     * $rows = RawPayloadExtractor::parsePayload($json);
     * // [['patron_last_name' => 'Smith', 'title' => 'Book 1']]
     * ```
     * @example
     * ```php
     * // Handles null/invalid JSON safely
     * RawPayloadExtractor::parsePayload(null);     // []
     * RawPayloadExtractor::parsePayload('');       // []
     * RawPayloadExtractor::parsePayload('invalid'); // []
     * ```
     */
    public static function parsePayload(?string $rawPayload): array
    {
        if (!$rawPayload) {
            return [];
        }

        $decoded = json_decode($rawPayload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check if a string looks like an email address.
     */
    protected static function looksLikeEmail(?string $value): bool
    {
        if (!$value) {
            return false;
        }

        return str_contains($value, '@') && str_contains($value, '.');
    }

    /**
     * Check if a string looks like a phone number.
     * Phone numbers are digit-only or have common formatting like (xxx) xxx-xxxx.
     */
    protected static function looksLikePhoneNumber(?string $value): bool
    {
        if (!$value) {
            return false;
        }

        // Remove common phone formatting characters
        $digitsOnly = preg_replace('/[^0-9]/', '', $value);

        // Valid phone numbers typically have 10-15 digits
        $length = strlen($digitsOnly);

        return $length >= 10 && $length <= 15;
    }
}
