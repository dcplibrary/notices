<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory;

    protected $table = 'import_jobs';

    protected $fillable = [
        'source_type',
        'started_at',
        'finished_at',
        'status',
        'options_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'options_json' => 'array',
    ];

    public function files()
    {
        return $this->hasMany(ImportFile::class);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }
}
