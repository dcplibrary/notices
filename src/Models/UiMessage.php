<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurable UI message displayed in the notices interface.
 *
 * UiMessages allow administrators to surface contextual banners, help text,
 * or alerts in specific areas of the notices UI without a code deploy.
 * Each message is keyed by a `code` and can be toggled on/off via the
 * settings panel.
 *
 * @property int            $id
 * @property string         $code       Unique identifier used in views (e.g. "dashboard_banner")
 * @property null|string    $message    HTML or plain-text content to display
 * @property bool           $is_enabled Whether the message is currently visible
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UiMessage extends Model
{
    protected $table = 'ui_messages';

    protected $guarded = [];
}
