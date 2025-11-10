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
        'polaris_log_id',
        'patron_id',
        'patron_barcode',
        'notification_date',
        'notification_type_id',
        'delivery_option_id',
        'notification_status_id',
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
     * Get the delivery method name.
     */
    public function getDeliveryMethodNameAttribute(): string
    {
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
     * Scope to get successful notifications.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('notification_status_id', 12); // 12 = Success
    }

    /**
     * Scope to get failed notifications.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('notification_status_id', 14); // 14 = Failed
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
     * Get patron's full name from Polaris.
     * Convenience accessor for displaying patron names.
     *
     * @return string
     */
    public function getPatronNameAttribute(): string
    {
        $patron = $this->patron;

        if ($patron) {
            return $patron->FormattedName; // "Last, First"
        }

        return $this->patron_barcode ?? 'Unknown Patron';
    }

    /**
     * Get patron's first name.
     *
     * @return string|null
     */
    public function getPatronFirstNameAttribute(): ?string
    {
        return $this->patron?->NameFirst;
    }

    /**
     * Get patron's last name.
     *
     * @return string|null
     */
    public function getPatronLastNameAttribute(): ?string
    {
        return $this->patron?->NameLast;
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
     * Get items associated with this notification from Polaris.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getItemsAttribute()
    {
        if (!$this->patron_id || !$this->notification_type_id) {
            return collect();
        }

        $service = app(PolarisQueryService::class);
        return $service->getNotificationItems(
            $this->patron_id,
            $this->notification_type_id,
            $this->notification_date
        );
    }

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return \Dcplibrary\Notices\Database\Factories\NotificationLogFactory::new();
    }
}
