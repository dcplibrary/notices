<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * PatronDeliveryPreference Model.
 *
 * Tracks the CURRENT delivery preference for each patron, along with
 * change history (when changed and previous preference).
 *
 * This is a "current state" table - one row per patron.
 */
class PatronDeliveryPreference extends Model
{
    protected $table = 'patron_delivery_preferences';

    protected $fillable = [
        'patron_barcode',
        'phone_voice1',
        'current_delivery_method',
        'current_delivery_option_id',
        'previous_delivery_method',
        'previous_delivery_option_id',
        'preference_changed_at',
        'first_seen_at',
        'last_seen_at',
        'last_source_file',
    ];

    protected $casts = [
        'current_delivery_option_id' => 'integer',
        'previous_delivery_option_id' => 'integer',
        'preference_changed_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Update or create a patron's delivery preference.
     *
     * If the delivery method has changed, tracks the previous value
     * and records the change timestamp.
     *
     * @param string $patronBarcode The patron barcode
     * @param string $phoneVoice1 The phone number
     * @param string $deliveryMethod 'voice' or 'text'
     * @param string|null $sourceFile The source filename
     * @return array{preference: PatronDeliveryPreference, changed: bool, is_new: bool}
     */
    public static function updateOrCreateFromPatronList(
        string $patronBarcode,
        string $phoneVoice1,
        string $deliveryMethod,
        ?string $sourceFile = null
    ): array {
        $deliveryOptionId = self::getDeliveryOptionId($deliveryMethod);
        $now = now();

        $existing = self::where('patron_barcode', $patronBarcode)->first();

        if (!$existing) {
            // New patron - first time seen
            $preference = self::create([
                'patron_barcode' => $patronBarcode,
                'phone_voice1' => $phoneVoice1,
                'current_delivery_method' => $deliveryMethod,
                'current_delivery_option_id' => $deliveryOptionId,
                'previous_delivery_method' => null, // Unknown - first time
                'previous_delivery_option_id' => null,
                'preference_changed_at' => null, // No change yet
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'last_source_file' => $sourceFile,
            ]);

            return [
                'preference' => $preference,
                'changed' => false,
                'is_new' => true,
            ];
        }

        // Existing patron - check if delivery method changed
        $changed = false;
        $updates = [
            'phone_voice1' => $phoneVoice1,
            'last_seen_at' => $now,
            'last_source_file' => $sourceFile,
        ];

        if ($existing->current_delivery_method !== $deliveryMethod) {
            // Delivery method has changed!
            $updates['previous_delivery_method'] = $existing->current_delivery_method;
            $updates['previous_delivery_option_id'] = $existing->current_delivery_option_id;
            $updates['current_delivery_method'] = $deliveryMethod;
            $updates['current_delivery_option_id'] = $deliveryOptionId;
            $updates['preference_changed_at'] = $now;
            $changed = true;
        }

        $existing->update($updates);

        return [
            'preference' => $existing->fresh(),
            'changed' => $changed,
            'is_new' => false,
        ];
    }

    /**
     * Scope to filter by current delivery method.
     */
    public function scopeVoice($query)
    {
        return $query->where('current_delivery_method', 'voice');
    }

    public function scopeText($query)
    {
        return $query->where('current_delivery_method', 'text');
    }

    public function scopeUnknown($query)
    {
        return $query->where('current_delivery_method', 'unknown');
    }

    /**
     * Scope to filter by patrons who have changed preferences.
     */
    public function scopeHasChanged($query)
    {
        return $query->whereNotNull('preference_changed_at');
    }

    /**
     * Scope to filter by patrons who changed preferences within a date range.
     */
    public function scopeChangedBetween($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('preference_changed_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by patrons who changed preferences today.
     */
    public function scopeChangedToday($query)
    {
        return $query->whereDate('preference_changed_at', today());
    }

    /**
     * Scope to filter patrons not seen since a given date.
     */
    public function scopeNotSeenSince($query, Carbon $date)
    {
        return $query->where('last_seen_at', '<', $date);
    }

    /**
     * Scope to filter by patron barcode.
     */
    public function scopeForPatron($query, string $barcode)
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Scope to filter by phone.
     */
    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone_voice1', $phone);
    }

    /**
     * Check if this is a voice preference.
     */
    public function isVoice(): bool
    {
        return $this->current_delivery_method === 'voice';
    }

    /**
     * Check if this is a text/SMS preference.
     */
    public function isText(): bool
    {
        return $this->current_delivery_method === 'text';
    }

    /**
     * Check if this patron has ever changed their preference.
     */
    public function hasChangedPreference(): bool
    {
        return $this->preference_changed_at !== null;
    }

    /**
     * Get a human-readable description of the preference change.
     */
    public function getChangeDescription(): ?string
    {
        if (!$this->hasChangedPreference()) {
            return null;
        }

        $prev = $this->previous_delivery_method ?? 'unknown';
        $curr = $this->current_delivery_method;
        $date = $this->preference_changed_at->format('Y-m-d H:i');

        return "{$prev} → {$curr} on {$date}";
    }

    /**
     * Get the delivery option ID for a delivery method.
     */
    public static function getDeliveryOptionId(string $deliveryMethod): ?int
    {
        return match ($deliveryMethod) {
            'voice' => 3,
            'text' => 8,
            'unknown' => null,
            default => null,
        };
    }

    /**
     * Get statistics about preference changes.
     */
    public static function getChangeStatistics(?Carbon $since = null): array
    {
        $query = self::hasChanged();

        if ($since) {
            $query->where('preference_changed_at', '>=', $since);
        }

        $changes = $query->get();

        return [
            'total_changes' => $changes->count(),
            'voice_to_text' => $changes->where('previous_delivery_method', 'voice')
                ->where('current_delivery_method', 'text')->count(),
            'text_to_voice' => $changes->where('previous_delivery_method', 'text')
                ->where('current_delivery_method', 'voice')->count(),
            'unknown_to_voice' => $changes->where('previous_delivery_method', 'unknown')
                ->where('current_delivery_method', 'voice')->count(),
            'unknown_to_text' => $changes->where('previous_delivery_method', 'unknown')
                ->where('current_delivery_method', 'text')->count(),
        ];
    }
}
