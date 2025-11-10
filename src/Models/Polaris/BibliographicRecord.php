<?php

namespace Dcplibrary\Notices\Models\Polaris;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris Bibliographic Record Model
 *
 * Queries bibliographic data (titles, authors, etc.) from Polaris ILS database.
 * Read-only model for displaying book/media information.
 */
class BibliographicRecord extends Model
{
    protected $connection = 'polaris';
    protected $table = 'Polaris.Polaris.BibliographicRecords';
    protected $primaryKey = 'BibliographicRecordID';
    public $timestamps = false;

    protected $casts = [
        'BibliographicRecordID' => 'integer',
        'Title' => 'string',
        'Author' => 'string',
        'PublicationYear' => 'string',
        'Publisher' => 'string',
        'ISBN' => 'string',
    ];

    /**
     * Get related items.
     */
    public function items()
    {
        return $this->hasMany(ItemRecord::class, 'BibliographicRecordID', 'BibliographicRecordID');
    }

    /**
     * Get formatted title with author.
     */
    public function getFullTitleAttribute(): string
    {
        if ($this->Author) {
            return "{$this->Title} / {$this->Author}";
        }

        return $this->Title ?? 'Unknown Title';
    }

    /**
     * Scope to search by title.
     */
    public function scopeByTitle($query, string $title)
    {
        return $query->where('Title', 'like', "%{$title}%");
    }
}
