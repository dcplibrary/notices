<?php

return [
    // Whether the notices routes/UI should require authentication. Kept for future use.
    'require_auth' => env('NOTICES_REQUIRE_AUTH', true),

    // Microsoft Graph API configuration for email ingestion.
    'microsoft' => [
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'mailbox' => env('MICROSOFT_MAILBOX', 'notices@example.org'),

        // Optional: restrict ingestion to specific sender addresses (comma-separated).
        'email_from' => env('MICROSOFT_EMAIL_FROM'),

        'folders' => [
            'inbox' => env('MICROSOFT_FOLDER_INBOX', 'Inbox'),
            'shoutbomb' => env('MICROSOFT_FOLDER_SHOUTBOMB', 'Shoutbomb_Reports'),
        ],
    ],

'polaris' => [
        // Local filesystem base path where we mirror Polaris/Shoutbomb exports.
        'export_path' => env('POLARIS_EXPORT_PATH', storage_path('app/notices-exports')),

        // Optional: path where another process has already placed exported files
        // (for example, a SQL job or external FTP sync). If set, this path is
        // checked first for holds_YYYY-MM-DD_*.txt, etc., before using FTP.
'import_path' => env('POLARIS_FTP_IMPORT_PATH', 'storage/logs'),

        // If true, fetch exports from the Polaris FTP server into export_path before importing.
        'use_ftp' => env('POLARIS_USE_FTP', false),

        'ftp' => [
            'host' => env('POLARIS_FTP_HOST'),
            'port' => (int) env('POLARIS_FTP_PORT', 21),
            'username' => env('POLARIS_FTP_USERNAME'),
            'password' => env('POLARIS_FTP_PASSWORD'),
            'root' => env('POLARIS_FTP_PATH', '/'),
            'ssl' => env('POLARIS_FTP_SSL', false),
            'passive' => env('POLARIS_FTP_PASSIVE', true),
            'timeout' => (int) env('POLARIS_FTP_TIMEOUT', 30),
        ],

        // Simple filename patterns (FTP root and local export_path).
        'file_patterns' => [
            'holds' => 'holds_*_*.txt',
            'overdue' => 'overdue_*_*.txt',
            'renew' => 'renew_*_*.txt',
            'notification_log' => 'notification_log_*_*.txt',
            'notification_history' => 'notification_history_*_*.txt',
            'phonenotices' => 'PhoneNotices*.csv',
        ],
    ],

'import' => [
        // Where downloaded FTP exports are stored locally before import.
        'local_storage' => env('NOTICES_IMPORT_STORAGE', storage_path('app/notices-exports')),
        'delimiter' => '|',
        'encoding' => 'UTF-8',
    ],

    // Reusable UI/status messages. These can be overridden in the database
    // for site-specific wording, but this array provides sane defaults for
    // new installs and tests.
'ui_messages' => [
        // SMS (Shoutbomb)
        'sms_invalid_phone'      => 'Phone number appears to be invalid. Update the phone number or switch to another notification option (Email, Voice, or Mail).',
        'sms_opt_out'            => 'Patron has opted out of receiving SMS messages. Update the notification option or use another delivery method.',
        'sms_landline'           => 'This phone number is detected as a landline and cannot receive SMS. Update the number or switch to Voice or another method.',
        'sms_carrier_issue'      => 'The mobile carrier reported a problem delivering SMS messages to this number. Try again later or use a different method.',
        'sms_delivery_failure'   => 'SMS delivery failed multiple times. Check the phone number and notification option for this patron.',

        // Voice (Shoutbomb)
        'voice_delivery_failure' => 'Voice call delivery failed. The patron\'s phone number may need to be verified in Polaris.',
        'voice_no_answer'        => 'The phone call was not answered. The patron may need to update their phone number or notification preference.',

        // Email
        'email_invalid_address'  => 'Email address appears to be invalid. Update the email address or choose a different notification option.',
        'email_bounce'           => 'Email may have bounced or was not accepted by the recipient. Verify the email address and mailbox.',
        'email_delivery_failure' => 'Email delivery failed. Check the patron\'s email address and notification preferences.',

        // LX Starter / system-level
        'lx_starter_failure'     => 'System-level LX Starter communication failure cluster, not a patron contact problem.',

        // Preference / config issues
        'null_preference'        => 'Patron has no valid notification option configured. Select at least one delivery method in Polaris.',
        'failed_contact_string'  => 'This contact information has failed for multiple notifications. Update the contact or change the notification method.',
    ],

    // Viewer Portal Messages - displayed in the user-facing notification status viewer.
    // One message per status/delivery method combination.
    'viewer_messages' => [
        // Delivered
        'viewer_delivered_email' => 'This notification was successfully delivered to the patron\'s email address.',
        'viewer_delivered_voice' => 'This notification was successfully delivered via phone call.',
        'viewer_delivered_sms'   => 'This notification was successfully delivered via text message.',
        'viewer_delivered_mail'  => 'This notification was successfully printed for mailing.',

        // Pending
        'viewer_pending_email'   => 'This notification is being processed for email delivery. Please check back later.',
        'viewer_pending_voice'   => 'This notification is being processed for phone delivery. Please check back later.',
        'viewer_pending_sms'     => 'This notification is being processed for text message delivery. Please check back later.',
        'viewer_pending_mail'    => 'This notification is being processed for mail delivery. Please check back later.',

        // Failed
        'viewer_failed_email'    => 'Email delivery failed. The patron\'s email address may need to be updated in Polaris.',
        'viewer_failed_voice'    => 'Phone delivery failed. The patron\'s phone number may need to be verified in Polaris.',
        'viewer_failed_sms'      => 'Text message delivery failed. The patron\'s phone number may need to be updated, or they may have opted out of text messages.',
        'viewer_failed_mail'     => 'Mail delivery could not be processed. Please verify the patron\'s mailing address in Polaris.',
    ],

    // Timezone for Polaris export file timestamps
    // Export filenames use this timezone (e.g., holds_2026-01-04_09-05-01.txt)
    'timezone' => env('NOTICES_TIMEZONE', 'America/Chicago'),

    /*
    |--------------------------------------------------------------------------
    | Query Performance Logging
    |--------------------------------------------------------------------------
    |
    | Enable query performance logging to track slow database queries in
    | application logs. Queries exceeding the threshold will be logged.
    |
    | - query_logging_enabled: Set to true to enable query logging
    | - query_logging_threshold: Time threshold in milliseconds (default: 100ms)
    | - query_logging_channel: Laravel logging channel to use (default: 'stack')
    |
    */
    'query_logging_enabled' => env('NOTICES_QUERY_LOGGING', false),
    'query_logging_threshold' => env('NOTICES_QUERY_THRESHOLD_MS', 100),
    'query_logging_channel' => env('NOTICES_QUERY_LOG_CHANNEL', 'stack'),
];
