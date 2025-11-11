<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryMethod extends Model
{
    use HasFactory, SoftDeletes;

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
        'label',
        'enabled',
        'display_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'enabled' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Scope to get only enabled delivery methods.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to order by display_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('delivery_option');
    }

    /**
     * Get the display name (label if available, otherwise delivery_option).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: $this->delivery_option;
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
