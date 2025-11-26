<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * PatronNotificationPreference Model.
 *
 * Stores patron notification preferences from daily patron list exports.
 * The file name indicates the delivery method:
 *   - voice_patrons*.txt → delivery_option_id = 3 (Voice calls)
 *   - text_patrons*.txt  → delivery_option_id = 8 (SMS)
 */
class PatronNotificationPreference extends Model
{
    protected $table = 'patrons_notification_preferences';

    protected $fillable = [
        'patron_barcode',
        'phone_voice1',
        'delivery_method',
        'delivery_option_id',
        'import_date',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'delivery_option_id' => 'integer',
        'import_date' => 'date',
        'imported_at' => 'datetime',
    ];

    /**
     * Scope to filter by delivery method.
     */
    public function scopeVoice($query)
    {
        return $query->where('delivery_method', 'voice');
    }

    public function scopeText($query)
    {
        return $query->where('delivery_method', 'text');
    }

    /**
     * Scope to filter by patron.
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
     * Scope to filter by date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('import_date', $date);
    }

    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('import_date', [$startDate, $endDate]);
    }

    /**
     * Check if this is a voice preference.
     */
    public function isVoice(): bool
    {
        return $this->delivery_method === 'voice' || $this->delivery_option_id === 3;
    }

    /**
     * Check if this is a text/SMS preference.
     */
    public function isText(): bool
    {
        return $this->delivery_method === 'text' || $this->delivery_option_id === 8;
    }

    /**
     * Get the delivery option ID based on delivery method.
     */
    public static function getDeliveryOptionId(string $deliveryMethod): int
    {
        return match ($deliveryMethod) {
            'voice' => 3,
            'text' => 8,
            default => throw new InvalidArgumentException("Unknown delivery method: {$deliveryMethod}"),
        };
    }
}
