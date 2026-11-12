<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Polaris patron_codes lookup table.
 *
 * Maps Polaris PatronCodeID values to descriptions (e.g. "Adult", "Juvenile",
 * "Staff"). Used to segment notification statistics and filter patron lists.
 *
 * @property int    $id          Polaris PatronCodeID
 * @property string $description Human-readable patron code label
 */
class PatronCode extends Model
{
    public $incrementing = false;
    protected $fillable  = ['id', 'description'];

    protected $casts = [
        'id' => 'integer',
    ];

    public function patrons(): HasMany
    {
        return $this->hasMany(Patron::class, 'patron_code_id');
    }
}
