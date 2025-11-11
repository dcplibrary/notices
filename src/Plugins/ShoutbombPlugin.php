<?php

namespace Dcplibrary\Notices\Plugins;

use Dcplibrary\Notices\Contracts\NotificationPlugin;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\ShoutbombSubmission;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Dcplibrary\Notices\Services\VerificationResult;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Plugin for Shoutbomb voice and text notifications.
 *
 * Tracks notices through:
 * 1. Submissions (from SQL-generated files)
 * 2. Phone notices (from Polaris PhoneNotices.csv)
 * 3. Deliveries (from Shoutbomb delivery reports)
 */
class ShoutbombPlugin implements NotificationPlugin
{
    /**
     * Get the plugin's unique identifier.
     */
    public function getName(): string
    {
        return 'shoutbomb';
    }

    /**
     * Get the plugin's display name.
     */
    public function getDisplayName(): string
    {
        return 'Shoutbomb Voice/Text';
    }

    /**
     * Get the plugin's description.
     */
    public function getDescription(): string
    {
        return 'Handles voice and text message notifications delivered via Shoutbomb service.';
    }

    /**
     * Get the delivery option IDs this plugin handles.
     *
     * @return array<int>
     */
    public function getDeliveryOptionIds(): array
    {
        return [
            3, // Voice
            8, // SMS/Text
        ];
    }

    /**
     * Check if this plugin can verify a given notice.
     */
    public function canVerify(NotificationLog $log): bool
    {
        return in_array($log->delivery_option_id, $this->getDeliveryOptionIds());
    }

    /**
     * Verify a notice using Shoutbomb verification logic.
     */
    public function verify(NotificationLog $log, VerificationResult $result): VerificationResult
    {
        if (!$this->canVerify($log)) {
            return $result;
        }

        // Step 1: Check submission
        $this->verifySubmission($log, $result);

        // Step 2: Check phone notice verification
        $this->verifyPhoneNotice($log, $result);

        // Step 3: Check delivery
        $this->verifyDelivery($log, $result);

        return $result;
    }

    /**
     * Verify if the notice was submitted to Shoutbomb.
     */
    protected function verifySubmission(NotificationLog $log, VerificationResult $result): void
    {
        $submission = $this->findSubmission($log);

        if ($submission) {
            $result->submitted = true;
            $result->submitted_at = $submission->submitted_at;
            $result->submission_file = $submission->source_file;

            $result->addTimelineEvent(
                'submitted',
                $submission->submitted_at,
                'shoutbomb_submissions',
                [
                    'id' => $submission->id,
                    'file' => $submission->source_file,
                    'delivery_type' => $submission->delivery_type,
                ]
            );
        }
    }

    /**
     * Verify if the notice appears in PhoneNotices.csv.
     */
    protected function verifyPhoneNotice(NotificationLog $log, VerificationResult $result): void
    {
        $phoneNotice = $this->findPhoneNotice($log);

        if ($phoneNotice) {
            $result->verified = true;
            $result->verified_at = Carbon::parse($phoneNotice->notice_date);
            $result->verification_file = $phoneNotice->source_file ?? 'PhoneNotices.csv';

            $result->addTimelineEvent(
                'verified',
                Carbon::parse($phoneNotice->notice_date),
                'polaris_phone_notices',
                [
                    'id' => $phoneNotice->id,
                    'file' => $phoneNotice->source_file ?? 'PhoneNotices.csv',
                    'delivery_type' => $phoneNotice->delivery_type,
                ]
            );
        }
    }

    /**
     * Verify if the notice was delivered (from Shoutbomb reports).
     */
    protected function verifyDelivery(NotificationLog $log, VerificationResult $result): void
    {
        $delivery = $this->findDelivery($log);

        if ($delivery) {
            $result->delivered = true;
            $result->delivered_at = $delivery->sent_date;
            $result->delivery_status = $delivery->status;
            $result->failure_reason = $delivery->failure_reason;

            $result->addTimelineEvent(
                'delivered',
                $delivery->sent_date,
                'shoutbomb_deliveries',
                [
                    'id' => $delivery->id,
                    'status' => $delivery->status,
                    'failure_reason' => $delivery->failure_reason,
                    'carrier' => $delivery->carrier,
                ]
            );
        }
    }

    /**
     * Find the submission record for a notice.
     */
    protected function findSubmission(NotificationLog $log): ?ShoutbombSubmission
    {
        $noticeDate = Carbon::parse($log->notification_date);
        $notificationType = $this->mapNoticeTypeToSubmissionType($log->notification_type_id);

        return ShoutbombSubmission::where('patron_barcode', $log->patron_barcode)
            ->where('notification_type', $notificationType)
            ->whereDate('submitted_at', $noticeDate->format('Y-m-d'))
            ->first();
    }

    /**
     * Find the phone notice record (verification).
     */
    protected function findPhoneNotice(NotificationLog $log): ?PolarisPhoneNotice
    {
        $noticeDate = Carbon::parse($log->notification_date);

        $query = PolarisPhoneNotice::where('patron_barcode', $log->patron_barcode)
            ->whereDate('notice_date', $noticeDate->format('Y-m-d'));

        // If we have item barcode, match on it too
        if ($log->item_barcode) {
            $query->where('item_barcode', $log->item_barcode);
        }

        return $query->first();
    }

    /**
     * Find the delivery record (Shoutbomb reports).
     */
    protected function findDelivery(NotificationLog $log): ?ShoutbombDelivery
    {
        if (!$log->phone) {
            return null;
        }

        $noticeDate = Carbon::parse($log->notification_date);

        return ShoutbombDelivery::where('phone_number', $log->phone)
            ->whereBetween('sent_date', [
                $noticeDate->copy()->subHours(2),
                $noticeDate->copy()->addHours(24),
            ])
            ->orderBy('sent_date', 'asc')
            ->first();
    }

    /**
     * Map notice type ID to submission type string.
     */
    protected function mapNoticeTypeToSubmissionType(int $typeId): string
    {
        return match($typeId) {
            2 => 'holds',           // Hold Ready
            1, 12, 13 => 'overdue', // Overdue notices
            default => 'unknown',
        };
    }

    /**
     * Get statistics for Shoutbomb within a date range.
     */
    public function getStatistics(Carbon $startDate, Carbon $endDate): array
    {
        // Get submission stats
        $submissionStats = ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_submissions,
                COUNT(DISTINCT patron_barcode) as unique_patrons,
                SUM(CASE WHEN delivery_type = "voice" THEN 1 ELSE 0 END) as voice_count,
                SUM(CASE WHEN delivery_type = "text" THEN 1 ELSE 0 END) as text_count
            ')
            ->first();

        // Get delivery stats
        $deliveryStats = ShoutbombDelivery::whereBetween('sent_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN status = "Delivered" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status != "Delivered" THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        $totalDeliveries = $deliveryStats->total_deliveries ?? 0;
        $successRate = $totalDeliveries > 0
            ? round(($deliveryStats->successful / $totalDeliveries) * 100, 2)
            : 0;

        return [
            'total_sent' => $submissionStats->total_submissions ?? 0,
            'total_delivered' => $deliveryStats->successful ?? 0,
            'total_failed' => $deliveryStats->failed ?? 0,
            'success_rate' => $successRate,
            'additional_stats' => [
                'unique_patrons' => $submissionStats->unique_patrons ?? 0,
                'voice_count' => $submissionStats->voice_count ?? 0,
                'text_count' => $submissionStats->text_count ?? 0,
            ],
        ];
    }

    /**
     * Get failed notices for Shoutbomb.
     */
    public function getFailedNotices(Carbon $startDate, Carbon $endDate, ?string $reason = null): Collection
    {
        $query = ShoutbombDelivery::failed()
            ->dateRange($startDate, $endDate);

        if ($reason) {
            $query->where('failure_reason', 'LIKE', "%{$reason}%");
        }

        return $query->orderBy('sent_date', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Get the dashboard widget view for Shoutbomb.
     */
    public function getDashboardWidget(Carbon $startDate, Carbon $endDate): ?View
    {
        $stats = $this->getStatistics($startDate, $endDate);

        return view('notifications::plugins.shoutbomb.widget', compact('stats', 'startDate', 'endDate'));
    }

    /**
     * Get configuration settings for this plugin.
     */
    public function getConfig(): array
    {
        return [
            'enabled' => config('notices.plugins.shoutbomb.enabled', true),
            'import_submissions' => config('notices.plugins.shoutbomb.import_submissions', true),
            'import_phone_notices' => config('notices.plugins.shoutbomb.import_phone_notices', true),
            'import_deliveries' => config('notices.plugins.shoutbomb.import_deliveries', true),
        ];
    }

    /**
     * Check if this plugin is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->getConfig()['enabled'] ?? true;
    }
}
