<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris statistical_codes lookup table (composite primary key).
 *
 * Maps (StatisticalCodeID, OrganizationID) pairs to human-readable descriptions.
 * Statistical codes are branch-scoped, so the same code ID can have different
 * meanings at different library branches.
 *
 * @property int    $statistical_code_id Part of composite primary key
 * @property int    $organization_id     Part of composite primary key (branch/org ID)
 * @property string $description         Human-readable code description
 */
class StatisticalCode extends Model
{
    public $incrementing = false;
    protected $fillable  = ['statistical_code_id', 'organization_id', 'description'];

    protected $primaryKey = ['statistical_code_id', 'organization_id'];

    protected $casts = [
        'statistical_code_id' => 'integer',
        'organization_id'     => 'integer',
    ];
}
