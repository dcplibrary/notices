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
        'enabled' => env('NOTIFICATIONS_DASHBOARD_ENABLED', true),

        // Route prefix for dashboard URLs
        'route_prefix' => env('NOTIFICATIONS_DASHBOARD_PREFIX', 'notifications'),

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
        'enabled' => env('NOTIFICATIONS_API_ENABLED', true),

        // Route prefix for API URLs
        'route_prefix' => env('NOTIFICATIONS_API_PREFIX', 'api/notifications'),

        // Middleware applied to API routes
        'middleware' => ['api', 'auth:sanctum'],

        // Rate limiting (requests per minute)
        'rate_limit' => env('NOTIFICATIONS_API_RATE_LIMIT', 60),

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
        1 => '1st Overdue',
        2 => 'Hold Ready',
        3 => 'Hold Cancel',
        7 => 'Almost Overdue',
        8 => 'Fine Notice',
        12 => '2nd Overdue',
        13 => '3rd Overdue',
    ],

    'delivery_options' => [
        1 => 'Mail',
        2 => 'Email',
        3 => 'Voice',
        8 => 'SMS',
    ],

    'notification_statuses' => [
        12 => 'Success',
        14 => 'Failed',
        15 => 'Pending',
    ],

];
