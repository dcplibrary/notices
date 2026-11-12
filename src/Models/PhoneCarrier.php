<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mobile phone carrier with SMS gateway information.
 *
 * PhoneCarrier records are used to construct SMS email gateway addresses
 * (e.g. 10digitnumber@txt.att.net) when sending notifications to patrons
 * who have a carrier-specific SMS address rather than a true SMS number.
 * Carriers can be activated or deactivated independently.
 *
 * @property int            $id          Auto-increment primary key
 * @property string         $name        Carrier display name (e.g. "AT&T")
 * @property null|string    $sms_gateway Email gateway domain (e.g. "@txt.att.net")
 * @property bool           $active      Whether this carrier is currently in use
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PhoneCarrier extends Model
{
    protected $fillable = [
        'name',
        'sms_gateway',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Patrons using this carrier for phone 1.
     */
    public function patronsPhone1(): HasMany
    {
        return $this->hasMany(Patron::class, 'phone1_carrier_id');
    }

    /**
     * Patrons using this carrier for phone 2.
     */
    public function patronsPhone2(): HasMany
    {
        return $this->hasMany(Patron::class, 'phone2_carrier_id');
    }

    /**
     * Patrons using this carrier for phone 3.
     */
    public function patronsPhone3(): HasMany
    {
        return $this->hasMany(Patron::class, 'phone3_carrier_id');
    }

    /**
     * Get SMS email address for a phone number.
     */
    public function getSmsEmail(?string $phoneNumber): ?string
    {
        if (!$this->sms_gateway || !$phoneNumber) {
            return null;
        }

        // Strip all non-digits from phone number
        $cleanPhone = preg_replace('/\D/', '', $phoneNumber);

        return $cleanPhone . $this->sms_gateway;
    }
}
