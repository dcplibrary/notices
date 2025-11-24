<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Polaris Database Connection
    |--------------------------------------------------------------------------
    |
    | Configure the MSSQL connection to your Polaris ILS database.
    | This connection is used to import notification logs and related data.
    |
    | Supported drivers:
    | - 'sqlsrv': Microsoft SQL Server driver (Windows, or Linux with Microsoft ODBC)
    | - 'dblib': FreeTDS driver (Linux/macOS - easier installation)
    |
    | For Linux systems, we recommend using 'dblib' with FreeTDS:
    |   sudo apt-get install php8.4-sybase freetds-common
    |   sudo service php8.4-fpm restart
    |
    | Then set POLARIS_DB_DRIVER=dblib in your .env file
    |
    */

    'polaris_connection' => [
        'driver' => env('POLARIS_DB_DRIVER', 'sqlsrv'),
        'host' => env('POLARIS_DB_HOST', 'localhost'),
        'port' => env('POLARIS_DB_PORT', '1433'),
        'database' => env('POLARIS_DB_DATABASE', 'Polaris'),
        'username' => env('POLARIS_DB_USERNAME', ''),
        'password' => env('POLARIS_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Settings
    |--------------------------------------------------------------------------
    |
    | Configure how notifications are imported from Polaris.
    |
    */

    'import' => [
        // Number of days to import by default
        'default_days' => 1,

        // Batch size for inserting records
        'batch_size' => 500,

        // Whether to skip duplicate records
        'skip_duplicates' => true,
    ],

    'patron_import' => [
        'enabled' => env('PATRON_IMPORT_ENABLED', false),
        'skip_processed_files' => env('PATRON_SKIP_PROCESSED', true),
        'batch_size' => env('PATRON_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler Settings
    |--------------------------------------------------------------------------
    |
    | Configure scheduled task execution times and enabled status.
    | These can be overridden via database settings for dynamic control.
    |
    */

    'scheduler' => [
        // Polaris notification import (hourly)
        'import_polaris_enabled' => env('SCHEDULER_IMPORT_POLARIS_ENABLED', true),

        // Shoutbomb report import
        'import_shoutbomb_enabled' => env('SCHEDULER_IMPORT_SHOUTBOMB_ENABLED', true),
        'import_shoutbomb_time' => env('SCHEDULER_IMPORT_SHOUTBOMB_TIME', '09:00'),

        // Shoutbomb submission import
        'import_submissions_enabled' => env('SCHEDULER_IMPORT_SUBMISSIONS_ENABLED', true),
        'import_submissions_time' => env('SCHEDULER_IMPORT_SUBMISSIONS_TIME', '05:30'),

        // Email report import
        'import_email_enabled' => env('SCHEDULER_IMPORT_EMAIL_ENABLED', true),
        'import_email_time' => env('SCHEDULER_IMPORT_EMAIL_TIME', '09:30'),

        // Data aggregation
        'aggregate_enabled' => env('SCHEDULER_AGGREGATE_ENABLED', true),
        'aggregate_time' => env('SCHEDULER_AGGREGATE_TIME', '00:30'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shoutbomb FTP Settings
    |--------------------------------------------------------------------------
    |
    | Configure FTP connection details for importing Shoutbomb reports.
    |
    */

    'shoutbomb' => [
        'enabled' => env('SHOUTBOMB_ENABLED', true),

        'ftp' => [
            'host' => env('SHOUTBOMB_FTP_HOST', ''),
            'port' => env('SHOUTBOMB_FTP_PORT', 21),
            'username' => env('SHOUTBOMB_FTP_USERNAME', ''),
            'password' => env('SHOUTBOMB_FTP_PASSWORD', ''),
            'passive' => env('SHOUTBOMB_FTP_PASSIVE', true),
            'ssl' => env('SHOUTBOMB_FTP_SSL', false),
            'timeout' => env('SHOUTBOMB_FTP_TIMEOUT', 30),
        ],

        // Directory paths on FTP server
        'paths' => [
            'monthly_reports' => env('SHOUTBOMB_PATH_MONTHLY', '/reports/monthly'),
            'weekly_reports' => env('SHOUTBOMB_PATH_WEEKLY', '/reports/weekly'),
            'daily_invalid' => env('SHOUTBOMB_PATH_DAILY_INVALID', '/reports/daily/invalid'),
            'daily_undelivered' => env('SHOUTBOMB_PATH_DAILY_UNDELIVERED', '/reports/daily/undelivered'),
        ],

        // Local storage path for downloaded reports
        'local_storage_path' => storage_path('app/shoutbomb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shoutbomb Submission (SQL export) Settings
    |--------------------------------------------------------------------------
    |
    | Configure where the official SQL-generated submission/export files live on
    | the Shoutbomb FTP server, and which filename patterns to use when
    | discovering them. These values are used by the
    | `notices:import-shoutbomb-submissions` command.
    |
    */

'shoutbomb_submissions' => [
        // Root directory on the FTP server where submission/export files live.
        // In the current environment, `notices:list-shoutbomb-files` lists
        // everything from `/`, so we default to '/'.
        'root' => '/',

        // File name patterns for the various submission/export types. These
        // defaults match the real filenames on the FTP server.
        'patterns' => [
            'voice_patrons' => 'voice_patrons_submitted_*.txt',
            'text_patrons'  => 'text_patrons_submitted_*.txt',
            'holds'         => 'holds_submitted_*.txt',
            'overdue'       => 'overdue_submitted_*.txt',
            'renew'         => 'renew_submitted_*.txt',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Report Settings
    |--------------------------------------------------------------------------
    |
    | Configure email inbox connection for importing Shoutbomb reports
    | sent via email (opt-outs, invalid phones, undelivered voice).
    |
    */

'email_reports' => [
        'enabled' => env('EMAIL_REPORTS_ENABLED', false),

        'connection' => [
            'protocol' => env('EMAIL_PROTOCOL', 'imap'),
            'host' => env('EMAIL_HOST', ''),
            'port' => env('EMAIL_PORT', 993),
            'username' => env('EMAIL_USERNAME', ''),
            'password' => env('EMAIL_PASSWORD', ''),
            'encryption' => env('EMAIL_ENCRYPTION', 'ssl'),
        ],

        // Email inbox settings
        'mailbox' => env('EMAIL_MAILBOX', 'INBOX'),
        'from_address' => env('EMAIL_FROM_ADDRESS', 'shoutbomb'),

        // Processing options
        'mark_as_read' => env('EMAIL_MARK_AS_READ', true),
        'move_to_folder' => env('EMAIL_MOVE_TO_FOLDER', ''),
        'max_emails_per_run' => env('EMAIL_MAX_PER_RUN', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Feature toggles for optional external integrations. These can be turned
    | on/off via .env or database-backed settings.
    |
    */

'integrations' => [
        'shoutbomb_reports' => [
            // When enabled, verification will consult the Shoutbomb failure report table
            // to infer delivery failures (and success by absence when a notice was submitted).
            // Prefer an explicit NOTICES_* toggle; fall back to SHOUTBOMB_LOG_PROCESSING if present.
            'enabled' => env('NOTICES_SHOUTBOMB_REPORTS_ENABLED', env('SHOUTBOMB_LOG_PROCESSING', false)),

            // Name of the failure table (reuses existing SHOUTBOMB_FAILURE_TABLE from the app .env)
            'table' => env('SHOUTBOMB_FAILURE_TABLE', 'notice_failure_reports'),

            // Hours around the notice date to search failure emails for a match
            'date_window_hours' => env('SHOUTBOMB_REPORTS_DATE_WINDOW_HOURS', 24),

            // Microsoft Graph API configuration (migrated from dcplibrary/shoutbomb-reports)
            'graph' => [
                'tenant_id' => env('SHOUTBOMB_TENANT_ID'),
                'client_id' => env('SHOUTBOMB_CLIENT_ID'),
                'client_secret' => env('SHOUTBOMB_CLIENT_SECRET'),
                'user_email' => env('SHOUTBOMB_USER_EMAIL'),
                'api_version' => env('SHOUTBOMB_API_VERSION', 'v1.0'),
            ],

            // Email filtering configuration
            'filters' => [
                'folder' => env('SHOUTBOMB_FOLDER', null),
                'subject_contains' => env('SHOUTBOMB_SUBJECT_FILTER', null),
                'from_addresses' => env('SHOUTBOMB_FROM_FILTER', null),
                'max_emails' => env('SHOUTBOMB_MAX_EMAILS', 50),
                'unread_only' => env('SHOUTBOMB_UNREAD_ONLY', true),
                'mark_as_read' => env('SHOUTBOMB_MARK_AS_READ', true),
                'move_to_folder' => env('SHOUTBOMB_MOVE_TO_FOLDER', null),
            ],

            // Parsing configuration
            'parsing' => [
                'recipient_patterns' => [
                    '/(?:To|Recipient):\s*<?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>?/i',
                    '/(?:Original recipient|Failed recipient):\s*<?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>?/i',
                    '/<?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>?\s*(?:could not be delivered|delivery failed)/i',
                ],
                'reason_patterns' => [
                    '/(?:Diagnostic code|Reason):\s*(.+?)(?:\n|$)/i',
                    '/(?:Status|Error):\s*(\d{3}\s*.+?)(?:\n|$)/i',
                    '/(?:Message from|Remote server said):\s*(.+?)(?:\n|$)/i',
                ],
                'message_id_patterns' => [
                    '/Message-ID:\s*<?([^>\s]+)>?/i',
                    '/Original message ID:\s*<?([^>\s]+)>?/i',
                ],
            ],

            // Storage configuration
            'storage' => [
                'table_name' => env('SHOUTBOMB_FAILURE_TABLE', 'notice_failure_reports'),
                'store_raw_content' => env('SHOUTBOMB_STORE_RAW', false),
                'log_processing' => env('SHOUTBOMB_LOG_PROCESSING', true),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Organization
    |--------------------------------------------------------------------------
    |
    | Default organization ID for filtering notifications.
    | Set to your library's ReportingOrgID (e.g., 3 for DCPL).
    |
    */

    'reporting_org_id' => env('POLARIS_REPORTING_ORG_ID', 3),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Configure the built-in dashboard interface. The dashboard can be
    | disabled if you're building a custom frontend using the API.
    |
    */

    'dashboard' => [
        // Enable or disable the default dashboard
        'enabled' => env('NOTICES_DASHBOARD_ENABLED', true),

        // Route prefix for dashboard URLs
        'route_prefix' => env('NOTICES_DASHBOARD_PREFIX', 'notices'),

        // Middleware applied to dashboard routes
        'middleware' => ['web', 'auth'],

        // Default date range for dashboard (days)
        'default_date_range' => 30,

        // Notification types to display (null = all)
        'visible_notification_types' => null,

        // Delivery methods to display (null = all)
        'visible_delivery_methods' => null,

        // Enable real-time refresh
        'enable_realtime' => false,

        // Refresh interval in seconds
        'refresh_interval' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Configure the RESTful API endpoints. The API can be used to build
    | custom dashboards or integrate with other applications.
    |
    */

    'api' => [
        // Enable or disable API routes
        'enabled' => env('NOTICES_API_ENABLED', true),

        // Route prefix for API URLs
        'route_prefix' => env('NOTICES_API_PREFIX', 'api/notices'),

        // Middleware applied to API routes
        'middleware' => ['api', 'auth:sanctum'],

        // Rate limiting (requests per minute)
        'rate_limit' => env('NOTICES_API_RATE_LIMIT', 60),

        // Default pagination size
        'per_page' => 20,

        // Maximum pagination size
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lookup Tables
    |--------------------------------------------------------------------------
    |
    | Static reference data for notification types, delivery options, etc.
    | These rarely change, so they're defined here instead of in the database.
    |
    */

    'notification_types' => [
        0 => 'Combined',
        1 => '1st Overdue',
        2 => 'Hold Ready',
        3 => 'Hold Cancel',
        4 => 'Recall',
        5 => 'All',
        6 => 'Route',
        7 => 'Almost Overdue',
        8 => 'Fine Notice',
        9 => 'Inactive Reminder',
        10 => 'Expiration Reminder',
        11 => 'Bill',
        12 => '2nd Overdue',
        13 => '3rd Overdue',
        14 => 'Serial Claim',
        15 => 'Polaris Fusion',
        16 => 'Course Reserves',
        17 => 'Borrow-By-Mail Failure',
        18 => '2nd Hold',
        19 => 'Missing Part',
        20 => 'Manual Bill',
        21 => '2nd Fine Notice',
    ],

    'delivery_options' => [
        1 => 'Mail',
        2 => 'Email',
        3 => 'Voice',           // PhoneVoice1 - Primary phone field (used for voice calls)
        4 => 'Phone 2 (Voice)', // PhoneVoice2 - Not used at DCPL
        5 => 'Phone 3 (Voice)', // PhoneVoice3 - Not used at DCPL
        6 => 'FAX',             // Not used at DCPL
        7 => 'EDI',             // Not used at DCPL
        8 => 'SMS',             // PhoneVoice1 - Same phone field as Voice (used for text messages)
        9 => 'Mobile App',      // Not active in Polaris
    ],

    'notification_statuses' => [
        1 => 'Call completed - Voice',
        2 => 'Call completed - Answering machine',
        3 => 'Call not completed - Hang up',
        4 => 'Call not completed - Busy',
        5 => 'Call not completed - No answer',
        6 => 'Call not completed - No ring',
        7 => 'Call failed - No dial tone',
        8 => 'Call failed - Intercept tones heard',
        9 => 'Call failed - Probable bad phone number',
        10 => 'Call failed - Maximum retries exceeded',
        11 => 'Call failed - Undetermined error',
        12 => 'Email Completed',
        13 => 'Email Failed - Invalid address',
        14 => 'Email Failed',
        15 => 'Mail Printed',
        16 => 'Sent',
    ],

];
