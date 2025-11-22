<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationType extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'notification_types';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'notification_type_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'notification_type_id',
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
     * Scope to get only enabled types.
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
        return $query->orderBy('display_order')->orderBy('description');
    }

    /**
     * Get the display name (label if available, otherwise description).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: $this->description;
    }

    /**
     * Get notifications of this type.
     */
    public function notifications()
    {
        return $this->hasMany(NotificationLog::class, 'notification_type_id', 'notification_type_id');
    }
}
