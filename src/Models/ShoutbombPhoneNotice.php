<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ShoutbombPhoneNotice extends Model
{
    protected $table = 'shoutbomb_phone_notices';

    protected $fillable = [
        'delivery_type',
        'language',
        'patron_barcode',
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'library_code',
        'library_name',
        'item_barcode',
        'notice_date',
        'title',
        'organization_code',
        'language_code',
        'patron_id',
        'item_record_id',
        'bib_record_id',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'imported_at' => 'datetime',
        'patron_id' => 'integer',
        'item_record_id' => 'integer',
        'bib_record_id' => 'integer',
    ];

    /**
     * Scope to filter by delivery type.
     */
    public function scopeVoice($query)
    {
        return $query->where('delivery_type', 'voice');
    }

    public function scopeText($query)
    {
        return $query->where('delivery_type', 'text');
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('notice_date', '>=', now()->subDays($days));
    }

    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('notice_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by patron.
     */
    public function scopeForPatron($query, string $barcode)
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Scope to filter by phone number.
     */
    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone_number', $phone);
    }

    /**
     * Scope to filter by library.
     */
    public function scopeForLibrary($query, string $code)
    {
        return $query->where('library_code', $code);
    }

    /**
     * Get the full patron name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
