[![PHP Composer](https://github.com/dcplibrary/notices/actions/workflows/php.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/php.yml) [![Semantic-Release](https://github.com/dcplibrary/notices/actions/workflows/semantic-release.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/semantic-release.yml)

# Polaris Notices Package

A Laravel package for tracking and analyzing Polaris ILS notification delivery across multiple channels (Email, SMS, Voice, Mail) with Shoutbomb integration.

## Features

- ✅ **Built-in Dashboard**: Visualize notification data out-of-the-box with charts and metrics
- ✅ **Verification System**: Track complete notice lifecycle from creation to delivery
- ✅ **Troubleshooting Dashboard**: Analyze failures and detect verification gaps
- ✅ **Plugin Architecture**: Modular design for easy channel additions
- ✅ **CSV Export**: Export verification data, patron history, and failure reports
- ✅ **RESTful API**: Access data programmatically for custom integrations
- ✅ **Direct MSSQL Connection**: Connect to Polaris ILS database
- ✅ **Shoutbomb Integration**: Import SMS/Voice delivery reports via FTP
- ✅ **Shoutbomb Reports (Graph) Integration**: Optionally read failure reports from dcplibrary/shoutbomb-reports
- ✅ **Email Report Ingester**: Automated IMAP email fetching and parsing for Shoutbomb reports
- ✅ **Automated Imports**: Schedule daily/hourly imports via Laravel scheduler
- ✅ **Real-time Tracking**: Track notification delivery across all channels
- ✅ **Historical Analysis**: Aggregated summaries and trend analysis
- ✅ **Comprehensive Commands**: Artisan commands for all operations
- ✅ **Fully Customizable**: Publish views, disable components, use API only
- ✅ **Docker Ready**: Complete Docker setup with SQL Server driver pre-installed

## Installation

> **🐳 Using Docker?** See **[Docker Setup Guide](docs/DOCKER_SETUP.md)** for a complete Docker-based installation with the SQL Server driver pre-configured.

### Standard Installation

#### 1. Install the package via Composer

```bash
composer require dcplibrary/notices
```

### 2. Publish configuration file

```bash
php artisan vendor:publish --tag=notices-config
```

This creates `config/notices.php` where you can configure database connections, FTP settings, and other options.

### 3. Configure environment variables

Add the following to your `.env` file:

```env
# Polaris MSSQL Database
POLARIS_DB_DRIVER=dblib  # Use 'dblib' for Linux/FreeTDS or 'sqlsrv' for Windows
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

# Email Reports (optional)
EMAIL_REPORTS_ENABLED=true
EMAIL_HOST=imap.example.com
EMAIL_PORT=993
EMAIL_USERNAME=your-email@example.com
EMAIL_PASSWORD=your-password
EMAIL_ENCRYPTION=ssl
EMAIL_MAILBOX=INBOX
EMAIL_FROM_ADDRESS=shoutbomb
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
php artisan notices:test-connections
```

Test specific connections:
```bash
php artisan notices:test-connections --polaris
php artisan notices:test-connections --shoutbomb
php artisan notices:test-connections --email
```

### Import Polaris Notifications

Import notifications from the last 24 hours (default):
```bash
php artisan notices:import
```

Import from the last 7 days:
```bash
php artisan notices:import --days=7
```

Import a specific date range:
```bash
php artisan notices:import --start-date=2025-01-01 --end-date=2025-01-31
```

Import all historical data:
```bash
php artisan notices:import --full
```

### Import Shoutbomb Reports

Import all Shoutbomb reports from FTP:
```bash
php artisan notices:import-shoutbomb
```

Import specific report types:
```bash
php artisan notices:import-shoutbomb --type=monthly
php artisan notices:import-shoutbomb --type=weekly
php artisan notices:import-shoutbomb --type=daily-invalid
php artisan notices:import-shoutbomb --type=daily-undelivered
```

### Import Email Reports

Import Shoutbomb reports from email inbox (opt-outs, invalid phones, undelivered voice):
```bash
php artisan notices:import-email-reports
```

Options:
```bash
php artisan notices:import-email-reports --mark-read  # Mark emails as read after import
php artisan notices:import-email-reports --move-to=Processed  # Move to folder after import
php artisan notices:import-email-reports --limit=100  # Process max 100 emails
```

### Aggregate Notification Data

Aggregate yesterday's notifications (typical nightly job):
```bash
php artisan notices:aggregate
```

Aggregate a specific date:
```bash
php artisan notices:aggregate --date=2025-01-15
```

Aggregate a date range:
```bash
php artisan notices:aggregate --start-date=2025-01-01 --end-date=2025-01-31
```

Re-aggregate all historical data:
```bash
php artisan notices:aggregate --all
```

## Shoutbomb Reports Integration (Optional)

See docs/integrations/shoutbomb-reports.md for how to enable reading failure reports from dcplibrary/shoutbomb-reports. This does not replace importing Shoutbomb submissions via FTP.

## Scheduled Tasks

Add these to your `app/Console/Kernel.php` for automated imports:

```php
protected function schedule(Schedule $schedule)
{
    // Import Polaris notifications hourly
    $schedule->command('notices:import --days=1')
        ->hourly()
        ->withoutOverlapping();

    // Import Shoutbomb reports daily at 9 AM
    $schedule->command('notices:import-shoutbomb')
        ->dailyAt('09:00')
        ->withoutOverlapping();

    // Import email reports daily at 9:30 AM
    $schedule->command('notices:import-email-reports --mark-read')
        ->dailyAt('09:30')
        ->withoutOverlapping();

    // Aggregate yesterday's data at midnight
    $schedule->command('notices:aggregate')
        ->dailyAt('00:30')
        ->withoutOverlapping();
}
```

## Dashboard

This package includes a **built-in dashboard** for visualizing notification data. The dashboard works out-of-the-box and can be customized.

### Accessing the Dashboard

After installation, visit:
```
https://yourapp.com/notices
```

**Note:** Dashboard requires authentication by default (configure in `config/notices.php`).

![Dashboard Overview](docs/images/dashboard-overview.png)

> See [docs/DASHBOARD.md](docs/DASHBOARD.md) for detailed documentation and [docs/SCREENSHOTS.md](docs/SCREENSHOTS.md) for adding images.

### Dashboard Features

- **Overview**: Key metrics, trends, type/delivery distribution
- **Notifications List**: Filterable table of individual notifications
- **Analytics**: Success rates, detailed breakdowns, performance metrics
- **Shoutbomb**: Subscriber statistics and growth trends
- **Verification**: Search notices, view timelines, patron history
- **Troubleshooting**: Analyze failures, detect verification gaps

### Customization

Publish and modify the views:
```bash
php artisan vendor:publish --tag=notices-views
```

Views will be in `resources/views/vendor/notifications/`.

For detailed dashboard customization, see [docs/DASHBOARD.md](docs/DASHBOARD.md).

### Disabling the Dashboard

If building a custom UI using the API:

```php
// config/notices.php
'dashboard' => [
    'enabled' => false,
],
```

## API

The package provides a **RESTful API** for accessing notification data. Perfect for building custom dashboards or integrating with other systems.

### API Endpoints

All endpoints are prefixed with `/api/notices`:

```bash
# Get notices (with filters)
GET /api/notices/logs?days=7&successful=1

# Get daily summaries
GET /api/notices/summaries

# Get analytics overview
GET /api/notices/analytics/overview?days=30

# Get Shoutbomb data
GET /api/notices/shoutbomb/deliveries
GET /api/notices/shoutbomb/keyword-usage
GET /api/notices/shoutbomb/registrations/latest
```

### Authentication

API routes use Laravel Sanctum by default:

```bash
curl -X GET "https://yourapp.com/api/notices/logs" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Example: Fetch Notification Stats

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken('YOUR_TOKEN')
    ->get('/api/notices/logs/stats', [
        'days' => 30
    ]);

$stats = $response->json();
// ['total' => 1000, 'successful' => 950, 'failed' => 50, ...]
```

For complete API documentation, see [docs/API.md](docs/API.md).

## Verification System

The package includes a comprehensive **verification system** to track every notice through its complete lifecycle and troubleshoot delivery issues.

### Verification Features

**Search & Timeline**
- Search by patron barcode, phone, email, or item barcode
- Visual timeline showing all verification steps
- Detailed failure information with troubleshooting tips
- Patron-specific history with success rates

**4-Step Verification Lifecycle**
1. ✅ **Created** - Notice created in notification_logs
2. ✅ **Submitted** - Submitted to delivery service (Shoutbomb, email, etc.)
3. ✅ **Verified** - Confirmed in verification reports (PhoneNotices.csv, etc.)
4. ✅ **Delivered** - Delivery confirmation received

**Troubleshooting Dashboard**
- Failure analysis by reason and type
- Mismatch detection (submitted but not verified, verified but not delivered)
- Recent failures with detailed information
- Configurable date ranges (7/14/30 days)

**CSV Export**
- Export verification search results
- Export patron history
- Export troubleshooting data
- UTF-8 BOM for Excel compatibility

### Verification API

Access verification data programmatically:

```bash
# Verify specific notice
GET /api/notices/verification/verify?patron_barcode=123456

# Get patron history
GET /api/notices/verification/patron/123456

# Get failures
GET /api/notices/verification/failures?days=7

# Get troubleshooting summary
GET /api/notices/verification/troubleshooting/summary
```

### Plugin Architecture

The verification system uses a modular plugin architecture for easy channel additions:

```php
// Each notification channel is a plugin
interface NotificationPlugin {
    public function getName(): string;
    public function canVerify(NotificationLog $log): bool;
    public function verify(NotificationLog $log, VerificationResult $result): VerificationResult;
    public function getStatistics(Carbon $startDate, Carbon $endDate): array;
}
```

**Current Plugins:**
- **ShoutbombPlugin** - Voice and SMS via Shoutbomb

**Future Plugins:**
- **EmailPlugin** - Email delivery verification
- **SmsDirectPlugin** - Direct SMS (non-Shoutbomb)

> See [docs/VERIFICATION_SYSTEM_DESIGN.md](docs/VERIFICATION_SYSTEM_DESIGN.md) for complete architecture details and plugin development guide.

## Demo Data

Generate realistic demo data for testing the dashboard:

```bash
# Generate 30 days of demo data
php artisan notices:seed-demo

# Generate 60 days
php artisan notices:seed-demo --days=60

# Clear existing data and seed fresh
php artisan notices:seed-demo --fresh
```

This creates sample notifications, summaries, Shoutbomb deliveries, keyword usage, and registration snapshots.

## Models

### NotificationLog

Main notification tracking model with scopes for common queries:

```php
use Dcplibrary\Notices\Models\NotificationLog;

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
use Dcplibrary\Notices\Models\DailyNotificationSummary;
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
use Dcplibrary\Notices\Models\ShoutbombDelivery;

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
use Dcplibrary\Notices\Models\ShoutbombKeywordUsage;

// Get keyword statistics
$stats = ShoutbombKeywordUsage::getKeywordStats($startDate, $endDate);

// Get total usage for a keyword
$rhlUsage = ShoutbombKeywordUsage::getTotalUsageByKeyword('RHL', $startDate, $endDate);
```

## Configuration

The `config/notices.php` file contains all configuration options:

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

## Documentation

Comprehensive documentation is available in the `docs/` directory:

### Setup & Deployment
- **[Docker Setup Guide](docs/DOCKER_SETUP.md)** - Complete Docker-based installation
- **[Deployment Checklist](docs/DEPLOYMENT_CHECKLIST.md)** - Production deployment guide
- **[SQL Server Driver Installation](docs/SQL_SERVER_DRIVER_INSTALLATION.md)** - Fixing "could not find driver" error

### Usage & Development
- **[Dashboard Guide](docs/DASHBOARD.md)** - Using and customizing the built-in dashboard
- **[API Reference](docs/API.md)** - Complete API endpoint documentation
- **[Integration Guide](docs/INTEGRATION.md)** - Integrating with authentication systems
- **[Testing Guide](docs/TESTING.md)** - Running tests and writing new tests
- **[Screenshots Guide](docs/SCREENSHOTS.md)** - Adding visual documentation

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- MSSQL Server (for Polaris database)
- MySQL/MariaDB (for local cache)
- FTP extension enabled (for Shoutbomb imports)
- **SQL Server PDO driver** (see Troubleshooting below)

## Troubleshooting

### ❌ "could not find driver" error

If you see `could not find driver` when testing the Polaris connection, the SQL Server PDO driver is not installed.

**Quick Fix (Linux):**
```bash
# Option 1: Install version-specific package
sudo apt-get install php8.4-sybase freetds-common

# Option 2: Install generic package (if php8.4-sybase unavailable)
sudo apt-get install php-sybase freetds-common

# Restart PHP-FPM
sudo service php8.4-fpm restart

# Update .env file
POLARIS_DB_DRIVER=dblib
```

> **Note:** If you encounter repository errors (403, package not available), see the detailed installation guide below for alternative methods.

**Detailed Installation Guide:**

See **[docs/SQL_SERVER_DRIVER_INSTALLATION.md](docs/SQL_SERVER_DRIVER_INSTALLATION.md)** for:
- Complete installation instructions for all platforms
- Driver comparison (FreeTDS vs Microsoft ODBC)
- Configuration examples
- Advanced troubleshooting

### Other Common Issues

**Connection timeout:**
- Verify SQL Server is accessible: `telnet your-server 1433`
- Check firewall rules
- Verify credentials in `.env`

**No data imported:**
- Check `POLARIS_REPORTING_ORG_ID` matches your library's ID
- Verify date range: `--days=7` or `--start-date/--end-date`
- Check Laravel logs: `storage/logs/laravel.log`

**Dashboard blank:**
- Run: `php artisan notices:import`
- Then: `php artisan notices:aggregate`
- Verify data exists: Check `notification_logs` table

## License

MIT License

## Author

Brian Lashbrook - Daviess County Public Library

## Support

For issues or questions, please contact the developer or open an issue in the project repository.
