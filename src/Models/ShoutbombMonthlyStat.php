<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ShoutbombMonthlyStat Model
 *
 * Stores aggregate statistics from Shoutbomb monthly email reports.
 * Parsed from "Shoutbomb Rpt [Month]" emails via notices:import-shoutbomb-email
 */
class ShoutbombMonthlyStat extends Model
{
    protected $table = 'shoutbomb_monthly_stats';

    protected $fillable = [
        // Email source
        'outlook_message_id',
        'subject',

        // Report period
        'report_month',
        'branch_name',

        // Hold notifications
        'hold_text_notices',
        'hold_text_reminders',
        'hold_voice_notices',
        'hold_voice_reminders',

        // Overdue notifications (text)
        'overdue_text_notices',
        'overdue_text_eligible_renewal',
        'overdue_text_ineligible_renewal',

        // Overdue notifications (voice)
        'overdue_voice_notices',
        'overdue_voice_eligible_renewal',
        'overdue_voice_ineligible_renewal',

        // Pre-due notifications
        'predue_text_notices',
        'predue_voice_notices',

        // Fine notifications
        'fine_text_notices',
        'fine_voice_notices',

        // Bill notifications
        'bill_text_notices',
        'bill_voice_notices',

        // Delivery statistics
        'total_text_sent',
        'total_voice_sent',
        'total_text_delivered',
        'total_voice_delivered',
        'total_text_failed',
        'total_voice_failed',

        // Registration statistics
        'total_registered_users',
        'total_registered_text',
        'total_registered_voice',
        'new_registrations',
        'unsubscribes',
        'invalid_numbers_count',

        // Keyword usage
        'keyword_usage',

        // Processing timestamps
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'report_month' => 'date',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'keyword_usage' => 'array',
        'hold_text_notices' => 'integer',
        'hold_text_reminders' => 'integer',
        'hold_voice_notices' => 'integer',
        'hold_voice_reminders' => 'integer',
        'overdue_text_notices' => 'integer',
        'overdue_text_eligible_renewal' => 'integer',
        'overdue_text_ineligible_renewal' => 'integer',
        'overdue_voice_notices' => 'integer',
        'overdue_voice_eligible_renewal' => 'integer',
        'overdue_voice_ineligible_renewal' => 'integer',
        'predue_text_notices' => 'integer',
        'predue_voice_notices' => 'integer',
        'fine_text_notices' => 'integer',
        'fine_voice_notices' => 'integer',
        'bill_text_notices' => 'integer',
        'bill_voice_notices' => 'integer',
        'total_text_sent' => 'integer',
        'total_voice_sent' => 'integer',
        'total_text_delivered' => 'integer',
        'total_voice_delivered' => 'integer',
        'total_text_failed' => 'integer',
        'total_voice_failed' => 'integer',
        'total_registered_users' => 'integer',
        'total_registered_text' => 'integer',
        'total_registered_voice' => 'integer',
        'new_registrations' => 'integer',
        'unsubscribes' => 'integer',
        'invalid_numbers_count' => 'integer',
    ];

    /**
     * Scope to filter by month.
     */
    public function scopeForMonth($query, $date)
    {
        return $query->where('report_month', $date);
    }

    /**
     * Scope to get recent reports.
     */
    public function scopeRecent($query, int $months = 12)
    {
        return $query->where('report_month', '>=', now()->subMonths($months)->startOfMonth());
    }

    /**
     * Scope to filter by branch.
     */
    public function scopeForBranch($query, string $branchName)
    {
        return $query->where('branch_name', $branchName);
    }

    /**
     * Get total notifications sent (text + voice).
     */
    public function getTotalSentAttribute(): int
    {
        return $this->total_text_sent + $this->total_voice_sent;
    }

    /**
     * Get total notifications delivered.
     */
    public function getTotalDeliveredAttribute(): int
    {
        return $this->total_text_delivered + $this->total_voice_delivered;
    }

    /**
     * Get total failures.
     */
    public function getTotalFailedAttribute(): int
    {
        return $this->total_text_failed + $this->total_voice_failed;
    }

    /**
     * Get delivery success rate as percentage.
     */
    public function getDeliveryRateAttribute(): float
    {
        $total = $this->total_sent;
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->total_delivered / $total) * 100, 2);
    }
}
