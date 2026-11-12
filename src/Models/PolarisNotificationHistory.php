<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Polaris NotificationHistory export record.
 *
 * Item-level detail from Results.Polaris.NotificationHistory table.
 * Used to confirm notifications were actually processed by Polaris.
 */
class PolarisNotificationHistory extends Model
{
    protected $table = 'polaris_notification_history';

    protected $guarded = [];

    protected $casts = [
        'notice_date'     => 'datetime',
        'amount'          => 'decimal:2',
        'export_datetime' => 'datetime',
    ];

    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }

    public function deliveryOption(): BelongsTo
    {
        return $this->belongsTo(DeliveryOption::class, 'delivery_option_id');
    }
}
