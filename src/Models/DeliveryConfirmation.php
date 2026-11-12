<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single confirmed or failed delivery event sourced from an ingested email report.
 *
 * DeliveryConfirmations are created by the email ingestion pipeline and linked
 * to unified Notification records during reconciliation. They serve as the
 * evidence trail for marking a notification as delivered or failed.
 *
 * @property int                 $id
 * @property null|int            $notification_id     FK → notifications
 * @property null|int            $ingested_email_id   FK → ingested_emails
 * @property string              $confirmation_source E.g. "shoutbomb_voice_failure", "polaris_summary_report"
 * @property null|string         $status              "delivered" | "failed" | "invalid_phone"
 * @property null|\Carbon\Carbon $notice_date         Date of the original notice event
 * @property null|string         $raw_data            Raw confirmation data from the report
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 * @property null|Notification   $notification
 * @property null|IngestedEmail  $ingestedEmail
 */
class DeliveryConfirmation extends Model
{
    protected $table = 'delivery_confirmations';

    protected $guarded = [];

    protected $casts = [
        'notice_date' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function ingestedEmail(): BelongsTo
    {
        return $this->belongsTo(IngestedEmail::class, 'ingested_email_id');
    }
}
