<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use DB;
use Dcplibrary\Notices\Database\Factories\PolarisNotificationLogFactory;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

class PolarisNotificationLog extends Model
{
    use HasFactory;

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
        // Parse phone/email from delivery_string based on delivery type
        $phone = null;
        $email = null;

        if ($this->DeliveryOptionID == 3 || $this->DeliveryOptionID == 8) {
            // Voice (3) or SMS (8)
            // DeliveryString may be a phone number or an email-to-SMS address (e.g., 2705551234@txt.att.net)
            // Best representation for our use case (Shoutbomb) is the 10-digit local number.
            $raw = (string) $this->DeliveryString;
            // Trim at '@' if present to avoid picking up digits from the domain
            $local = str_contains($raw, '@') ? substr($raw, 0, strpos($raw, '@')) : $raw;
            $digits = preg_replace('/[^0-9]/', '', $local);
            if (!empty($digits)) {
                // Use last 10 digits if longer (typical NANP)
                $phone = strlen($digits) > 10 ? substr($digits, -10) : $digits;
            }
        } elseif ($this->DeliveryOptionID == 2) {
            // Email (2) - delivery_string contains email
            $email = $this->DeliveryString;
        }

        // Get patron name from Polaris (cached per import batch for performance)
        $patronName = $this->getPatronName();

        // Get primary item info (first hold/overdue item if available)
        $itemInfo = $this->getPrimaryItemInfo();

        // Map notification_status_id to simplified status field
        $completedStatuses = [1, 2, 12, 15, 16];
        $failedStatuses = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14];

        if (in_array($this->NotificationStatusID, $completedStatuses)) {
            $status = 'completed';
        } elseif (in_array($this->NotificationStatusID, $failedStatuses)) {
            $status = 'failed';
        } else {
            $status = 'pending';
        }

        return [
            'polaris_log_id' => $this->NotificationLogID,
            'patron_id' => $this->PatronID,
            'patron_barcode' => $this->PatronBarcode,
            'phone' => $phone,
            'email' => $email,
            'patron_name' => $patronName,
            'item_barcode' => $itemInfo['barcode'] ?? null,
            'item_title' => $itemInfo['title'] ?? null,
            'notification_date' => $this->NotificationDateTime,
            'notification_type_id' => $this->NotificationTypeID,
            'delivery_option_id' => $this->DeliveryOptionID,
            'notification_status_id' => $this->NotificationStatusID,
            'status' => $status,
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
     * Get patron name from Polaris.
     */
    protected function getPatronName(): ?string
    {
        try {
            $patron = DB::connection('polaris')
                ->table('Polaris.Polaris.PatronRegistration')
                ->where('PatronID', $this->PatronID)
                ->select('NameLast', 'NameFirst')
                ->first();

            if ($patron) {
                return trim(($patron->NameFirst ?? '') . ' ' . ($patron->NameLast ?? ''));
            }
        } catch (Exception $e) {
            Log::warning('Failed to fetch patron name', [
                'patron_id' => $this->PatronID,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get primary item information.
     * Note: NotificationLogLineItems table doesn't exist in this Polaris installation.
     * Item details would need to be retrieved from imported Shoutbomb data instead.
     */
    protected function getPrimaryItemInfo(): array
    {
        // NotificationLogLineItems table doesn't exist in this database
        // Return empty array - item info will be populated from Shoutbomb imports
        return [];
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

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return PolarisNotificationLogFactory::new();
    }
}
