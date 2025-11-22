<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationStatus extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'notification_statuses';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'notification_status_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'notification_status_id',
        'description',
        'label',
        'enabled',
        'display_order',
        'category',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'enabled' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Scope to get only enabled statuses.
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
     * Scope to get statuses by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get notifications with this status.
     */
    public function notifications()
    {
        return $this->hasMany(NotificationLog::class, 'notification_status_id', 'notification_status_id');
    }

    /**
     * Scope to get email statuses (12-14).
     */
    public function scopeEmailStatuses($query)
    {
        return $query->whereIn('notification_status_id', [12, 13, 14]);
    }

    /**
     * Scope to get voice statuses (1-11).
     */
    public function scopeVoiceStatuses($query)
    {
        return $query->whereIn('notification_status_id', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
    }

    /**
     * Scope to get successful statuses (12, 15, 16).
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('notification_status_id', [12, 15, 16]);
    }

    /**
     * Scope to get failed statuses.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('notification_status_id', [13, 14]);
    }
}
