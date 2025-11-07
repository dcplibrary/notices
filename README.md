[![PHP Composer](https://github.com/dcplibrary/notifications/actions/workflows/php.yml/badge.svg)](https://github.com/dcplibrary/notifications/actions/workflows/php.yml) [![Semantic-Release](https://github.com/dcplibrary/notifications/actions/workflows/semantic-release.yml/badge.svg)](https://github.com/dcplibrary/notifications/actions/workflows/semantic-release.yml)

# Polaris Notifications Package

A Laravel package for tracking and analyzing Polaris ILS notification delivery across multiple channels (Email, SMS, Voice, Mail) with Shoutbomb integration.

## Features

- ✅ Direct MSSQL connection to Polaris ILS database
- ✅ FTP integration for Shoutbomb SMS/Voice delivery reports
- ✅ Automated daily imports via scheduled tasks
- ✅ Real-time notification tracking and verification
- ✅ Historical trend analysis with aggregated summaries
- ✅ Dashboard-ready data models
- ✅ Comprehensive Artisan commands
- ✅ Configurable import schedules and batch sizes

## Installation

### 1. Install the package via Composer

```bash
composer require dcplibrary/polaris-notifications
```

### 2. Publish configuration file

```bash
php artisan vendor:publish --tag=polaris-notifications-config
```

This creates `config/polaris-notifications.php` where you can configure database connections, FTP settings, and other options.

### 3. Configure environment variables

Add the following to your `.env` file:

```env
# Polaris MSSQL Database
POLARIS_DB_HOST=your-polaris-server.local
POLARIS_DB_PORT=1433
POLARIS_DB_DATABASE=Polaris
POLARIS_DB_USERNAME=your-username
POLARIS_DB_PASSWORD=your-password
POLARIS_REPORTING_ORG_ID=3

# Shoutbomb FTP (optional)
SHOUTBOMB_ENABLED=true
SHOUTBOMB_FTP_HOST=ftp.shoutbomb.com
SHOUTBOMB_FTP_PORT=21
SHOUTBOMB_FTP_USERNAME=your-username
SHOUTBOMB_FTP_PASSWORD=your-password
SHOUTBOMB_FTP_PASSIVE=true
SHOUTBOMB_FTP_SSL=false
```

### 4. Run migrations

```bash
php artisan migrate
```

This creates the following tables:
- `notification_logs` - Main notification tracking table
- `shoutbomb_deliveries` - SMS/Voice delivery tracking
- `shoutbomb_keyword_usage` - Patron keyword interactions
- `shoutbomb_registrations` - Subscriber statistics
- `daily_notification_summary` - Aggregated daily summaries

## Usage

### Test Connections

Before importing data, test your connections:

```bash
php artisan polaris:test-connections
```

Test specific connections:
```bash
php artisan polaris:test-connections --polaris
php artisan polaris:test-connections --shoutbomb
```

### Import Polaris Notifications

Import notifications from the last 24 hours (default):
```bash
php artisan polaris:import-notifications
```

Import from the last 7 days:
```bash
php artisan polaris:import-notifications --days=7
```

Import a specific date range:
```bash
php artisan polaris:import-notifications --start-date=2025-01-01 --end-date=2025-01-31
```

Import all historical data:
```bash
php artisan polaris:import-notifications --full
```

### Import Shoutbomb Reports

Import all Shoutbomb reports from FTP:
```bash
php artisan polaris:import-shoutbomb
```

Import specific report types:
```bash
php artisan polaris:import-shoutbomb --type=monthly
php artisan polaris:import-shoutbomb --type=weekly
php artisan polaris:import-shoutbomb --type=daily-invalid
php artisan polaris:import-shoutbomb --type=daily-undelivered
```

### Aggregate Notification Data

Aggregate yesterday's notifications (typical nightly job):
```bash
php artisan polaris:aggregate-notifications
```

Aggregate a specific date:
```bash
php artisan polaris:aggregate-notifications --date=2025-01-15
```

Aggregate a date range:
```bash
php artisan polaris:aggregate-notifications --start-date=2025-01-01 --end-date=2025-01-31
```

Re-aggregate all historical data:
```bash
php artisan polaris:aggregate-notifications --all
```

## Scheduled Tasks

Add these to your `app/Console/Kernel.php` for automated imports:

```php
protected function schedule(Schedule $schedule)
{
    // Import Polaris notifications hourly
    $schedule->command('polaris:import-notifications --days=1')
        ->hourly()
        ->withoutOverlapping();

    // Import Shoutbomb reports daily at 9 AM
    $schedule->command('polaris:import-shoutbomb')
        ->dailyAt('09:00')
        ->withoutOverlapping();

    // Aggregate yesterday's data at midnight
    $schedule->command('polaris:aggregate-notifications')
        ->dailyAt('00:30')
        ->withoutOverlapping();
}
```

## Models

### NotificationLog

Main notification tracking model with scopes for common queries:

```php
use Dcplibrary\PolarisNotifications\Models\NotificationLog;

// Get recent notifications
$recent = NotificationLog::recent(7)->get();

// Get successful email notifications
$emails = NotificationLog::successful()
    ->byDeliveryMethod(2) // 2 = Email
    ->get();

// Get notifications for a patron
$patronNotifications = NotificationLog::forPatron($patronId)->get();

// Get notifications by type
$holds = NotificationLog::ofType(2)->get(); // 2 = Hold Ready
```

### DailyNotificationSummary

Aggregated data for dashboard queries:

```php
use Dcplibrary\PolarisNotifications\Models\DailyNotificationSummary;
use Carbon\Carbon;

// Get summary for date range
$summaries = DailyNotificationSummary::dateRange(
    Carbon::parse('2025-01-01'),
    Carbon::parse('2025-01-31')
)->get();

// Get breakdown by notification type
$breakdown = DailyNotificationSummary::getBreakdownByType(
    $startDate,
    $endDate
);

// Get breakdown by delivery method
$deliveryBreakdown = DailyNotificationSummary::getBreakdownByDelivery(
    $startDate,
    $endDate
);
```

### ShoutbombDelivery

SMS/Voice delivery tracking:

```php
use Dcplibrary\PolarisNotifications\Models\ShoutbombDelivery;

// Get failed SMS deliveries
$failed = ShoutbombDelivery::sms()->failed()->get();

// Get invalid phone numbers
$invalid = ShoutbombDelivery::invalid()->get();

// Get recent Voice deliveries
$voice = ShoutbombDelivery::voice()->recent(7)->get();
```

### ShoutbombKeywordUsage

Track patron keyword interactions (RHL, RA, OI, etc.):

```php
use Dcplibrary\PolarisNotifications\Models\ShoutbombKeywordUsage;

// Get keyword statistics
$stats = ShoutbombKeywordUsage::getKeywordStats($startDate, $endDate);

// Get total usage for a keyword
$rhlUsage = ShoutbombKeywordUsage::getTotalUsageByKeyword('RHL', $startDate, $endDate);
```

## Configuration

The `config/polaris-notifications.php` file contains all configuration options:

- **polaris_connection**: MSSQL connection settings
- **import**: Batch size, default days, duplicate handling
- **shoutbomb**: FTP connection and path settings
- **reporting_org_id**: Your library's organization ID
- **dashboard**: Display preferences for dashboards
- **notification_types**: Lookup table for notification types
- **delivery_options**: Lookup table for delivery methods
- **notification_statuses**: Lookup table for statuses

## Architecture

This package uses a **hybrid architecture**:

1. **Polaris MSSQL**: Direct connection for notification logs (what was sent)
2. **Shoutbomb FTP**: File import for delivery confirmation (what was delivered)
3. **Local MySQL**: Cached data for fast dashboard queries

### Data Flow

```
Polaris MSSQL → Import Service → notification_logs (MySQL)
                                         ↓
Shoutbomb FTP → Parser → shoutbomb_* tables (MySQL)
                                         ↓
                            Aggregator Service
                                         ↓
                          daily_notification_summary
                                         ↓
                                    Dashboard
```

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- MSSQL Server (for Polaris database)
- MySQL/MariaDB (for local cache)
- FTP extension enabled (for Shoutbomb imports)

## License

MIT License

## Author

Brian Lashbrook - Daviess County Public Library

## Support

For issues or questions, please contact the developer or open an issue in the project repository.
