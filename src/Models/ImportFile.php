<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportFile extends Model
{
    use HasFactory;

    protected $table = 'import_files';

    protected $fillable = [
        'import_job_id',
        'filename',
        'logical_date',
        'records_imported',
        'records_skipped',
        'checksum',
    ];

    protected $casts = [
        'logical_date' => 'date',
    ];

    public function job()
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
