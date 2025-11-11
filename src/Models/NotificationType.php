<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationType extends Model
{
    use HasFactory;

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
    ];

    /**
     * Get notifications of this type.
     */
    public function notifications()
    {
        return $this->hasMany(NotificationLog::class, 'notification_type_id', 'notification_type_id');
    }
}
