<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Polaris languages lookup table.
 *
 * Maps Polaris LanguageID values to language descriptions and NCIP values.
 * Used when displaying patron language preference information alongside
 * notification history.
 *
 * @property int         $id                Polaris LanguageID
 * @property string      $language_desc     Human-readable language name (e.g. "English")
 * @property null|string $ncip_value        NCIP protocol language code
 * @property null|int    $admin_language_id Parent administrative language ID
 */
class Language extends Model
{
    public $incrementing = false;
    protected $fillable  = ['id', 'language_desc', 'ncip_value', 'admin_language_id'];

    protected $casts = [
        'id'                => 'integer',
        'admin_language_id' => 'integer',
    ];

    public function patrons(): HasMany
    {
        return $this->hasMany(Patron::class, 'language_id');
    }
}
