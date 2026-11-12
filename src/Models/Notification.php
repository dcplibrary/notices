<?php

namespace Dcplibrary\Notices\Models;

use Dcplibrary\Notices\Database\Factories\NotificationFactory;
use Dcplibrary\Notices\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unified notification record.
 *
 * This model represents a deduplicated notification derived from one or more
 * raw Polaris exports plus auxiliary sources (e.g. Shoutbomb failures).
 */
class Notification extends Model
{
    use HasFactory;
    use HasUtcTimestamps;

    protected $table = 'notifications';

    protected $guarded = [];

    protected $casts = [
        'delivery_option_id' => 'integer',
        'is_combined'        => 'boolean',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(NotificationImportBatch::class, 'import_batch_id');
    }

    public function deliveryOption(): BelongsTo
    {
        return $this->belongsTo(DeliveryOption::class, 'delivery_option_id');
    }

    public function activeIssues()
    {
        return $this->hasMany(PatronDeliveryIssue::class, 'notification_id')
            ->whereNull('resolved_at')
        ;
    }

    public function issues()
    {
        return $this->hasMany(PatronDeliveryIssue::class, 'notification_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(NotificationActivityLog::class, 'notification_id');
    }

    public function ingestedEmail(): BelongsTo
    {
        return $this->belongsTo(IngestedEmail::class, 'ingested_email_id');
    }

    /**
     * Database stores timestamps in UTC. Convert to application timezone.
     */
    protected function generatedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function firstExportedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function lastExportedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function expectedDeliveryAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function deliveredAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function failedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    /**
     * notice_date is used for reconciliation matching and is stored in UTC.
     * Ensure it hydrates as a Carbon instance (not a raw string) for API resources.
     */
    protected function noticeDate(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    /**
     * Calculate the sent date for this notification.
     *
     * Logic:
     * - For Voice (3) and SMS (8): Extract date from first filename in import batch identifier
     *   - This represents when the notification first appeared in exports
     *   - For multi-step notifications (2nd overdue, 3rd overdue, etc.), the schedule_step field
     *     and item due date would be needed to calculate the actual scheduled send date
     *     based on Shoutbomb's delivery schedule (see docs/NOTIFICATIONS README.md)
     * - For Email (2) and Mail (1): Use notification_log created date (first_exported_at)
     *   - Usually the day before the notification log is imported
     * - Fallback to first_exported_at if no better data available
     *
     * TODO: Implement multi-step calculation for overdue (1st, 2nd, 3rd, bill, collection),
     *       renewal (3 days before, 1 day before), and hold (initial, 1 day before expiry, 7 days after)
     *       This requires:
     *       - Item due date from raw_payload
     *       - Tracking schedule_step during import
     *       - Applying Shoutbomb schedule rules
     */
    protected function sentDate(): Attribute
    {
        return Attribute::get(function () {
            // For Email and Mail, use first_exported_at (notification log created date)
            if (in_array($this->delivery_option_id, [1, 2])) {
                return $this->first_exported_at;
            }

            // For Voice and SMS, try to extract date from import batch filename
            if (in_array($this->delivery_option_id, [3, 8]) && $this->importBatch) {
                $filename = $this->importBatch->identifier;

                // Extract date from filename pattern: {type}_{yyyy-mm-dd_hh-mm-ss}.txt
                if ($filename && preg_match('/_(\d{4}-\d{2}-\d{2})_\d{2}-\d{2}-\d{2}/', $filename, $matches)) {
                    $dateStr = $matches[1];

                    // Parse as date in application timezone
                    try {
                        return \Carbon\Carbon::parse($dateStr);
                    } catch (\Exception $e) {
                        // Fall through to default
                    }
                }
            }

            // Fallback to first_exported_at
            return $this->first_exported_at;
        });
    }

    /**
     * Get a human-readable failure reason.
     *
     * Converts raw failure data into user-friendly messages.
     */
    protected function failureReasonFormatted(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->failure_reason) {
                return null;
            }

            // Check if it's a raw summary report line (format: phone :: barcode :: patron_id :: org_id :: type)
            // May have "Invalid phone number:" prefix
            if (preg_match('/(?:Invalid phone number:\s*)?(\d+)\s*::\s*\d+\s*::\s*\d+\s*::\s*\d+\s*::\s*(SMS|Voice)$/i', $this->failure_reason, $matches)) {
                $phone = $matches[1];
                $type  = $matches[2];

                // Use Shoutbomb's language from their email reports
                if (str_contains(strtolower($this->failure_reason), 'invalid phone')) {
                    return "Phone number seems to be invalid based on multiple attempts to contact ({$phone})";
                }

                return "Phone number seems to have opted-out from {$type} messages based on multiple attempts to contact ({$phone})";
            }

            // Check if it's a Bandwidth error (BANDWIDTH :: ...)
            if (str_contains($this->failure_reason, 'BANDWIDTH ::')) {
                return 'Voice notification failed - carrier error';
            }

            // Check for common failure patterns
            if (str_contains($this->failure_reason, 'opted out') || str_contains($this->failure_reason, 'OPTED-OUT')) {
                return 'Patron opted out of notifications';
            }

            if (str_contains($this->failure_reason, 'invalid') && str_contains($this->failure_reason, 'phone')) {
                return 'Invalid phone number';
            }

            if (str_contains($this->failure_reason, 'Email notification not uploaded to LX Starter')) {
                return 'Email not uploaded to delivery service';
            }

            // Return the original if no pattern matches
            return $this->failure_reason;
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }
}
