<?php

namespace Dcplibrary\Notices\Models\Polaris;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris Patron Model.
 *
 * Queries patron data directly from the Polaris ILS database.
 * Read-only model for displaying patron information.
 */
class Patron extends Model
{
    protected $connection = 'polaris';

    protected $table = 'Polaris.Polaris.PatronRegistration';

    protected $primaryKey = 'PatronID';

    public $timestamps = false;

    protected $casts = [
        'Barcode' => 'string',
        'PatronID' => 'integer',
        'NameFirst' => 'string',
        'NameLast' => 'string',
        'NameMiddle' => 'string',
        'PhoneVoice1' => 'string',
        'PhoneVoice2' => 'string',
        'PhoneVoice3' => 'string',
        'EmailAddress' => 'string',
        'ExpirationDate' => 'datetime',
        'RegistrationDate' => 'datetime',
    ];

    /**
     * Get the full name of the patron.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->NameFirst,
            $this->NameMiddle,
            $this->NameLast,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Get the formatted name (Last, First).
     */
    public function getFormattedNameAttribute(): string
    {
        if ($this->NameFirst && $this->NameLast) {
            return "{$this->NameLast}, {$this->NameFirst}";
        }

        return $this->FullName;
    }

    /**
     * Get the primary phone number (PhoneVoice1).
     */
    public function getPrimaryPhoneAttribute(): ?string
    {
        return $this->PhoneVoice1;
    }

    /**
     * Get link to patron record in Polaris staff interface.
     */
    public function getStaffLinkAttribute(): string
    {
        return "https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/{$this->PatronID}/record";
    }

    /**
     * Scope to find by barcode.
     */
    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('Barcode', $barcode);
    }

    /**
     * Scope to find by patron ID.
     */
    public function scopeByPatronId($query, int $patronId)
    {
        return $query->where('PatronID', $patronId);
    }
}
