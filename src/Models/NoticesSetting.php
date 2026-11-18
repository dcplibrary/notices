<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single saved Notices setting, keyed by a dotted name.
 *
 * @property int            $id
 * @property string         $key
 * @property null|string    $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class NoticesSetting extends Model
{
    protected $table = 'notices_settings';

    protected $guarded = [];
}
