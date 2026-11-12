<?php

namespace Dcplibrary\Notices\Services\EmailReportParsers;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DeliveryOption;

class ShoutbombMismatchedPreferenceParser
{
    /**
     * Parse "Mismatched patron preference, please switch to text ..." emails.
     *
     * Format: Tab-separated table
     *   Home Library | Patron Phone | Registration failed because cards are set for {preference} notice preference and not for {other} for this phone number | These cards are already registered for {other} with this phone number
     *
     * Example:
     *   3 | 5555666612 | 20000935838013 | 20000973630843
     *
     * Interpretation:
     * - Column 3: NEW barcodes (latest preference) - DeliveryOptionID 3 (Voice) in this example
     * - Column 4: OLD barcodes (older preference) - DeliveryOptionID 8 (Text) in this example
     * - Action: Update OLD barcodes to match NEW barcodes' preference
     */
    public function parse(string $subject, string $body): array
    {
        // CRITICAL: The date in the email subject is when the email was SENT (this morning)
        // But it's reporting mismatches from exports sent YESTERDAY
        // So we subtract 1 day to get the actual export date (notice_date)
        $emailSentDate = $this->extractDateFromSubject($subject);
        $exportDate    = $emailSentDate ? $emailSentDate->copy()->subDay() : null;

        $result = [
            'report_type' => 'shoutbomb_mismatched_preference',
            'report_date' => $exportDate,
            'mismatches'  => [],
        ];

        if ('' === $body) {
            return $result;
        }

        $lines = preg_split("/(\r\n|\n|\r)/", $body);

        // Find the header row (contains "Home Library" and "Patron Phone")
        $headerIndex = null;
        $headerLine  = null;
        foreach ($lines as $index => $line) {
            if (false !== stripos($line, 'Home Library') && false !== stripos($line, 'Patron Phone')) {
                $headerIndex = $index;
                $headerLine  = $line;

                break;
            }
        }

        if (null === $headerIndex || !$headerLine) {
            return $result;
        }

        // Determine delivery options from header text
        // Column 3 header contains the NEW preference (e.g., "voice notice preference")
        // Column 4 header contains the OLD preference (e.g., "registered for text")
        // Header may be tab-separated OR concatenated without tabs
        $headerParts = preg_split('/\t+/', $headerLine);

        // If no tabs, try to extract from concatenated header
        if (count($headerParts) < 4) {
            // Header is concatenated, extract delivery options from text
            // Look for patterns like "voice notice preference" or "registered for text"
            $newDeliveryOptionId = $this->extractDeliveryOptionFromHeader($headerLine);
            $oldDeliveryOptionId = null;

            // Try to find the opposite preference
            if (3 === $newDeliveryOptionId) { // Voice
                $oldDeliveryOptionId = 8; // Text
            } elseif (8 === $newDeliveryOptionId) { // Text
                $oldDeliveryOptionId = 3; // Voice
            } else {
                // Fallback: check header text for both preferences
                $newDeliveryOptionId = $this->extractDeliveryOptionFromHeader($headerLine);
                // Look for "registered for" which indicates the old preference
                if (false !== stripos($headerLine, 'registered for text')) {
                    $oldDeliveryOptionId = 8; // Text
                    $newDeliveryOptionId = $newDeliveryOptionId ?: 3; // Voice (default)
                } elseif (false !== stripos($headerLine, 'registered for voice')) {
                    $oldDeliveryOptionId = 3; // Voice
                    $newDeliveryOptionId = $newDeliveryOptionId ?: 8; // Text (default)
                }
            }
        } else {
            $newDeliveryOptionId = $this->extractDeliveryOptionFromHeader($headerParts[2] ?? '');
            $oldDeliveryOptionId = $this->extractDeliveryOptionFromHeader($headerParts[3] ?? '');
        }

        // Parse data rows (skip header)
        for ($i = $headerIndex + 1; $i < count($lines); ++$i) {
            $line = trim($lines[$i]);

            if ('' === $line) {
                continue;
            }

            // Skip narrative text
            if (0 === stripos($line, 'hello') || 0 === stripos($line, 'the way')) {
                continue;
            }

            // Try tab-separated first
            $parts = preg_split('/\t+/', $line);

            // If no tabs or not enough parts, try space-separated
            if (count($parts) < 2) {
                $parts = preg_split('/\s{2,}/', $line);
            }

            // If still not enough parts, try single space (but be careful with concatenated format)
            if (count($parts) < 2) {
                // Check if it's a concatenated numeric string (e.g., "327030245972330701539027323307015152582")
                // Format: {home_library}{10-digit-phone}{barcode1}{barcode2}
                // Barcodes are typically 13-14 digits, so we need to find the split point
                // Strategy: After phone (10 digits), try to find two barcodes of reasonable length
                if (preg_match('/^(\d)(\d{10})(.+)$/', $line, $matches)) {
                    $homeLibrary   = $matches[1];
                    $phone         = $matches[2];
                    $barcodeString = $matches[3];

                    // Try to split barcodes - they're typically 13-14 digits each
                    // Total barcode string length should be 26-28 digits (13+13, 13+14, 14+13, or 14+14)
                    $barcodeLen = strlen($barcodeString);

                    if ($barcodeLen >= 26 && $barcodeLen <= 28) {
                        // Try 14 digits for first barcode (most common), remainder for second
                        $firstBarcode  = substr($barcodeString, 0, 14);
                        $secondBarcode = substr($barcodeString, 14);

                        // If second barcode is too short (< 13 digits), try 13 for first
                        if (strlen($secondBarcode) < 13) {
                            $firstBarcode  = substr($barcodeString, 0, 13);
                            $secondBarcode = substr($barcodeString, 13);
                        }

                        $parts = [
                            $homeLibrary,
                            $phone,
                            $firstBarcode,   // New barcode
                            $secondBarcode,  // Old barcode
                        ];
                    } else {
                        // Fallback: split roughly in half
                        $mid   = (int) ($barcodeLen / 2);
                        $parts = [
                            $homeLibrary,
                            $phone,
                            substr($barcodeString, 0, $mid),  // New barcode
                            substr($barcodeString, $mid),     // Old barcode
                        ];
                    }
                } else {
                    // Try single space split as last resort
                    $parts = preg_split('/\s+/', $line);
                }
            }

            if (count($parts) < 2) {
                continue;
            }

            $homeLibrary = trim($parts[0] ?? '');
            $patronPhone = trim($parts[1] ?? '');
            $newBarcodes = trim($parts[2] ?? ''); // Cards with NEW preference (failed registration)
            $oldBarcodes = trim($parts[3] ?? ''); // Cards with OLD preference (already registered)

            // Validate phone number format (10 digits)
            if (!preg_match('/^\d{10}$/', $patronPhone)) {
                continue;
            }

            // Parse barcode lists (space or comma separated)
            $newBarcodeList = $this->parseBarcodeList($newBarcodes);
            $oldBarcodeList = $this->parseBarcodeList($oldBarcodes);

            if (empty($newBarcodeList) || empty($oldBarcodeList)) {
                continue; // Need both to have a mismatch
            }

            $result['mismatches'][] = [
                'raw'                    => $line,
                'home_library'           => $homeLibrary,
                'patron_phone'           => $patronPhone,
                'new_barcodes'           => $newBarcodeList,  // Cards with NEW preference
                'old_barcodes'           => $oldBarcodeList,  // Cards with OLD preference (need updating)
                'new_delivery_option_id' => $newDeliveryOptionId,  // The preference to match
                'old_delivery_option_id' => $oldDeliveryOptionId,  // The preference to update
                'parsed_at'              => Carbon::now(),
            ];
        }

        $result['mismatches_count'] = count($result['mismatches']);

        return $result;
    }

    /**
     * Extract delivery option ID from header text using DeliveryOption model.
     * Only returns IDs for enabled delivery options.
     *
     * Looks for patterns like:
     * - "voice notice preference" → 3 (Voice)
     * - "registered for text" → 8 (Text/SMS)
     * - "set for voice" → 3
     * - "set for text" → 8
     */
    protected function extractDeliveryOptionFromHeader(string $headerText): ?int
    {
        $normalized = strtolower($headerText);

        // Check for specific patterns first (more specific = higher priority)
        if (false    !== stripos($normalized, 'voice notice preference')
            || false !== stripos($normalized, 'set for voice')
            || false !== stripos($normalized, 'cards are set for voice')) {
            $option = DeliveryOption::find(3);
            if ($option && $option->isEnabled()) {
                return 3;
            }
        }

        if (false    !== stripos($normalized, 'registered for text')
            || false !== stripos($normalized, 'set for text')
            || false !== stripos($normalized, 'cards are set for text')) {
            $option = DeliveryOption::find(8);
            if ($option && $option->isEnabled()) {
                return 8;
            }
        }

        // Fallback to keyword matching
        $keywordMapping = [
            'voice' => 3,
            'phone' => 3,
            'text'  => 8,
            'sms'   => 8,
            'txt'   => 8,
            'email' => 2,
            'mail'  => 1,
        ];

        foreach ($keywordMapping as $keyword => $deliveryOptionId) {
            if (str_contains($normalized, $keyword)) {
                $deliveryOption = DeliveryOption::find($deliveryOptionId);

                // Only return if exists AND is enabled
                if ($deliveryOption && $deliveryOption->isEnabled()) {
                    return $deliveryOptionId;
                }
            }
        }

        return null;
    }

    /**
     * Parse a space or comma-separated list of barcodes.
     */
    protected function parseBarcodeList(string $barcodeString): array
    {
        if ('' === trim($barcodeString)) {
            return [];
        }

        // Split by comma or whitespace
        $barcodes = preg_split('/[,\s]+/', trim($barcodeString));

        return array_filter(array_map('trim', $barcodes), function ($barcode) {
            // Only include valid-looking barcodes (numeric, reasonable length)
            return !empty($barcode) && preg_match('/^\d+$/', $barcode) && strlen($barcode) >= 10;
        });
    }

    protected function extractDateFromSubject(string $subject): ?Carbon
    {
        if ('' === $subject) {
            return null;
        }

        // Remove ordinal suffixes (1st, 2nd, 3rd, 4th etc.)
        $normalized = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $subject);

        // Match date patterns like "Sun, January 4th 2026" or "Sun, January 4 2026"
        if (preg_match('/(?:mismatched patron preference, please switch to (?:text|voice)|switch to (?:text|voice))\s+(.+)$/i', $normalized, $matches)) {
            $datePart = trim($matches[1]);

            try {
                return Carbon::parse($datePart)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
