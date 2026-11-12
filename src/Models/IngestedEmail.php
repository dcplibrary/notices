<?php

namespace Dcplibrary\Notices\Models;

use Dcplibrary\Notices\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Email failure report ingested from Microsoft Graph API.
 *
 * Stores complete email metadata and body content for audit trail
 * and debugging. Links to notifications that were created or updated
 * from this email.
 */
class IngestedEmail extends Model
{
    use HasUtcTimestamps;

    protected $table = 'ingested_emails';

    protected $guarded = [];

    protected $casts = [
        'raw_metadata'         => 'array',
        'attachments_count'    => 'integer',
        'received_at'          => 'datetime',
        'imported_at'          => 'datetime',
        'parsed_data'          => 'array',
        'parsed_record_count'  => 'integer',
        'processed_at'         => 'datetime',
        'report_date'          => 'date',
        'failures_count'       => 'integer',
        'invalid_phones_count' => 'integer',
    ];

    /**
     * Get notifications that were created/updated from this email.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'ingested_email_id');
    }

    /**
     * Get the email report type.
     */
    public function emailReportType()
    {
        return $this->belongsTo(EmailReportType::class, 'email_report_type_id');
    }

    /**
     * Get truncated body preview for list views.
     */
    public function getBodyPreviewAttribute(): ?string
    {
        if ($this->attributes['body_preview']) {
            return $this->attributes['body_preview'];
        }

        if ($this->attributes['body_full']) {
            return substr($this->attributes['body_full'], 0, 500);
        }

        return null;
    }

    /**
     * Check if this email has a full body stored.
     */
    public function hasFullBody(): bool
    {
        return !empty($this->attributes['body_full']);
    }

    /**
     * Get sender name from email address if available.
     */
    public function getSenderNameAttribute(): string
    {
        if (!$this->sender_email) {
            return 'Unknown';
        }

        // Extract name from email like "Name <email@example.com>" or just return email
        if (preg_match('/^(.+?)\s*<.+>$/', $this->sender_email, $matches)) {
            return trim($matches[1], '" ');
        }

        return explode('@', $this->sender_email)[0];
    }

    /**
     * Database stores timestamps in UTC. Convert to application timezone.
     */
    protected function receivedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function importedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function processedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }
}
