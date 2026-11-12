<?php

namespace Dcplibrary\Notices\Support;

use Dcplibrary\Notices\Models\PatronDeliveryIssue;

/**
 * Helper class for displaying issue information in a user-friendly format.
 *
 * Provides methods to extract and format data from PatronDeliveryIssue models
 * for display in lists and details views.
 */
class IssueDisplayHelper
{
    /**
     * Get a user-friendly description for an issue.
     *
     * Converts technical issue types into readable descriptions:
     * - failed_delivery_string: Shows the actual failed contact (email/phone/address)
     * - lx_starter_failure: "Not delivered to patron"
     * - null_preference: "No delivery preference set"
     * - manual_review: "Requires manual review"
     * - system_error: "System processing error"
     *
     * @param PatronDeliveryIssue $issue The issue to get description for
     *
     * @return string User-friendly issue description
     */
    public static function getIssueDescription(PatronDeliveryIssue $issue): string
    {
        return match ($issue->issue_type) {
            'failed_delivery_string' => $issue->failedDeliveryString
                ? "Failed contact: {$issue->failedDeliveryString->delivery_string}"
                : 'Invalid contact information',
            'lx_starter_failure' => 'Not delivered to patron',
            // User-facing label: keep internal issue_type but rename display.
            'polaris_export_failure' => 'LX Starter failure',
            'null_preference'        => 'No delivery preference set',
            'mismatched_preference'  => self::getMismatchedPreferenceLabel($issue),
            'manual_review'          => 'Requires manual review',
            'system_error'           => 'System processing error',
            default                  => $issue->issue_details ?? str_replace('_', ' ', ucwords($issue->issue_type))
        };
    }

    /**
     * Extract patron information from notification payload.
     *
     * Retrieves patron first name and last name from the notification's raw_payload JSON.
     * The payload is expected to be an array with patron_first_name and patron_last_name fields.
     *
     * @param PatronDeliveryIssue $issue The issue with associated notification
     *
     * @return array{first_name: string, last_name: string} Patron name components
     */
    public static function getPatronName(PatronDeliveryIssue $issue): array
    {
        if (!$issue->notification) {
            return ['first_name' => '', 'last_name' => ''];
        }

        $payload = json_decode($issue->notification->raw_payload, true);

        return [
            'first_name' => $payload[0]['patron_first_name'] ?? '',
            'last_name'  => $payload[0]['patron_last_name']  ?? '',
        ];
    }

    /**
     * Extract item information from notification payload.
     *
     * Retrieves item title and barcode from the notification's raw_payload JSON.
     * The payload is expected to be an array with title and item_barcode fields.
     *
     * @param PatronDeliveryIssue $issue The issue with associated notification
     *
     * @return array{title: string, barcode: string} Item information
     */
    public static function getItemInfo(PatronDeliveryIssue $issue): array
    {
        if (!$issue->notification) {
            return ['title' => '', 'barcode' => ''];
        }

        $payload = json_decode($issue->notification->raw_payload, true);

        return [
            'title'   => $payload[0]['title']        ?? '',
            'barcode' => $payload[0]['item_barcode'] ?? '',
        ];
    }

    /**
     * Get formatted patron name as "Last, First".
     *
     * @param PatronDeliveryIssue $issue The issue with associated notification
     *
     * @return string Formatted patron name
     */
    public static function getFormattedPatronName(PatronDeliveryIssue $issue): string
    {
        $name = self::getPatronName($issue);

        if (empty($name['last_name']) && empty($name['first_name'])) {
            return '';
        }

        return trim("{$name['last_name']}, {$name['first_name']}");
    }

    /**
     * Extract phone number from issue details text.
     *
     * Looks for 10-digit phone numbers in issue_details text.
     * Pattern: "phone 1234567890" or similar.
     *
     * @param null|string $issueDetails The issue details text to search
     *
     * @return null|string The extracted phone number, or null if not found
     */
    public static function extractPhoneFromDetails(?string $issueDetails): ?string
    {
        if (empty($issueDetails)) {
            return null;
        }

        if (preg_match('/phone (\d{10})/', $issueDetails, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get label for mismatched preference issues.
     *
     * Uses issue_details which contains the full description, or falls back
     * to a generic message with new barcode info if available.
     */
    protected static function getMismatchedPreferenceLabel(PatronDeliveryIssue $issue): string
    {
        // Use issue_details which contains the full description
        if ($issue->issue_details) {
            return $issue->issue_details;
        }

        // Fallback
        $meta = json_decode($issue->meta ?? '{}', true);
        if (!empty($meta['new_barcodes'])) {
            return sprintf(
                'Mismatched preference: Update barcode %s to match newer barcode(s) %s',
                $issue->patron_barcode,
                implode(', ', $meta['new_barcodes'])
            );
        }

        return 'Mismatched patron preference';
    }
}
