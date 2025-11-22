<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Dcplibrary\Notices\Models\ShoutbombKeywordUsage;
use Dcplibrary\Notices\Models\ShoutbombRegistration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShoutbombFileParser
{
    /**
     * Parse a Shoutbomb monthly report file.
     */
    public function parseMonthlyReport(string $filePath): array
    {
        Log::info("Parsing monthly report: {$filePath}");

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $data = [
            'registration_stats' => null,
            'keyword_usage' => [],
            'deliveries' => [],
        ];

        // Extract report date from filename or content
        $reportDate = $this->extractDateFromFilename($filePath);

        // Parse registration statistics
        foreach ($lines as $line) {
            // Look for "Registration Statistics: 13307 text (72%), 5199 voice (28%)"
            if (preg_match('/Registration Statistics:\s*(\d+)\s*text\s*\((\d+)%\),\s*(\d+)\s*voice\s*\((\d+)%\)/', $line, $matches)) {
                $data['registration_stats'] = [
                    'snapshot_date' => $reportDate,
                    'total_text_subscribers' => (int)$matches[1],
                    'text_percentage' => (float)$matches[2],
                    'total_voice_subscribers' => (int)$matches[3],
                    'voice_percentage' => (float)$matches[4],
                    'total_subscribers' => (int)$matches[1] + (int)$matches[3],
                    'report_file' => basename($filePath),
                    'report_type' => 'Monthly',
                ];
            }

            // Parse keyword usage (e.g., "RHL (Renew holds): 1234 uses")
            if (preg_match('/^([A-Z]+)\s*\(([^)]+)\):\s*(\d+)\s*use/i', $line, $matches)) {
                $data['keyword_usage'][] = [
                    'keyword' => strtoupper($matches[1]),
                    'keyword_description' => trim($matches[2]),
                    'usage_count' => (int)$matches[3],
                    'usage_date' => $reportDate,
                    'report_file' => basename($filePath),
                    'report_period' => 'Monthly',
                ];
            }
        }

        return $data;
    }

    /**
     * Parse a Shoutbomb weekly report file.
     */
    public function parseWeeklyReport(string $filePath): array
    {
        Log::info("Parsing weekly report: {$filePath}");

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $data = [
            'registration_stats' => null,
            'keyword_usage' => [],
        ];

        $reportDate = $this->extractDateFromFilename($filePath);

        // Similar parsing logic to monthly report
        foreach ($lines as $line) {
            // Registration statistics
            if (preg_match('/Registration Statistics:\s*(\d+)\s*text.*?(\d+)\s*voice/i', $line, $matches)) {
                $textCount = (int)$matches[1];
                $voiceCount = (int)$matches[2];
                $total = $textCount + $voiceCount;

                $data['registration_stats'] = [
                    'snapshot_date' => $reportDate,
                    'total_text_subscribers' => $textCount,
                    'text_percentage' => $total > 0 ? round(($textCount / $total) * 100, 2) : 0,
                    'total_voice_subscribers' => $voiceCount,
                    'voice_percentage' => $total > 0 ? round(($voiceCount / $total) * 100, 2) : 0,
                    'total_subscribers' => $total,
                    'report_file' => basename($filePath),
                    'report_type' => 'Weekly',
                ];
            }

            // Keyword usage
            if (preg_match('/^([A-Z]+).*?(\d+)\s*use/i', $line, $matches)) {
                $data['keyword_usage'][] = [
                    'keyword' => strtoupper($matches[1]),
                    'usage_count' => (int)$matches[2],
                    'usage_date' => $reportDate,
                    'report_file' => basename($filePath),
                    'report_period' => 'Weekly',
                ];
            }
        }

        return $data;
    }

    /**
     * Parse daily invalid phone numbers report.
     */
    public function parseDailyInvalidReport(string $filePath): array
    {
        Log::info("Parsing daily invalid report: {$filePath}");

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $deliveries = [];
        $reportDate = $this->extractDateFromFilename($filePath);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'Patron') !== false) {
                continue; // Skip headers and empty lines
            }

            // Parse lines like: "PatronBarcode: 21234567890  Phone: 270-555-0123"
            if (preg_match('/Patron.*?(\d+).*?Phone.*?(\d{3}[-.\s]?\d{3}[-.\s]?\d{4})/', $line, $matches)) {
                $deliveries[] = [
                    'patron_barcode' => $matches[1],
                    'phone_number' => $this->normalizePhoneNumber($matches[2]),
                    'delivery_type' => 'SMS', // Could be Voice too
                    'sent_date' => $reportDate,
                    'status' => 'Invalid',
                    'report_file' => basename($filePath),
                    'report_type' => 'Daily',
                ];
            }
        }

        return ['deliveries' => $deliveries];
    }

    /**
     * Parse daily undelivered voice notices report.
     */
    public function parseDailyUndeliveredReport(string $filePath): array
    {
        Log::info("Parsing daily undelivered report: {$filePath}");

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $deliveries = [];
        $reportDate = $this->extractDateFromFilename($filePath);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse undelivered voice notices
            if (preg_match('/(\d+).*?(\d{3}[-.\s]?\d{3}[-.\s]?\d{4})/', $line, $matches)) {
                $deliveries[] = [
                    'patron_barcode' => $matches[1],
                    'phone_number' => $this->normalizePhoneNumber($matches[2]),
                    'delivery_type' => 'Voice',
                    'sent_date' => $reportDate,
                    'status' => 'Failed',
                    'failure_reason' => 'Undelivered voice notice',
                    'report_file' => basename($filePath),
                    'report_type' => 'Daily',
                ];
            }
        }

        return ['deliveries' => $deliveries];
    }

    /**
     * Import parsed data into database.
     */
    public function importParsedData(array $data, string $reportType): array
    {
        $stats = [
            'registrations' => 0,
            'keyword_usage' => 0,
            'deliveries' => 0,
        ];

        // Import registration statistics
        if (isset($data['registration_stats']) && $data['registration_stats']) {
            try {
                ShoutbombRegistration::updateOrCreate(
                    ['snapshot_date' => $data['registration_stats']['snapshot_date']],
                    $data['registration_stats']
                );
                $stats['registrations'] = 1;
            } catch (\Exception $e) {
                Log::error("Error importing registration stats", ['error' => $e->getMessage()]);
            }
        }

        // Import keyword usage
        if (isset($data['keyword_usage']) && !empty($data['keyword_usage'])) {
            foreach ($data['keyword_usage'] as $keyword) {
                try {
                    ShoutbombKeywordUsage::create($keyword);
                    $stats['keyword_usage']++;
                } catch (\Exception $e) {
                    Log::error("Error importing keyword usage", ['error' => $e->getMessage()]);
                }
            }
        }

        // Import deliveries
        if (isset($data['deliveries']) && !empty($data['deliveries'])) {
            foreach ($data['deliveries'] as $delivery) {
                try {
                    ShoutbombDelivery::create($delivery);
                    $stats['deliveries']++;
                } catch (\Exception $e) {
                    Log::error("Error importing delivery", ['error' => $e->getMessage()]);
                }
            }
        }

        return $stats;
    }

    /**
     * Normalize phone number format.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Format as XXX-XXX-XXXX
        if (strlen($phone) === 10) {
            return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);
        }

        return $phone;
    }

    /**
     * Extract date from filename.
     */
    private function extractDateFromFilename(string $filePath): Carbon
    {
        $filename = basename($filePath);

        // Try to extract date from filename patterns like "Report_2025-11-06.txt"
        if (preg_match('/(\d{4}[-_]\d{2}[-_]\d{2})/', $filename, $matches)) {
            $dateStr = str_replace('_', '-', $matches[1]);
            return Carbon::parse($dateStr);
        }

        // Try month/year pattern like "October_2025"
        if (preg_match('/([A-Za-z]+)[-_](\d{4})/', $filename, $matches)) {
            $monthStr = $matches[1];
            $year = $matches[2];
            return Carbon::parse("$monthStr 1, $year")->endOfMonth();
        }

        // Default to today
        return now();
    }
}
