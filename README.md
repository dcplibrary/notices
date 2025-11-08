[![PHP Composer](https://github.com/dcplibrary/notifications/actions/workflows/php.yml/badge.svg)](https://github.com/dcplibrary/notifications/actions/workflows/php.yml) [![Semantic-Release](https://github.com/dcplibrary/notifications/actions/workflows/semantic-release.yml/badge.svg)](https://github.com/dcplibrary/notifications/actions/workflows/semantic-release.yml)

# Polaris Notifications Package

A Laravel package for tracking and analyzing Polaris ILS notification delivery across multiple channels (Email, SMS, Voice, Mail) with Shoutbomb integration.

## Features

- ✅ **Built-in Dashboard**: Visualize notification data out-of-the-box with charts and metrics
- ✅ **RESTful API**: Access data programmatically for custom integrations
- ✅ **Direct MSSQL Connection**: Connect to Polaris ILS database
- ✅ **Shoutbomb Integration**: Import SMS/Voice delivery reports via FTP
- ✅ **Automated Imports**: Schedule daily/hourly imports via Laravel scheduler
- ✅ **Real-time Tracking**: Track notification delivery across all channels
- ✅ **Historical Analysis**: Aggregated summaries and trend analysis
- ✅ **Comprehensive Commands**: Artisan commands for all operations
- ✅ **Fully Customizable**: Publish views, disable components, use API only

## Installation

### 1. Install the package via Composer

```bash
composer require dcplibrary/notifications
```

### 2. Publish configuration file

```bash
php artisan vendor:publish --tag=notifications-config
```

This creates `config/notifications.php` where you can configure database connections, FTP settings, and other options.

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
php artisan notifications:test-connections
```

Test specific connections:
```bash
php artisan notifications:test-connections --polaris
php artisan notifications:test-connections --shoutbomb
```

### Import Polaris Notifications

Import notifications from the last 24 hours (default):
```bash
php artisan notifications:import-notifications
```

Import from the last 7 days:
```bash
php artisan notifications:import-notifications --days=7
```

Import a specific date range:
```bash
php artisan notifications:import-notifications --start-date=2025-01-01 --end-date=2025-01-31
```

Import all historical data:
```bash
php artisan notifications:import-notifications --full
```

### Import Shoutbomb Reports

Import all Shoutbomb reports from FTP:
```bash
php artisan notifications:import-shoutbomb
```

Import specific report types:
```bash
php artisan notifications:import-shoutbomb --type=monthly
php artisan notifications:import-shoutbomb --type=weekly
php artisan notifications:import-shoutbomb --type=daily-invalid
php artisan notifications:import-shoutbomb --type=daily-undelivered
```

### Aggregate Notification Data

Aggregate yesterday's notifications (typical nightly job):
```bash
php artisan notifications:aggregate-notifications
```

Aggregate a specific date:
```bash
php artisan notifications:aggregate-notifications --date=2025-01-15
```

Aggregate a date range:
```bash
php artisan notifications:aggregate-notifications --start-date=2025-01-01 --end-date=2025-01-31
```

Re-aggregate all historical data:
```bash
php artisan notifications:aggregate-notifications --all
```

## Scheduled Tasks

Add these to your `app/Console/Kernel.php` for automated imports:

```php
protected function schedule(Schedule $schedule)
{
    // Import Polaris notifications hourly
    $schedule->command('notifications:import-notifications --days=1')
        ->hourly()
        ->withoutOverlapping();

    // Import Shoutbomb reports daily at 9 AM
    $schedule->command('notifications:import-shoutbomb')
        ->dailyAt('09:00')
        ->withoutOverlapping();

    // Aggregate yesterday's data at midnight
    $schedule->command('notifications:aggregate-notifications')
        ->dailyAt('00:30')
        ->withoutOverlapping();
}
```

## Dashboard

This package includes a **built-in dashboard** for visualizing notification data. The dashboard works out-of-the-box and can be customized.

### Accessing the Dashboard

After installation, visit:
```
https://yourapp.com/notifications
```

**Note:** Dashboard requires authentication by default (configure in `config/notifications.php`).

### Dashboard Features

- **Overview**: Key metrics, trends, type/delivery distribution
- **Notifications List**: Filterable table of individual notifications
- **Analytics**: Success rates, detailed breakdowns, performance metrics
- **Shoutbomb**: Subscriber statistics and growth trends

### Customization

Publish and modify the views:
```bash
php artisan vendor:publish --tag=notifications-views
```

Views will be in `resources/views/vendor/notifications/`.

For detailed dashboard customization, see [DASHBOARD.md](DASHBOARD.md).

### Disabling the Dashboard

If building a custom UI using the API:

```php
// config/notifications.php
'dashboard' => [
    'enabled' => false,
],
```

## API

The package provides a **RESTful API** for accessing notification data. Perfect for building custom dashboards or integrating with other systems.

### API Endpoints

All endpoints are prefixed with `/api/notifications`:

```bash
# Get notifications (with filters)
GET /api/notifications/notifications?days=7&successful=1

# Get daily summaries
GET /api/notifications/summaries

# Get analytics overview
GET /api/notifications/analytics/overview?days=30

# Get Shoutbomb data
GET /api/notifications/shoutbomb/deliveries
GET /api/notifications/shoutbomb/keyword-usage
GET /api/notifications/shoutbomb/registrations/latest
```

### Authentication

API routes use Laravel Sanctum by default:

```bash
curl -X GET "https://yourapp.com/api/notifications/notifications" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Example: Fetch Notification Stats

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken('YOUR_TOKEN')
    ->get('/api/notifications/notifications/stats', [
        'days' => 30
    ]);

$stats = $response->json();
// ['total' => 1000, 'successful' => 950, 'failed' => 50, ...]
```

For complete API documentation, see [API.md](API.md).

## Demo Data

Generate realistic demo data for testing the dashboard:

```bash
# Generate 30 days of demo data
php artisan notifications:seed-demo

# Generate 60 days
php artisan notifications:seed-demo --days=60

# Clear existing data and seed fresh
php artisan notifications:seed-demo --fresh
```

This creates sample notifications, summaries, Shoutbomb deliveries, keyword usage, and registration snapshots.

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

The `config/notifications.php` file contains all configuration options:

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
