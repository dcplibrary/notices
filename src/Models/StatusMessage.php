<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status messages lookup model.
 *
 * Stores all notification status messages (delivered, pending, failed)
 * with customizable labels, messages, and actions for staff.
 */
class StatusMessage extends Model
{
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_FAILED    = 'failed';
    protected $table              = 'status_messages';

    protected $fillable = [
        'code',
        'status',
        'label',
        'message',
        'action',
        'delivery_option_id',
        'source',
        'notification_status_id',
        'is_contact_issue',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'delivery_option_id'     => 'integer',
        'notification_status_id' => 'integer',
        'is_contact_issue'       => 'boolean',
        'sort_order'             => 'integer',
        'is_enabled'             => 'boolean',
    ];

    /**
     * Get the delivery option associated with this status message.
     */
    public function deliveryOption(): BelongsTo
    {
        return $this->belongsTo(DeliveryOption::class, 'delivery_option_id');
    }

    /**
     * Get the notification status associated with this status message.
     */
    public function notificationStatus(): BelongsTo
    {
        return $this->belongsTo(NotificationStatus::class, 'notification_status_id');
    }

    /**
     * Find a status message by its code.
     *
     * @param string $code The status message code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->where('is_enabled', true)->first();
    }

    /**
     * Get all enabled status messages.
     */
    public static function getEnabled(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_enabled', true)
            ->orderBy('status')
            ->orderBy('sort_order')
            ->get()
        ;
    }

    /**
     * Get enabled status messages by status type.
     *
     * @param string $status The status (delivered, pending, failed)
     */
    public static function getByStatus(string $status): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_enabled', true)
            ->where('status', $status)
            ->orderBy('sort_order')
            ->get()
        ;
    }

    /**
     * Get delivered messages.
     */
    public static function getDelivered(): \Illuminate\Database\Eloquent\Collection
    {
        return static::getByStatus(self::STATUS_DELIVERED);
    }

    /**
     * Get pending messages.
     */
    public static function getPending(): \Illuminate\Database\Eloquent\Collection
    {
        return static::getByStatus(self::STATUS_PENDING);
    }

    /**
     * Get failed messages (failure reasons).
     */
    public static function getFailed(): \Illuminate\Database\Eloquent\Collection
    {
        return static::getByStatus(self::STATUS_FAILED);
    }

    /**
     * Get enabled status messages for a specific source.
     *
     * @param string $source The source (shoutbomb, notification_log, system)
     */
    public static function getBySource(string $source): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_enabled', true)
            ->where('source', $source)
            ->orderBy('sort_order')
            ->get()
        ;
    }

    /**
     * Get enabled status messages that are contact issues.
     */
    public static function getContactIssues(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_enabled', true)
            ->where('is_contact_issue', true)
            ->orderBy('sort_order')
            ->get()
        ;
    }

    /**
     * Find a message by status and delivery option icon name.
     * Used for delivered/pending messages which are channel-specific.
     *
     * @param string $status   The status (delivered, pending)
     * @param string $iconName The delivery option icon name (email, sms, phone, mail)
     */
    public static function findByStatusAndChannel(string $status, string $iconName): ?self
    {
        // Build the expected code pattern: delivered_email, pending_sms, etc.
        $channelSuffix = match ($iconName) {
            'email' => 'email',
            'sms'   => 'sms',
            'phone' => 'voice',
            'mail'  => 'mail',
            default => 'email',
        };

        $code = "{$status}_{$channelSuffix}";

        return static::where('code', $code)
            ->where('status', $status)
            ->where('is_enabled', true)
            ->first();
    }
}
