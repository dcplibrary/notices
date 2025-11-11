<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryMethod extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'delivery_methods';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'delivery_option_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'delivery_option_id',
        'delivery_option',
        'description',
        'active',
        'display_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Scope to get only active delivery methods.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Get notifications using this delivery method.
     */
    public function notifications()
    {
        return $this->hasMany(NotificationLog::class, 'delivery_option_id', 'delivery_option_id');
    }

    /**
     * Find delivery method by Polaris delivery_option_id.
     */
    public static function findByDeliveryOptionId($optionId)
    {
        return static::where('delivery_option_id', $optionId)->first();
    }
}
