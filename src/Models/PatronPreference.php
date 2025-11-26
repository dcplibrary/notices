<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PatronPreference extends Model
{
    protected $table = 'patron_preferences';

    protected $fillable = [
        'patron_barcode',
        'patron_id',
        'opt_out_voice',
        'opt_out_text',
        'opt_out_email',
        'opt_out_all',
        'channel_preferences',
        'preferred_channel',
        'phone_number',
        'email',
        'language',
        'notify_holds',
        'notify_overdues',
        'notify_renewals',
        'notify_bills',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'max_daily_notifications',
        'overdue_reminder_frequency_days',
        'preferences_updated_at',
        'updated_by',
        'update_source',
    ];

    protected $casts = [
        'opt_out_voice' => 'boolean',
        'opt_out_text' => 'boolean',
        'opt_out_email' => 'boolean',
        'opt_out_all' => 'boolean',
        'notify_holds' => 'boolean',
        'notify_overdues' => 'boolean',
        'notify_renewals' => 'boolean',
        'notify_bills' => 'boolean',
        'channel_preferences' => 'array',
        'max_daily_notifications' => 'integer',
        'overdue_reminder_frequency_days' => 'integer',
        'preferences_updated_at' => 'datetime',
    ];

    /**
     * Check if patron has opted out of a specific channel.
     */
    public function hasOptedOut(string $channel): bool
    {
        if ($this->opt_out_all) {
            return true;
        }

        return match(strtolower($channel)) {
            'voice' => $this->opt_out_voice,
            'text' => $this->opt_out_text,
            'email' => $this->opt_out_email,
            default => false,
        };
    }

    /**
     * Check if patron wants notifications for a specific type.
     */
    public function wantsNotificationType(string $type): bool
    {
        return match(strtolower($type)) {
            'holds', 'hold' => $this->notify_holds,
            'overdue', 'overdues' => $this->notify_overdues,
            'renew', 'renewal', 'renewals' => $this->notify_renewals,
            'bill', 'bills' => $this->notify_bills,
            default => true,
        };
    }

    /**
     * Get available channels for this patron.
     */
    public function getAvailableChannels(): array
    {
        $channels = [];

        if (!$this->opt_out_all) {
            if (!$this->opt_out_voice) {
                $channels[] = 'voice';
            }
            if (!$this->opt_out_text) {
                $channels[] = 'text';
            }
            if (!$this->opt_out_email) {
                $channels[] = 'email';
            }
        }

        return $channels;
    }

    /**
     * Get preferred channels in order.
     */
    public function getPreferredChannels(): array
    {
        // Use explicit preference array if set
        if (!empty($this->channel_preferences)) {
            return array_filter($this->channel_preferences, function ($channel) {
                return !$this->hasOptedOut($channel);
            });
        }

        // Fall back to single preferred channel
        if ($this->preferred_channel && !$this->hasOptedOut($this->preferred_channel)) {
            return [$this->preferred_channel];
        }

        // Return all available channels
        return $this->getAvailableChannels();
    }

    /**
     * Check if patron is in quiet hours.
     */
    public function isInQuietHours(?Carbon $time = null): bool
    {
        if (!$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $time = $time ?? now($this->timezone ?? 'America/Chicago');
        $currentTime = $time->format('H:i:s');

        return $currentTime < $this->quiet_hours_start || $currentTime > $this->quiet_hours_end;
    }

    /**
     * Get contact info for a specific channel.
     */
    public function getContactInfo(string $channel): ?string
    {
        return match(strtolower($channel)) {
            'voice', 'text' => $this->phone_number,
            'email' => $this->email,
            default => null,
        };
    }

    /**
     * Check if patron can receive notification now.
     */
    public function canReceiveNotification(string $channel, string $type, ?Carbon $time = null): bool
    {
        // Check global opt-out
        if ($this->opt_out_all) {
            return false;
        }

        // Check channel opt-out
        if ($this->hasOptedOut($channel)) {
            return false;
        }

        // Check notification type preference
        if (!$this->wantsNotificationType($type)) {
            return false;
        }

        // Check quiet hours (for voice/text only)
        if (in_array($channel, ['voice', 'text']) && $this->isInQuietHours($time)) {
            return false;
        }

        return true;
    }

    /**
     * Scope to patrons who have opted out of all notifications.
     */
    public function scopeOptedOutAll(Builder $query): Builder
    {
        return $query->where('opt_out_all', true);
    }

    /**
     * Scope to patrons who can receive voice notifications.
     */
    public function scopeCanReceiveVoice(Builder $query): Builder
    {
        return $query->where('opt_out_all', false)
            ->where('opt_out_voice', false);
    }

    /**
     * Scope to patrons who can receive text notifications.
     */
    public function scopeCanReceiveText(Builder $query): Builder
    {
        return $query->where('opt_out_all', false)
            ->where('opt_out_text', false);
    }

    /**
     * Scope to patrons who can receive email notifications.
     */
    public function scopeCanReceiveEmail(Builder $query): Builder
    {
        return $query->where('opt_out_all', false)
            ->where('opt_out_email', false);
    }

    /**
     * Update preferences and track audit info.
     */
    public function updatePreferences(array $preferences, string $updatedBy = 'system', string $source = 'api'): void
    {
        $this->fill($preferences);
        $this->preferences_updated_at = now();
        $this->updated_by = $updatedBy;
        $this->update_source = $source;
        $this->save();
    }
}
