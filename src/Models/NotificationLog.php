<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Dcplibrary\Notices\Database\Factories\NotificationLogFactory;
use Dcplibrary\Notices\Models\Polaris\Patron;
use Dcplibrary\Notices\Services\PolarisQueryService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

class NotificationLog extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Keep derived status fields in sync when a status code is set.
            if ($model->notification_status_id !== null) {
                $model->setStatusFromNotificationStatusId();
            }
        });
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'notification_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'polaris_log_id',
        'patron_id',
        'patron_barcode',
        'phone',
        'email',
        'patron_name',
        'item_barcode',
        'item_title',
        'notification_date',
        'notification_type_id',
        'delivery_option_id',
        'notification_status_id',
        'status',
        'status_description',
        'delivery_string',
        'holds_count',
        'overdues_count',
        'overdues_2nd_count',
        'overdues_3rd_count',
        'cancels_count',
        'recalls_count',
        'routings_count',
        'bills_count',
        'manual_bill_count',
        'reporting_org_id',
        'language_id',
        'carrier_name',
        'details',
        'reported',
        'imported_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'notification_date' => 'datetime',
        'imported_at' => 'datetime',
        'reported' => 'boolean',
        'holds_count' => 'integer',
        'overdues_count' => 'integer',
        'overdues_2nd_count' => 'integer',
        'overdues_3rd_count' => 'integer',
        'cancels_count' => 'integer',
        'recalls_count' => 'integer',
        'routings_count' => 'integer',
        'bills_count' => 'integer',
        'manual_bill_count' => 'integer',
    ];

    /**
     * Get the notification type name.
     * Transforms "Email" to "SMS" when delivery method is SMS (delivery_option_id = 8).
     * This is because Polaris used to send SMS messages using email infrastructure,
     * but now we want to display accurate language to users.
     */
    public function getNotificationTypeNameAttribute(): string
    {
        $typeName = config("notices.notification_types.{$this->notification_type_id}", 'Unknown');

        // If delivery method is SMS, replace "Email" with "SMS" in the type name
        if ($this->delivery_option_id === 8) {
            $typeName = str_replace('Email', 'SMS', $typeName);
        }

        return $typeName;
    }

    /**
     * Relationship: Get the delivery method for this notification.
     */
    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class, 'delivery_option_id', 'delivery_option_id');
    }

    /**
     * Get the delivery method name.
     * Falls back to config if deliveryMethod relationship is not loaded.
     */
    public function getDeliveryMethodNameAttribute(): string
    {
        if ($this->relationLoaded('deliveryMethod') && $this->deliveryMethod) {
            return $this->deliveryMethod->name;
        }

        return config("notices.delivery_options.{$this->delivery_option_id}", 'Unknown');
    }

    /**
     * Get the notification status name.
     * Transforms "Email" to "SMS" when delivery method is SMS (delivery_option_id = 8).
     * This is because Polaris used to send SMS messages using email infrastructure,
     * but now we want to display accurate language to users.
     */
    public function getNotificationStatusNameAttribute(): string
    {
        $statusName = config("notices.notification_statuses.{$this->notification_status_id}", 'Unknown');

        // If delivery method is SMS, replace "Email" with "SMS" in the status name
        if ($this->delivery_option_id === 8) {
            $statusName = str_replace('Email', 'SMS', $statusName);
        }

        return $statusName;
    }

    /**
     * Get the total item count for this notification.
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->holds_count +
               $this->overdues_count +
               $this->overdues_2nd_count +
               $this->overdues_3rd_count +
               $this->cancels_count +
               $this->recalls_count +
               $this->bills_count +
               $this->manual_bill_count;
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('notification_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by notification type.
     */
    public function scopeOfType(Builder $query, int $typeId): Builder
    {
        return $query->where('notification_type_id', $typeId);
    }

    /**
     * Scope to filter by delivery method.
     */
    public function scopeByDeliveryMethod(Builder $query, int $deliveryId): Builder
    {
        return $query->where('delivery_option_id', $deliveryId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, int $statusId): Builder
    {
        return $query->where('notification_status_id', $statusId);
    }

    /**
     * Scope to get completed notifications by simplified status.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get pending notifications by simplified status.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get failed notifications by simplified status.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get notifications that represent a successful delivery.
     *
     * This uses notification_status_id semantics that match the reference
     * data tables (12, 15, 16 = success).
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('notification_status_id', [12, 15, 16]);
    }

    /**
     * Scope to get notifications that represent a failed delivery
     * based on notification_status_id.
     */
    public function scopeStatusFailed(Builder $query): Builder
    {
        return $query->whereIn('notification_status_id', [13, 14]);
    }

    /**
     * Scope to filter by patron.
     */
    public function scopeForPatron(Builder $query, int $patronId): Builder
    {
        return $query->where('patron_id', $patronId);
    }

    /**
     * Scope to get recent notifications.
     *
     * "Recent" is defined relative to the most recent notification in the
     * dataset rather than wall-clock time. This keeps behavior deterministic
     * in tests that use fixed dates while still behaving intuitively in
     * production (where new data is continually appended).
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        $latest = static::max('notification_date');

        if (!$latest) {
            return $query;
        }

        $latestDate = $latest instanceof Carbon ? $latest : Carbon::parse($latest);
        $cutoff = $latestDate->copy()->subDays($days);

        return $query->where('notification_date', '>=', $cutoff);
    }

    /**
     * Set status field based on notification_status_id.
     * Can be called manually or via model events.
     */
    public function setStatusFromNotificationStatusId(): void
    {
        if ($this->notification_status_id === null) {
            $this->status = 'pending';
            $this->status_description = null;

            return;
        }

        $completedStatuses = [1, 2, 12, 15, 16];
        $failedStatuses = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14];

        if (in_array($this->notification_status_id, $completedStatuses, true)) {
            $this->status = 'completed';
        } elseif (in_array($this->notification_status_id, $failedStatuses, true)) {
            $this->status = 'failed';
        } else {
            $this->status = 'pending';
        }

        // Set human-readable status description
        $this->status_description = config("notices.notification_statuses.{$this->notification_status_id}");
    }

    /**
     * Get patron details from Polaris.
     * Returns cached Polaris patron record with full name, email, phone, etc.
     *
     * @return Patron|null
     */
    public function getPatronAttribute()
    {
        if (!$this->patron_id) {
            return null;
        }

        $service = app(PolarisQueryService::class);

        return $service->getPatron($this->patron_id);
    }

    /**
     * Get patron's full name from imported Shoutbomb data.
     * Falls back to Polaris if available, then barcode.
     *
     * @return string
     */
    public function getPatronNameAttribute(): string
    {
        // First try Shoutbomb phone notices (already imported data)
        // Use a 60-minute window to account for timing differences
        if ($this->patron_barcode) {
            $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('import_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->name_first && $phoneNotice->name_last) {
                return "{$phoneNotice->name_last}, {$phoneNotice->name_first}";
            }
        }

        // Fall back to Polaris if connected
        $patron = $this->patron;
        if ($patron) {
            return $patron->FormattedName;
        }

        return $this->patron_barcode ?? 'Unknown Patron';
    }

    /**
     * Get patron's first name from imported data.
     *
     * @return string|null
     */
    public function getPatronFirstNameAttribute(): ?string
    {
        if ($this->patron_barcode) {
            $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('import_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->name_first) {
                return $phoneNotice->name_first;
            }
        }

        return $this->patron?->NameFirst;
    }

    /**
     * Get patron's last name from imported data.
     *
     * @return string|null
     */
    public function getPatronLastNameAttribute(): ?string
    {
        if ($this->patron_barcode) {
            $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('import_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->name_last) {
                return $phoneNotice->name_last;
            }
        }

        return $this->patron?->NameLast;
    }

    /**
     * Get patron's email from imported data.
     *
     * @return string|null
     */
    public function getPatronEmailAttribute(): ?string
    {
        if ($this->patron_barcode) {
            $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('import_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->email_address) {
                return $phoneNotice->email_address;
            }
        }

        return $this->patron?->EmailAddress;
    }

    /**
     * Get patron's phone from imported data.
     *
     * @return string|null
     */
    public function getPatronPhoneAttribute(): ?string
    {
        if ($this->patron_barcode) {
            $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('import_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->phone_number) {
                return $phoneNotice->phone_number;
            }
        }

        return $this->patron?->PhoneVoice1;
    }

    /**
     * Get link to patron record in Polaris staff interface.
     *
     * @return string|null
     */
    public function getPatronStaffLinkAttribute(): ?string
    {
        return $this->patron_id
            ? "https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/{$this->patron_id}/record"
            : null;
    }

    /**
     * Get items associated with this notification from imported data.
     * Uses Shoutbomb phone notices first, enriched with Polaris data for complete details.
     * Falls back to direct Polaris query if phone notices not available.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getItemsAttribute()
    {
        // First try to get items from imported Shoutbomb data
        if ($this->patron_barcode) {
            $phoneNotices = PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('import_date', $this->notification_date->format('Y-m-d'))
                ->orderBy('import_date', 'desc')
                ->get();

            if ($phoneNotices->isNotEmpty()) {
                $service = app(PolarisQueryService::class);

                // Enrich phone notices with full Polaris item details
                return $phoneNotices->map(function ($notice) use ($service) {
                    // If we have item_record_id, fetch full item details from Polaris
                    if ($notice->item_record_id) {
                        try {
                            $item = $service->getItem($notice->item_record_id);

                            if ($item) {
                                // Return full item record with bibliographic relationship
                                return $item;
                            }
                        } catch (Exception $e) {
                            // If Polaris query fails, fall through to basic data
                            Log::warning("Failed to fetch item details for ItemRecordID {$notice->item_record_id}", [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Fallback to basic phone notice data if Polaris unavailable
                    return (object) [
                        'title' => $notice->title,
                        'item_barcode' => $notice->item_barcode,
                        'bibliographic' => (object) [
                            'Title' => $notice->title,
                        ],
                        'staff_link' => $notice->item_record_id
                            ? "https://catalog.dcplibrary.org/leapwebapp/staff/default#itemrecords/{$notice->item_record_id}"
                            : null,
                        'ItemRecordID' => $notice->item_record_id,
                        'Barcode' => $notice->item_barcode,
                        'CallNumber' => null,
                    ];
                });
            }
        }

        // Fall back to Polaris if connected
        if ($this->patron_id && $this->notification_type_id) {
            try {
                $service = app(PolarisQueryService::class);

                return $service->getNotificationItems(
                    $this->patron_id,
                    $this->notification_type_id,
                    $this->notification_date
                );
            } catch (Exception $e) {
                // Polaris not available, return empty collection
                return collect();
            }
        }

        return collect();
    }

    /**
     * Get related Shoutbomb phone notice records.
     * Matches by patron barcode and notification date.
     *
     * @return Collection
     */
    public function getPolarisPhoneNoticesAttribute()
    {
        if (!$this->patron_barcode) {
            return collect();
        }

        return PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
            ->whereDate('import_date', $this->notification_date->format('Y-m-d'))
            ->get();
    }

    /**
     * Alias for getPolarisPhoneNoticesAttribute().
     * Matches by patron barcode and notification date.
     *
     * @return Collection
     */
    public function getShoutbombPhoneNoticesAttribute()
    {
        return $this->polaris_phone_notices;
    }

    /**
     * Get related Shoutbomb submission records.
     * Matches by patron barcode and submitted date.
     *
     * @return Collection
     */
    public function getShoutbombSubmissionsAttribute()
    {
        if (!$this->patron_barcode) {
            return collect();
        }

        return ShoutbombSubmission::where('patron_barcode', $this->patron_barcode)
            ->whereDate('submitted_at', $this->notification_date->format('Y-m-d'))
            ->get();
    }

    /**
     * Get related Shoutbomb delivery records.
     * Matches by patron barcode and delivery string (phone).
     *
     * @return Collection
     */
    public function getShoutbombDeliveriesAttribute()
    {
        if (!$this->delivery_string || !in_array($this->delivery_option_id, [3, 8])) {
            return collect();
        }

        // Clean phone number for comparison
        $cleanPhone = preg_replace('/[^0-9]/', '', $this->delivery_string);

        return ShoutbombDelivery::where(function ($query) use ($cleanPhone) {
            $query->where('phone_number', 'LIKE', "%{$cleanPhone}%")
                  ->orWhere('phone_number', 'LIKE', "%{$this->delivery_string}%");
        })
        ->whereDate('sent_date', $this->notification_date->format('Y-m-d'))
        ->get();
    }

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return NotificationLogFactory::new();
    }
}
