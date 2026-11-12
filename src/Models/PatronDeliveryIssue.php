<?php

namespace Dcplibrary\Notices\Models;

use Dcplibrary\Notices\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links individual patrons to delivery issues.
 *
 * Each patron affected by a failed delivery string or missing preference gets an entry.
 *
 * @property int                       $id
 * @property string                    $issue_type                Type of issue: polaris_export_failure, lx_starter_failure, failed_delivery_string, null_preference
 * @property null|int                  $patron_id                 Patron ID from Polaris
 * @property null|string               $patron_barcode            Patron barcode
 * @property null|int                  $failed_delivery_string_id Foreign key to failed_delivery_strings table
 * @property null|int                  $notification_id           Foreign key to notifications table
 * @property null|string               $issue_details             Human-readable description (calculated dynamically for polaris_export_failure)
 * @property null|string               $meta                      JSON metadata for the issue
 * @property \Carbon\Carbon            $detected_at               When this issue was detected
 * @property null|\Carbon\Carbon       $resolved_at               When this issue was resolved
 * @property null|int                  $resolved_by_user_id       ID of the staff user who resolved the issue (if available)
 * @property null|string               $resolved_by_name          Display name of the staff user who resolved the issue
 * @property null|string               $resolution_notes          Notes about how the issue was resolved
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property null|FailedDeliveryString $failedDeliveryString
 * @property null|Notification         $notification
 */
class PatronDeliveryIssue extends Model
{
    use HasUtcTimestamps;

    protected $table = 'patron_delivery_issues';

    protected $guarded = [];

    protected $casts = [
        'patron_id'                 => 'integer',
        'failed_delivery_string_id' => 'integer',
        'notification_id'           => 'integer',
        'resolved_by_user_id'       => 'integer',
    ];

    /**
     * Get the failed delivery string associated with this issue.
     *
     * @return BelongsTo<FailedDeliveryString, PatronDeliveryIssue>
     */
    public function failedDeliveryString(): BelongsTo
    {
        return $this->belongsTo(FailedDeliveryString::class, 'failed_delivery_string_id');
    }

    /**
     * Get the notification associated with this issue.
     *
     * @return BelongsTo<Notification, PatronDeliveryIssue>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    /**
     * Accessor for detected_at timestamp with UTC timezone handling.
     */
    protected function detectedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    /**
     * Accessor for resolved_at timestamp with UTC timezone handling.
     */
    protected function resolvedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    /**
     * Accessor for issue_details that dynamically generates cluster information.
     *
     * For polaris_export_failure issues, this calculates the cluster description
     * in real-time using the current notification statuses. This ensures the
     * cluster information is always accurate even if notification statuses change
     * after the issue was created (e.g., due to reconciliation fixes).
     *
     * IMPORTANT: The stored database value for polaris_export_failure issues
     * may be stale due to the timezone bug that was fixed on 2026-01-03. This
     * accessor ensures the UI always shows correct data by recalculating it.
     *
     * For other issue types (failed_delivery_string, null_preference, etc.),
     * returns the stored database value.
     *
     * @return Attribute<null|string, never>
     */
    protected function issueDetails(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // For polaris_export_failure issues with a notification, calculate dynamically
                if ('polaris_export_failure' === $this->issue_type && $this->notification) {
                    return $this->calculateClusterDescription();
                }

                // For other issue types, use stored value
                return $value;
            }
        );
    }

    /**
     * Calculate cluster description for a polaris_export_failure issue.
     *
     * Uses ClusterAnalyzer to generate a human-readable description of the
     * failure cluster this notification belongs to.
     */
    protected function calculateClusterDescription(): ?string
    {
        $notification = $this->notification;

        if (!$notification || !$notification->first_exported_at) {
            return null;
        }

        // Extract date and hour from notification's first_exported_at
        $date = $notification->first_exported_at->format('Y-m-d');
        $hour = (int) $notification->first_exported_at->format('G');

        // Use ClusterAnalyzer to get counts and generate description
        $failedCount  = \Dcplibrary\Notices\Support\ClusterAnalyzer::countFailedDeliveries($date, $hour);
        $successCount = \Dcplibrary\Notices\Support\ClusterAnalyzer::countSuccessfulDeliveries($date, $hour);

        return \Dcplibrary\Notices\Support\ClusterAnalyzer::generateClusterDescription(
            $date,
            $hour,
            $failedCount,
            $successCount
        );
    }
}
