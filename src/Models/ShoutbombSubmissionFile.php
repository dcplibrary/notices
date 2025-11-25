<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShoutbombSubmissionFile extends Model
{
    use HasFactory;

    protected $table = 'shoutbomb_submission_files';

    protected $fillable = [
        'submission_id',
        'import_file_id',
    ];

    public function submission()
    {
        return $this->belongsTo(ShoutbombSubmission::class, 'submission_id');
    }

    public function importFile()
    {
        return $this->belongsTo(ImportFile::class, 'import_file_id');
    }
}
