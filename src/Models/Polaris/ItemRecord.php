<?php

namespace Dcplibrary\Notices\Models\Polaris;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris Item Record Model.
 *
 * Queries item/bibliographic data from Polaris ILS database.
 * Read-only model for displaying item information in notification details.
 */
class ItemRecord extends Model
{
    protected $connection = 'polaris';

    protected $table = 'Polaris.Polaris.CircItemRecords';

    protected $primaryKey = 'ItemRecordID';

    public $timestamps = false;

    protected $casts = [
        'ItemRecordID' => 'integer',
        'Barcode' => 'string',
        'BibliographicRecordID' => 'integer',
        'CallNumber' => 'string',
        'Price' => 'decimal:2',
    ];

    /**
     * Get the bibliographic record (book title, author, etc.).
     */
    public function bibliographic()
    {
        return $this->belongsTo(BibliographicRecord::class, 'BibliographicRecordID', 'BibliographicRecordID');
    }

    /**
     * Get link to item record in Polaris staff interface.
     */
    public function getStaffLinkAttribute(): string
    {
        return "https://catalog.dcplibrary.org/leapwebapp/staff/default#itemrecords/{$this->ItemRecordID}";
    }

    /**
     * Scope to find by barcode.
     */
    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('Barcode', $barcode);
    }

    /**
     * Scope to find by item ID.
     */
    public function scopeByItemId($query, int $itemId)
    {
        return $query->where('ItemRecordID', $itemId);
    }
}
