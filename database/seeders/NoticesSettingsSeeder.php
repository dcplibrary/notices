<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Dcplibrary\Notices\Models\NotificationSetting;
use Illuminate\Database\Seeder;

class NoticesSettingsSeeder extends Seeder
{
    /**
     * Seed the notification settings table with default values.
     */
    public function run(): void
    {
        $settings = [
            // Shoutbomb FTP Settings
            [
                'group' => 'shoutbomb',
                'key' => 'enabled',
                'value' => config('notices.shoutbomb.enabled', true),
                'type' => 'boolean',
                'description' => 'Enable Shoutbomb voice/SMS notifications',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'shoutbomb',
                'key' => 'ftp.host',
                'value' => config('notices.shoutbomb.ftp.host', ''),
                'type' => 'string',
                'description' => 'Shoutbomb FTP server hostname',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'string', 'max:255'],
            ],
            [
                'group' => 'shoutbomb',
                'key' => 'ftp.port',
                'value' => config('notices.shoutbomb.ftp.port', 21),
                'type' => 'integer',
                'description' => 'Shoutbomb FTP server port',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:1', 'max:65535'],
            ],
            [
                'group' => 'shoutbomb',
                'key' => 'ftp.username',
                'value' => config('notices.shoutbomb.ftp.username', ''),
                'type' => 'string',
                'description' => 'Shoutbomb FTP username',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => true,
                'validation_rules' => ['required', 'string', 'max:255'],
            ],
            [
                'group' => 'shoutbomb',
                'key' => 'ftp.password',
                'value' => config('notices.shoutbomb.ftp.password', ''),
                'type' => 'encrypted',
                'description' => 'Shoutbomb FTP password',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => true,
                'validation_rules' => ['required', 'string'],
            ],
            [
                'group' => 'shoutbomb',
                'key' => 'ftp.passive',
                'value' => config('notices.shoutbomb.ftp.passive', true),
                'type' => 'boolean',
                'description' => 'Use passive mode for FTP connection',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'shoutbomb',
                'key' => 'ftp.ssl',
                'value' => config('notices.shoutbomb.ftp.ssl', false),
                'type' => 'boolean',
                'description' => 'Use SSL/TLS for FTP connection',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'shoutbomb',
                'key' => 'ftp.timeout',
                'value' => config('notices.shoutbomb.ftp.timeout', 30),
                'type' => 'integer',
                'description' => 'FTP connection timeout in seconds',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:5', 'max:300'],
            ],

            // Import Settings
            [
                'group' => 'import',
                'key' => 'default_days',
                'value' => config('notices.import.default_days', 1),
                'type' => 'integer',
                'description' => 'Default number of days to import',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
            ],
            [
                'group' => 'import',
                'key' => 'batch_size',
                'value' => config('notices.import.batch_size', 500),
                'type' => 'integer',
                'description' => 'Number of records to insert per batch',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:1', 'max:5000'],
            ],
            [
                'group' => 'import',
                'key' => 'skip_duplicates',
                'value' => config('notices.import.skip_duplicates', true),
                'type' => 'boolean',
                'description' => 'Skip duplicate records during import',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
            ],

            // Dashboard Settings
            [
                'group' => 'dashboard',
                'key' => 'enabled',
                'value' => config('notices.dashboard.enabled', true),
                'type' => 'boolean',
                'description' => 'Enable the web dashboard interface',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'dashboard',
                'key' => 'default_date_range',
                'value' => config('notices.dashboard.default_date_range', 30),
                'type' => 'integer',
                'description' => 'Default date range in days for dashboard views',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
            ],
            [
                'group' => 'dashboard',
                'key' => 'enable_realtime',
                'value' => config('notices.dashboard.enable_realtime', false),
                'type' => 'boolean',
                'description' => 'Enable real-time auto-refresh on dashboard',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'dashboard',
                'key' => 'refresh_interval',
                'value' => config('notices.dashboard.refresh_interval', 300),
                'type' => 'integer',
                'description' => 'Dashboard auto-refresh interval in seconds',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:30', 'max:3600'],
            ],

            // API Settings
            [
                'group' => 'api',
                'key' => 'enabled',
                'value' => config('notices.api.enabled', true),
                'type' => 'boolean',
                'description' => 'Enable API endpoints',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'api',
                'key' => 'rate_limit',
                'value' => config('notices.api.rate_limit', 60),
                'type' => 'integer',
                'description' => 'API rate limit (requests per minute)',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:10', 'max:1000'],
            ],
            [
                'group' => 'api',
                'key' => 'per_page',
                'value' => config('notices.api.per_page', 20),
                'type' => 'integer',
                'description' => 'Default number of results per page',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
            ],
            [
                'group' => 'api',
                'key' => 'max_per_page',
                'value' => config('notices.api.max_per_page', 100),
                'type' => 'integer',
                'description' => 'Maximum number of results per page',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:10', 'max:500'],
            ],

            // Email Reports Settings
            [
                'group' => 'email_reports',
                'key' => 'enabled',
                'value' => config('notices.email_reports.enabled', false),
                'type' => 'boolean',
                'description' => 'Enable importing reports from email',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'email_reports',
                'key' => 'connection.host',
                'value' => config('notices.email_reports.connection.host', ''),
                'type' => 'string',
                'description' => 'Email server hostname',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required_if:email_reports.enabled,true', 'string', 'max:255'],
            ],
            [
                'group' => 'email_reports',
                'key' => 'connection.port',
                'value' => config('notices.email_reports.connection.port', 993),
                'type' => 'integer',
                'description' => 'Email server port',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:1', 'max:65535'],
            ],
            [
                'group' => 'email_reports',
                'key' => 'connection.username',
                'value' => config('notices.email_reports.connection.username', ''),
                'type' => 'string',
                'description' => 'Email account username',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => true,
                'validation_rules' => ['required_if:email_reports.enabled,true', 'string'],
            ],
            [
                'group' => 'email_reports',
                'key' => 'connection.password',
                'value' => config('notices.email_reports.connection.password', ''),
                'type' => 'encrypted',
                'description' => 'Email account password',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => true,
                'validation_rules' => ['required_if:email_reports.enabled,true', 'string'],
            ],
            [
                'group' => 'email_reports',
                'key' => 'max_emails_per_run',
                'value' => config('notices.email_reports.max_emails_per_run', 50),
                'type' => 'integer',
                'description' => 'Maximum emails to process per run',
                'is_public' => false,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:1', 'max:500'],
            ],

            // General Settings
            [
                'group' => 'general',
                'key' => 'reporting_org_id',
                'value' => config('notices.reporting_org_id', 3),
                'type' => 'integer',
                'description' => 'Default library reporting organization ID',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'integer', 'min:1'],
            ],

            // Scheduler Settings
            [
                'group' => 'scheduler',
                'key' => 'import_polaris_enabled',
                'value' => config('notices.scheduler.import_polaris_enabled', true),
                'type' => 'boolean',
                'description' => 'Enable hourly Polaris notification import',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'scheduler',
                'key' => 'import_shoutbomb_enabled',
                'value' => config('notices.scheduler.import_shoutbomb_enabled', true),
                'type' => 'boolean',
                'description' => 'Enable daily Shoutbomb report import',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'scheduler',
                'key' => 'import_shoutbomb_time',
                'value' => config('notices.scheduler.import_shoutbomb_time', '09:00'),
                'type' => 'string',
                'description' => 'Time to run Shoutbomb import (HH:MM format, 24-hour)',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            ],
            [
                'group' => 'scheduler',
                'key' => 'import_submissions_enabled',
                'value' => config('notices.scheduler.import_submissions_enabled', true),
                'type' => 'boolean',
                'description' => 'Enable daily Shoutbomb submission import',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'scheduler',
                'key' => 'import_submissions_time',
                'value' => config('notices.scheduler.import_submissions_time', '05:30'),
                'type' => 'string',
                'description' => 'Time to run Shoutbomb submission import (HH:MM format, 24-hour)',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            ],
            [
                'group' => 'scheduler',
                'key' => 'import_email_enabled',
                'value' => config('notices.scheduler.import_email_enabled', true),
                'type' => 'boolean',
                'description' => 'Enable daily email report import',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'scheduler',
                'key' => 'import_email_time',
                'value' => config('notices.scheduler.import_email_time', '09:30'),
                'type' => 'string',
                'description' => 'Time to run email import (HH:MM format, 24-hour)',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            ],
            [
                'group' => 'scheduler',
                'key' => 'aggregate_enabled',
                'value' => config('notices.scheduler.aggregate_enabled', true),
                'type' => 'boolean',
                'description' => 'Enable daily data aggregation',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
            ],
            [
                'group' => 'scheduler',
                'key' => 'aggregate_time',
                'value' => config('notices.scheduler.aggregate_time', '00:30'),
                'type' => 'string',
                'description' => 'Time to run aggregation (HH:MM format, 24-hour)',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => ['required', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            ],
        ];

        foreach ($settings as $settingData) {
            $setting = NotificationSetting::firstOrNew([
                'scope' => null,
                'scope_id' => null,
                'group' => $settingData['group'],
                'key' => $settingData['key'],
            ]);

            // Only set default values if this is a new record
            if (!$setting->exists) {
                $setting->fill([
                    'type' => $settingData['type'],
                    'description' => $settingData['description'],
                    'is_public' => $settingData['is_public'],
                    'is_editable' => $settingData['is_editable'],
                    'is_sensitive' => $settingData['is_sensitive'],
                    'validation_rules' => $settingData['validation_rules'] ?? null,
                    'updated_by' => 'seeder',
                ]);

                $setting->setTypedValue($settingData['value']);
                $setting->save();

                $this->command->info("Created setting: {$settingData['group']}.{$settingData['key']}");
            } else {
                $this->command->info("Setting already exists: {$settingData['group']}.{$settingData['key']}");
            }
        }

        $this->command->info('Notification settings seeded successfully!');
    }
}
