<?php

namespace Dcplibrary\PolarisNotifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class PolarisNotificationLog extends Model
{
    /**
     * The connection name for the model.
     * This should point to the Polaris MSSQL database.
     */
    protected $connection = 'polaris';

    /**
     * The table associated with the model.
     */
    protected $table = 'PolarisTransactions.Polaris.NotificationLog';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'NotificationLogID';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'NotificationDateTime' => 'datetime',
        'Reported' => 'boolean',
    ];

    /**
     * Scope to get notifications from the last N days.
     */
    public function scopeRecentDays(Builder $query, int $days = 1): Builder
    {
        return $query->where('NotificationDateTime', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('NotificationDateTime', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by reporting organization.
     */
    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('ReportingOrgID', $orgId);
    }

    /**
     * Scope to get only unreported notifications.
     */
    public function scopeUnreported(Builder $query): Builder
    {
        return $query->where('Reported', 0);
    }

    /**
     * Scope to filter by notification type.
     */
    public function scopeOfType(Builder $query, int $typeId): Builder
    {
        return $query->where('NotificationTypeID', $typeId);
    }

    /**
     * Scope to filter by delivery method.
     */
    public function scopeByDeliveryMethod(Builder $query, int $deliveryId): Builder
    {
        return $query->where('DeliveryOptionID', $deliveryId);
    }

    /**
     * Convert to local NotificationLog model format.
     */
    public function toLocalFormat(): array
    {
        return [
            'polaris_log_id' => $this->NotificationLogID,
            'patron_id' => $this->PatronID,
            'patron_barcode' => $this->PatronBarcode,
            'notification_date' => $this->NotificationDateTime,
            'notification_type_id' => $this->NotificationTypeID,
            'delivery_option_id' => $this->DeliveryOptionID,
            'notification_status_id' => $this->NotificationStatusID,
            'delivery_string' => $this->DeliveryString,
            'holds_count' => $this->HoldsCount ?? 0,
            'overdues_count' => $this->OverduesCount ?? 0,
            'overdues_2nd_count' => $this->Overdues2ndCount ?? 0,
            'overdues_3rd_count' => $this->Overdues3rdCount ?? 0,
            'cancels_count' => $this->CancelsCount ?? 0,
            'recalls_count' => $this->RecallsCount ?? 0,
            'routings_count' => $this->RoutingsCount ?? 0,
            'bills_count' => $this->BillsCount ?? 0,
            'manual_bill_count' => $this->ManualBillCount ?? 0,
            'reporting_org_id' => $this->ReportingOrgID,
            'language_id' => $this->LanguageID,
            'carrier_name' => $this->CarrierName,
            'details' => $this->Details,
            'reported' => $this->Reported,
            'imported_at' => now(),
        ];
    }

    /**
     * Get statistics for a date range.
     */
    public static function getStats(Carbon $startDate, Carbon $endDate, ?int $orgId = null): array
    {
        $query = static::dateRange($startDate, $endDate);

        if ($orgId) {
            $query->forOrganization($orgId);
        }

        return [
            'total' => $query->count(),
            'by_type' => $query->selectRaw('NotificationTypeID, COUNT(*) as count')
                ->groupBy('NotificationTypeID')
                ->pluck('count', 'NotificationTypeID')
                ->toArray(),
            'by_delivery' => $query->selectRaw('DeliveryOptionID, COUNT(*) as count')
                ->groupBy('DeliveryOptionID')
                ->pluck('count', 'DeliveryOptionID')
                ->toArray(),
        ];
    }
}
