<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\ShoutbombSubmission;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Carbon\Carbon;

/**
 * Service for verifying the complete lifecycle of a notice.
 *
 * Tracks: Created → Submitted → Verified → Delivered
 *
 * Uses the plugin system to delegate verification to channel-specific plugins.
 */
class NoticeVerificationService
{
    protected ?PluginRegistry $pluginRegistry = null;

    /**
     * Set the plugin registry.
     */
    public function setPluginRegistry(PluginRegistry $registry): void
    {
        $this->pluginRegistry = $registry;
    }

    /**
     * Verify a single notice log entry.
     */
    public function verify(NotificationLog $log): VerificationResult
    {
        $result = new VerificationResult([
            'created' => true,
            'created_at' => $log->notification_date,
        ]);

        // Build timeline
        $result->addTimelineEvent(
            'created',
            $log->notification_date,
            'notification_logs',
            [
                'id' => $log->id,
                'patron_barcode' => $log->patron_barcode,
                'delivery_method' => $this->getDeliveryMethodName($log->delivery_option_id),
                'notice_type' => $this->getNoticeTypeName($log->notification_type_id),
            ]
        );

        // Try plugin-based verification first
        if ($this->pluginRegistry) {
            $plugin = $this->pluginRegistry->findPluginForNotice($log);
            if ($plugin) {
                $result = $plugin->verify($log, $result);
                $result->determineOverallStatus();
                return $result;
            }
        }

        // Fallback to legacy Shoutbomb verification (for backward compatibility)
        if ($this->isShoutbombDelivery($log)) {
            $this->verifySubmission($log, $result);
            $this->verifyPhoneNotice($log, $result);
            $this->verifyDelivery($log, $result);
        }

        // Re-determine status after all checks
        $result->determineOverallStatus();

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
        // Match by patron + notification type + date (within same day)
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
        // Match by patron + item + date
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
        // Match by phone number + date (within 24 hours)
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
     * Check if this is a Shoutbomb delivery (voice or text).
     */
    protected function isShoutbombDelivery(NotificationLog $log): bool
    {
        // delivery_option_id: 3 = Voice, 8 = SMS
        return in_array($log->delivery_option_id, [3, 8]);
    }

    /**
     * Map notice type ID to submission type string.
     */
    protected function mapNoticeTypeToSubmissionType(int $typeId): string
    {
        return match($typeId) {
            2 => 'holds',      // Hold Ready
            1, 12, 13 => 'overdue',  // Overdue notices
            default => 'unknown',
        };
    }

    /**
     * Get delivery method name.
     */
    protected function getDeliveryMethodName(int $id): string
    {
        $methods = config('notices.delivery_options', []);
        return $methods[$id] ?? "Unknown ($id)";
    }

    /**
     * Get notice type name.
     */
    protected function getNoticeTypeName(int $id): string
    {
        $types = config('notices.notification_types', []);
        return $types[$id] ?? "Unknown ($id)";
    }

    /**
     * Verify multiple notices by patron barcode.
     */
    public function verifyByPatron(string $barcode, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = NotificationLog::where('patron_barcode', $barcode);

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        } elseif ($startDate || $endDate) {
            $date = $startDate ?? $endDate;
            $query->whereDate('notification_date', $date->format('Y-m-d'));
        }

        $notices = $query->orderBy('notification_date', 'desc')->get();

        return $notices->map(function($notice) {
            return [
                'notice' => $notice,
                'verification' => $this->verify($notice),
            ];
        })->toArray();
    }

    /**
     * Get failed notices within a date range.
     */
    public function getFailedNotices(?Carbon $startDate = null, ?Carbon $endDate = null, ?string $reason = null): array
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        $query = ShoutbombDelivery::failed()
            ->dateRange($startDate, $endDate);

        if ($reason) {
            $query->where('failure_reason', 'LIKE', "%{$reason}%");
        }

        return $query->orderBy('sent_date', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Get failure statistics grouped by reason.
     */
    public function getFailuresByReason(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        $failures = ShoutbombDelivery::failed()
            ->dateRange($startDate, $endDate)
            ->selectRaw('
                failure_reason,
                COUNT(*) as count
            ')
            ->whereNotNull('failure_reason')
            ->groupBy('failure_reason')
            ->orderBy('count', 'desc')
            ->get();

        $total = $failures->sum('count');

        return $failures->map(function($failure) use ($total) {
            return [
                'reason' => $failure->failure_reason,
                'count' => $failure->count,
                'percentage' => $total > 0 ? round(($failure->count / $total) * 100, 1) : 0,
            ];
        })->toArray();
    }

    /**
     * Get failure statistics grouped by notification type.
     */
    public function getFailuresByType(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        // Get failed deliveries with their associated submissions
        $failures = ShoutbombDelivery::failed()
            ->dateRange($startDate, $endDate)
            ->get();

        // Group by notification type from submissions
        $byType = [];
        foreach ($failures as $delivery) {
            // Find matching submission to get notification type
            $submission = ShoutbombSubmission::where('phone_number', $delivery->phone_number)
                ->whereDate('submitted_at', $delivery->sent_date->format('Y-m-d'))
                ->first();

            if ($submission) {
                $type = $submission->notification_type;
                if (!isset($byType[$type])) {
                    $byType[$type] = 0;
                }
                $byType[$type]++;
            }
        }

        arsort($byType);

        $total = array_sum($byType);

        return array_map(function($type, $count) use ($total) {
            return [
                'type' => ucfirst($type),
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }, array_keys($byType), $byType);
    }

    /**
     * Detect verification mismatches.
     *
     * Returns:
     * - submitted_not_verified: Notices submitted to Shoutbomb but missing from PhoneNotices
     * - verified_not_delivered: Notices in PhoneNotices but no delivery report
     */
    public function getMismatches(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(1);
        $endDate = $endDate ?? now();

        // Find submissions without matching phone notices
        $submissions = ShoutbombSubmission::dateRange($startDate, $endDate)->get();
        $submittedNotVerified = [];

        foreach ($submissions as $submission) {
            // Check if there's a matching phone notice
            $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $submission->patron_barcode)
                ->whereDate('notice_date', Carbon::parse($submission->submitted_at)->format('Y-m-d'))
                ->first();

            if (!$phoneNotice) {
                $submittedNotVerified[] = [
                    'id' => $submission->id,
                    'patron_barcode' => $submission->patron_barcode,
                    'phone' => $submission->phone_number,
                    'type' => $submission->notification_type,
                    'submitted_at' => $submission->submitted_at,
                    'source_file' => $submission->source_file,
                ];
            }

            // Limit results
            if (count($submittedNotVerified) >= 50) {
                break;
            }
        }

        // Find phone notices without matching deliveries
        $phoneNotices = PolarisPhoneNotice::dateRange($startDate, $endDate)->get();
        $verifiedNotDelivered = [];

        foreach ($phoneNotices as $phoneNotice) {
            // Check if there's a matching delivery
            $delivery = ShoutbombDelivery::where('phone_number', $phoneNotice->phone_number)
                ->whereDate('sent_date', Carbon::parse($phoneNotice->notice_date)->format('Y-m-d'))
                ->first();

            if (!$delivery) {
                $verifiedNotDelivered[] = [
                    'id' => $phoneNotice->id,
                    'patron_barcode' => $phoneNotice->patron_barcode,
                    'phone' => $phoneNotice->phone_number,
                    'item_barcode' => $phoneNotice->item_barcode,
                    'notice_date' => $phoneNotice->notice_date,
                    'delivery_type' => $phoneNotice->delivery_type,
                ];
            }

            // Limit results
            if (count($verifiedNotDelivered) >= 50) {
                break;
            }
        }

        return [
            'submitted_not_verified' => $submittedNotVerified,
            'verified_not_delivered' => $verifiedNotDelivered,
            'summary' => [
                'submitted_not_verified_count' => count($submittedNotVerified),
                'verified_not_delivered_count' => count($verifiedNotDelivered),
            ],
        ];
    }

    /**
     * Get troubleshooting summary statistics.
     */
    public function getTroubleshootingSummary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        // Total notices
        $totalNotices = NotificationLog::dateRange($startDate, $endDate)->count();

        // Failed deliveries
        $failedCount = ShoutbombDelivery::failed()
            ->dateRange($startDate, $endDate)
            ->count();

        // Success rate
        $successRate = $totalNotices > 0 ? round((($totalNotices - $failedCount) / $totalNotices) * 100, 2) : 0;

        // Get mismatches
        $mismatches = $this->getMismatches($startDate, $endDate);

        return [
            'total_notices' => $totalNotices,
            'failed_count' => $failedCount,
            'success_rate' => $successRate,
            'submitted_not_verified' => $mismatches['summary']['submitted_not_verified_count'],
            'verified_not_delivered' => $mismatches['summary']['verified_not_delivered_count'],
        ];
    }
}
