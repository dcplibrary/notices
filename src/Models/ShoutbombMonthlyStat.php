<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model for Shoutbomb monthly statistics reports.
 * Stores comprehensive monthly statistics from Shoutbomb report emails.
 *
 * This was migrated from dcplibrary/shoutbomb-reports package to consolidate
 * all notice-related data models in one place.
 */
class ShoutbombMonthlyStat extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shoutbomb_monthly_stats';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'outlook_message_id',
        'subject',
        'report_month',
        'branch_name',
        'hold_text_notices',
        'hold_text_reminders',
        'hold_voice_notices',
        'hold_voice_reminders',
        'overdue_text_notices',
        'overdue_text_eligible_renewal',
        'overdue_text_ineligible_renewal',
        'overdue_text_renewed_successfully',
        'overdue_text_renewed_unsuccessfully',
        'overdue_voice_notices',
        'overdue_voice_eligible_renewal',
        'overdue_voice_ineligible_renewal',
        'renewal_text_notices',
        'renewal_text_eligible',
        'renewal_text_ineligible',
        'renewal_text_unsuccessfully',
        'renewal_text_reminders',
        'renewal_text_reminder_eligible',
        'renewal_text_reminder_ineligible',
        'renewal_voice_notices',
        'renewal_voice_eligible',
        'renewal_voice_ineligible',
        'renewal_voice_reminders',
        'renewal_voice_reminder_eligible',
        'renewal_voice_reminder_ineligible',
        'total_registered_users',
        'total_registered_barcodes',
        'total_registered_text',
        'total_registered_voice',
        'new_registrations_month',
        'new_voice_signups',
        'new_text_signups',
        'average_daily_calls',
        'keyword_usage',
        'received_at',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     */
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
        'overdue_text_renewed_successfully' => 'integer',
        'overdue_text_renewed_unsuccessfully' => 'integer',
        'overdue_voice_notices' => 'integer',
        'overdue_voice_eligible_renewal' => 'integer',
        'overdue_voice_ineligible_renewal' => 'integer',
        'renewal_text_notices' => 'integer',
        'renewal_text_eligible' => 'integer',
        'renewal_text_ineligible' => 'integer',
        'renewal_text_unsuccessfully' => 'integer',
        'renewal_text_reminders' => 'integer',
        'renewal_text_reminder_eligible' => 'integer',
        'renewal_text_reminder_ineligible' => 'integer',
        'renewal_voice_notices' => 'integer',
        'renewal_voice_eligible' => 'integer',
        'renewal_voice_ineligible' => 'integer',
        'renewal_voice_reminders' => 'integer',
        'renewal_voice_reminder_eligible' => 'integer',
        'renewal_voice_reminder_ineligible' => 'integer',
        'total_registered_users' => 'integer',
        'total_registered_barcodes' => 'integer',
        'total_registered_text' => 'integer',
        'total_registered_voice' => 'integer',
        'new_registrations_month' => 'integer',
        'new_voice_signups' => 'integer',
        'new_text_signups' => 'integer',
        'average_daily_calls' => 'integer',
    ];

    /**
     * Scope to get stats for a specific month.
     */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('report_month', $year)
                     ->whereMonth('report_month', $month);
    }

    /**
     * Scope to get stats for a specific branch.
     */
    public function scopeForBranch(Builder $query, string $branchName): Builder
    {
        return $query->where('branch_name', $branchName);
    }

    /**
     * Scope to get recent stats.
     */
    public function scopeRecent(Builder $query, int $months = 6): Builder
    {
        return $query->where('report_month', '>=', now()->subMonths($months));
    }

    /**
     * Scope to get unprocessed stats.
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope to get stats ordered by month descending.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('report_month', 'desc');
    }

    /**
     * Mark this stat as processed.
     */
    public function markAsProcessed(): bool
    {
        $this->processed_at = now();
        return $this->save();
    }

    /**
     * Get total hold notices (text + voice).
     */
    public function getTotalHoldNoticesAttribute(): int
    {
        return ($this->hold_text_notices ?? 0) + ($this->hold_voice_notices ?? 0);
    }

    /**
     * Get total overdue notices (text + voice).
     */
    public function getTotalOverdueNoticesAttribute(): int
    {
        return ($this->overdue_text_notices ?? 0) + ($this->overdue_voice_notices ?? 0);
    }

    /**
     * Get total renewal notices (text + voice).
     */
    public function getTotalRenewalNoticesAttribute(): int
    {
        return ($this->renewal_text_notices ?? 0) + ($this->renewal_voice_notices ?? 0);
    }

    /**
     * Get total notices sent for the month.
     */
    public function getTotalNoticesAttribute(): int
    {
        return $this->total_hold_notices + $this->total_overdue_notices + $this->total_renewal_notices;
    }

    /**
     * Get percentage of text vs voice users.
     */
    public function getTextPercentageAttribute(): ?float
    {
        $total = ($this->total_registered_text ?? 0) + ($this->total_registered_voice ?? 0);

        if ($total === 0) {
            return null;
        }

        return round(($this->total_registered_text / $total) * 100, 2);
    }

    /**
     * Get percentage of voice vs text users.
     */
    public function getVoicePercentageAttribute(): ?float
    {
        $total = ($this->total_registered_text ?? 0) + ($this->total_registered_voice ?? 0);

        if ($total === 0) {
            return null;
        }

        return round(($this->total_registered_voice / $total) * 100, 2);
    }
}
