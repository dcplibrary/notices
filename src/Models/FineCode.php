<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris fine_codes lookup table.
 *
 * Maps Polaris FineCodeID values to their human-readable descriptions.
 * Used when displaying overdue/fine notification details.
 *
 * @property int    $id          Polaris FineCodeID
 * @property string $description Human-readable description of the fine code
 */
class FineCode extends Model
{
    public $incrementing = false;
    protected $fillable  = ['id', 'description'];

    protected $casts = [
        'id' => 'integer',
    ];
}
