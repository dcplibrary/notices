<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris material_types lookup table.
 *
 * Maps Polaris MaterialTypeID values to descriptions and optional age
 * restrictions. Used when displaying item details in notification views.
 *
 * @property int      $id          Polaris MaterialTypeID
 * @property string   $description Human-readable material type (e.g. "Book", "DVD")
 * @property null|int $minimum_age Minimum patron age required for this material type
 */
class MaterialType extends Model
{
    public $incrementing = false;
    protected $fillable  = ['id', 'description', 'minimum_age'];

    protected $casts = [
        'id'          => 'integer',
        'minimum_age' => 'integer',
    ];
}
