<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Dcplibrary\Notices\Services\PolarisQueryService;

class NotificationLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'notification_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
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
     */
    public function getNotificationTypeNameAttribute(): string
    {
        return config("notices.notification_types.{$this->notification_type_id}", 'Unknown');
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
     */
    public function getNotificationStatusNameAttribute(): string
    {
        return config("notices.notification_statuses.{$this->notification_status_id}", 'Unknown');
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
     * Scope to get completed notifications.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get pending notifications.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get failed notifications.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
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
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('notification_date', '>=', now()->subDays($days));
    }

    /**
     * Set status field based on notification_status_id.
     * Called automatically when notification_status_id is set.
     */
    public function setStatusFromNotificationStatusId(): void
    {
        $completedStatuses = [1, 2, 12, 15, 16];
        $failedStatuses = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14];

        if (in_array($this->notification_status_id, $completedStatuses)) {
            $this->status = 'completed';
        } elseif (in_array($this->notification_status_id, $failedStatuses)) {
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
     * @return \Dcplibrary\Notices\Models\Polaris\Patron|null
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
            $phoneNotice = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereBetween('notice_date', [
                    $this->notification_date->copy()->subMinutes(60),
                    $this->notification_date->copy()->addMinutes(60)
                ])
                ->orderBy('notice_date', 'desc')->first();

            if ($phoneNotice && $phoneNotice->first_name && $phoneNotice->last_name) {
                return "{$phoneNotice->last_name}, {$phoneNotice->first_name}";
            }

            // Second attempt: Exact date match if the first one failed
            $phoneNotice = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('notice_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->first_name && $phoneNotice->last_name) {
                return "{$phoneNotice->last_name}, {$phoneNotice->first_name}";
            }

        // Fall back to Polaris if connected
        $patron = $this->patron;
        if ($patron) {
            return $patron->FormattedName;
        }

        return $this->patron_barcode ?? 'Unknown Patron';
     }
    }

    /**
     * Get patron's first name from imported data.
     *
     * @return string|null
     */
    public function getPatronFirstNameAttribute(): ?string
    {
        if ($this->patron_barcode) {
            $phoneNotice = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereBetween('notice_date', [
                    $this->notification_date->copy()->subMinutes(60),
                    $this->notification_date->copy()->addMinutes(60)
                ])
                ->orderBy('notice_date', 'desc')
                ->whereDate('notice_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->first_name) {
                return $phoneNotice->first_name;
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
            $phoneNotice = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereBetween('notice_date', [
                    $this->notification_date->copy()->subMinutes(60),
                    $this->notification_date->copy()->addMinutes(60)
                ])
                ->orderBy('notice_date', 'desc')
                ->whereDate('notice_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->last_name) {
                return $phoneNotice->last_name;
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
            $phoneNotice = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereBetween('notice_date', [
                    $this->notification_date->copy()->subMinutes(60),
                    $this->notification_date->copy()->addMinutes(60)
                ])
                ->orderBy('notice_date', 'desc')
                ->whereDate('notice_date', $this->notification_date->format('Y-m-d'))
                ->first();

            if ($phoneNotice && $phoneNotice->email) {
                return $phoneNotice->email;
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
            $phoneNotice = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereBetween('notice_date', [
                    $this->notification_date->copy()->subMinutes(60),
                    $this->notification_date->copy()->addMinutes(60)
                ])
                ->orderBy('notice_date', 'desc')
                ->whereDate('notice_date', $this->notification_date->format('Y-m-d'))
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
     * Uses Shoutbomb phone notices first, falls back to Polaris if available.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getItemsAttribute()
    {
        // First try to get items from imported Shoutbomb data
        if ($this->patron_barcode) {
            $phoneNotices = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
                ->whereDate('notice_date', $this->notification_date->format('Y-m-d'))
                ->orderBy('notice_date', 'desc')
                ->get();

            if ($phoneNotices->isNotEmpty()) {
                // Convert phone notices to a collection with title and barcode
                return $phoneNotices->map(function ($notice) {
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
                        'CallNumber' => null, // Not in phone notices
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
            } catch (\Exception $e) {
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
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPolarisPhoneNoticesAttribute()
    {
        if (!$this->patron_barcode) {
            return collect();
        }

        return \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $this->patron_barcode)
            ->whereDate('notice_date', $this->notification_date->format('Y-m-d'))
            ->get();
    }

    /**
     * Alias for getPolarisPhoneNoticesAttribute().
     * Matches by patron barcode and notification date.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getShoutbombPhoneNoticesAttribute()
    {
        return $this->polaris_phone_notices;
    }

    /**
     * Get related Shoutbomb submission records.
     * Matches by patron barcode and submitted date.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getShoutbombSubmissionsAttribute()
    {
        if (!$this->patron_barcode) {
            return collect();
        }

        return \Dcplibrary\Notices\Models\ShoutbombSubmission::where('patron_barcode', $this->patron_barcode)
            ->whereDate('submitted_at', $this->notification_date->format('Y-m-d'))
            ->get();
    }

    /**
     * Get related Shoutbomb delivery records.
     * Matches by patron barcode and delivery string (phone).
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getShoutbombDeliveriesAttribute()
    {
        if (!$this->delivery_string || !in_array($this->delivery_option_id, [3, 8])) {
            return collect();
        }

        // Clean phone number for comparison
        $cleanPhone = preg_replace('/[^0-9]/', '', $this->delivery_string);

        return \Dcplibrary\Notices\Models\ShoutbombDelivery::where(function ($query) use ($cleanPhone) {
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
        return \Dcplibrary\Notices\Database\Factories\NotificationLogFactory::new();
    }
}
