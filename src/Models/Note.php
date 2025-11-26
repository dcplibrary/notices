<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $table = 'notes';

    protected $fillable = [
        'noteable_type',
        'noteable_id',
        'user_id',
        'body',
    ];

    public function noteable()
    {
        return $this->morphTo();
    }
}
