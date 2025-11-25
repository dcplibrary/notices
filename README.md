[![PHP Composer](https://github.com/dcplibrary/notices/actions/workflows/php.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/php.yml) [![Semantic-Release](https://github.com/dcplibrary/notices/actions/workflows/semantic-release.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/semantic-release.yml)

# Polaris Notices Package

**Track, analyze, and troubleshoot every library notification sent to your patrons - from creation to delivery.**

A Laravel package that connects to your Polaris ILS database and Shoutbomb delivery service to give you complete visibility into notification delivery across all channels (Email, SMS, Voice, Mail).

---

## 📖 Table of Contents

- [What This Package Does](#what-this-package-does)
- [Why Use This Package?](#why-use-this-package)
- [Quick Start](#quick-start)
- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [First-Time Setup](#first-time-setup)
- [Usage](#usage)
- [Dashboard](#dashboard)
- [Documentation](#documentation)
- [Troubleshooting](#troubleshooting)
- [Master Notifications Data Model](#master-notifications-data-model)

---

## What This Package Does

This package solves a common problem in libraries: **"Did my patron actually receive their notification?"**

When your library sends notifications (hold ready, overdue, renewal reminders), they pass through multiple systems:
1. **Polaris ILS** creates the notification
2. **Delivery services** (Shoutbomb, email servers) send the message
3. **Patrons** receive (or don't receive) the notification

This package **bridges these systems** by:
- 📊 Importing notification data from Polaris MSSQL database
- 📥 Importing delivery reports from Shoutbomb FTP
- 🔍 Matching them together to verify delivery
- 📈 Providing a dashboard to visualize success/failure rates
- 🚨 Identifying and troubleshooting delivery failures

### Real-World Example

**Without this package:**
> "Patron says they never got their hold notification. Let me check... Polaris shows it was sent... Shoutbomb shows... wait, I need to log into the FTP... download the CSV... search for their phone number... hmm, shows 'invalid number' but Polaris doesn't know that..."

**With this package:**
> Visit `yourapp.com/notices/verification`, search patron barcode → See complete timeline: Created ✓, Submitted ✓, Verified ✗ (Invalid phone number). Export list of all invalid numbers for cleanup.

---

## Why Use This Package?

✅ **Single Dashboard** - All notification data in one place instead of scattered across systems
✅ **Automatic Verification** - Matches Polaris notifications with delivery confirmations
✅ **Proactive Problem Detection** - Identifies opt-outs, invalid phone numbers, and system failures
✅ **Historical Analysis** - Track trends, success rates, and delivery performance over time
✅ **Patron-Specific View** - See complete notification history for individual patrons
✅ **No Manual FTP Downloads** - Automated imports from Shoutbomb
✅ **Works with Existing Systems** - Read-only connection to Polaris, doesn't modify your ILS

---

## Quick Start

**New to Laravel?** Don't worry! This guide assumes you have a Laravel application running and walks you through everything else.

### 1️⃣ Install the Package

```bash
composer require dcplibrary/notices
```

### 2️⃣ Publish Configuration

```bash
php artisan vendor:publish --tag=notices-config
```

This creates `config/notices.php` where you'll configure your connections.

### 3️⃣ Configure Your Connections

Add these to your `.env` file (the values from your Polaris and Shoutbomb accounts):

```env
# Your Polaris database connection
POLARIS_DB_HOST=polaris-server.yourlibrary.org
POLARIS_DB_USERNAME=readonly_user
POLARIS_DB_PASSWORD=your_password
POLARIS_REPORTING_ORG_ID=3

# Your Shoutbomb FTP credentials (optional, but recommended)
SHOUTBOMB_FTP_HOST=ftp.shoutbomb.com
SHOUTBOMB_FTP_USERNAME=your_username
SHOUTBOMB_FTP_PASSWORD=your_password
```

### 4️⃣ Run Migrations

```bash
php artisan migrate
```

This creates the tables where notification data will be stored.

### 5️⃣ Test Your Connections

```bash
php artisan notices:test-connections
```

You should see ✅ green checkmarks for Polaris and Shoutbomb connections.

### 6️⃣ Import Your First Data

```bash
# Import last 7 days of notifications from Polaris
php artisan notices:import --days=7

# Import Shoutbomb delivery reports
php artisan notices:import-shoutbomb

# Project NotificationLog rows into master notifications/events
php artisan notices:sync-from-logs --days=7

# Create summary data for the dashboard
php artisan notices:aggregate --all
```

### 7️⃣ View the Dashboard

Visit `https://yourapp.com/notices` to see your notification data!

**🎉 That's it!** You now have a working notification tracking system.

---

## Features

- ✅ **Built-in Dashboard**: Visualize notification data out-of-the-box with charts and metrics
- ✅ **Verification System**: Track complete notice lifecycle from creation to delivery
- ✅ **Troubleshooting Dashboard**: Analyze failures and detect verification gaps
- ✅ **Plugin Architecture**: Modular design for easy channel additions
- ✅ **CSV Export**: Export verification data, patron history, and failure reports
- ✅ **RESTful API**: Access data programmatically for custom integrations
- ✅ **Master notifications lifecycle**: `notifications` + `notification_events` provide a channel-agnostic view of every notice, anchored to Polaris `NotificationLog`
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

---

## Prerequisites

**What You Need Before Installing:**

### Required
- ✅ **Laravel Application** (v10.x or v11.x) - This is a Laravel package, not a standalone application
- ✅ **PHP 8.1+** - Check with `php -v`
- ✅ **Composer** - PHP package manager
- ✅ **MySQL/MariaDB** - For storing cached notification data
- ✅ **Polaris ILS Access** - Read-only MSSQL credentials to your Polaris database
- ✅ **SQL Server PDO Driver** - See [installation guide](docs/SQL_SERVER_DRIVER_INSTALLATION.md) if you get "could not find driver" error

### Optional (but recommended)
- ⭕ **Shoutbomb FTP Access** - To import SMS/Voice delivery reports
- ⭕ **Email Account** - To import Shoutbomb reports via IMAP

### Not Sure If You Have What You Need?

After installing the package, run `php artisan notices:test-connections` to verify all connections.

---

## Installation

> **🚀 First-time with Laravel packages?** No problem! Follow these steps carefully and you'll be up and running in 10 minutes.

> **🐳 Prefer Docker?** See [Docker Setup Guide](docs/DOCKER_SETUP.md) for a pre-configured Docker environment with all drivers installed.

### Step 1: Install via Composer

In your Laravel application directory, run:

```bash
composer require dcplibrary/notices
```

**What this does:** Downloads the package and its dependencies into your Laravel project.

---

### Step 2: Publish Configuration File

```bash
php artisan vendor:publish --tag=notices-config
```

**What this does:** Creates `config/notices.php` in your Laravel application where you can customize:
- Database connection settings
- FTP paths and credentials
- Import batch sizes
- Dashboard display preferences
- API settings

**💡 Tip:** You can edit this file later to fine-tune settings, but most configuration happens in your `.env` file (next step).

---

### Step 3: Configure Environment Variables

Open your `.env` file and add these settings:

```env
# ===================================
# REQUIRED: Polaris Database Connection
# ===================================
# This connects to your Polaris ILS database (read-only)
POLARIS_DB_DRIVER=dblib              # 'dblib' for Linux/Mac, 'sqlsrv' for Windows
POLARIS_DB_HOST=polaris.yourlibrary.org  # Your Polaris server hostname or IP
POLARIS_DB_PORT=1433                 # Standard MSSQL port
POLARIS_DB_DATABASE=Polaris          # Usually "Polaris"
POLARIS_DB_USERNAME=readonly_user    # Use a read-only account for security
POLARIS_DB_PASSWORD=your_password    # Database password
POLARIS_REPORTING_ORG_ID=3           # Your library's organization ID from Polaris

# ===================================
# OPTIONAL: Shoutbomb Integration
# ===================================
# Import SMS/Voice delivery reports from Shoutbomb FTP
SHOUTBOMB_ENABLED=true               # Set to false to skip Shoutbomb imports
SHOUTBOMB_FTP_HOST=ftp.shoutbomb.com
SHOUTBOMB_FTP_PORT=21
SHOUTBOMB_FTP_USERNAME=your_username # Provided by Shoutbomb
SHOUTBOMB_FTP_PASSWORD=your_password
SHOUTBOMB_FTP_PASSIVE=true           # Usually true
SHOUTBOMB_FTP_SSL=false              # Usually false for Shoutbomb

# ===================================
# OPTIONAL: Email Report Imports
# ===================================
# Import Shoutbomb failure reports from email (alternative to FTP)
EMAIL_REPORTS_ENABLED=false          # Enable if you want to import from email
EMAIL_HOST=imap.gmail.com            # Your email provider's IMAP server
EMAIL_PORT=993
EMAIL_USERNAME=notifications@yourlibrary.org
EMAIL_PASSWORD=your_app_password
EMAIL_ENCRYPTION=ssl
EMAIL_MAILBOX=INBOX                  # Mailbox to check for reports
EMAIL_FROM_ADDRESS=shoutbomb         # Filter emails from this sender
```

**💡 Configuration Tips:**

- **Polaris credentials:** Ask your ILS administrator for read-only access to the Polaris database
- **Organization ID:** Found in Polaris under System Administration → Organizations → Your Library
- **Shoutbomb FTP:** Credentials are provided when you sign up for Shoutbomb service
- **Security:** Use read-only database accounts and never commit `.env` to version control

**❓ Don't have Shoutbomb?** That's okay! The package still works without it - you just won't get SMS/Voice delivery verification.

---

### Step 4: Run Migrations

```bash
php artisan migrate
```

**What this does:** Creates database tables to store notification data. You'll see output like:

```
Migration table created successfully.
Migrating: 2025_01_01_000001_create_notification_logs_table
Migrated:  2025_01_01_000001_create_notification_logs_table (45.67ms)
...
```

**Tables created:**
- `notification_logs` - Stores notifications from Polaris (what was sent)
- `shoutbomb_deliveries` - Stores SMS/Voice delivery confirmations
- `shoutbomb_keyword_usage` - Tracks patron interactions (RHL, RA, OI keywords)
- `shoutbomb_registrations` - Subscriber counts and statistics
- `daily_notification_summary` - Pre-aggregated data for fast dashboard queries

---

### Step 5: Test Your Connections (Important!)

Before importing data, make sure everything is configured correctly:

```bash
php artisan notices:test-connections
```

**What you should see:**

```
✅ Polaris connection successful
✅ Shoutbomb FTP connection successful
✅ Email connection successful
```

**If you see errors:**
- ❌ **"could not find driver"** → You need to install the SQL Server PDO driver (see [Troubleshooting](#troubleshooting))
- ❌ **Connection refused** → Check your hostname and firewall settings
- ❌ **Login failed** → Double-check your username and password in `.env`

**💡 Tip:** You can test individual connections:
```bash
php artisan notices:test-connections --polaris   # Test only Polaris
php artisan notices:test-connections --shoutbomb # Test only Shoutbomb FTP
php artisan notices:test-connections --email     # Test only email
```

---

## First-Time Setup

**✅ Installation complete!** Now let's import some data and set up automated imports.

### Import Your First Data

Start with a small date range to make sure everything works:

```bash
# Import last 7 days of notifications from Polaris (takes 30 seconds - 2 minutes)
php artisan notices:import --days=7
```

**What this does:** Connects to your Polaris database and imports notification records. You'll see progress output like:

```
Importing notifications from 2025-01-18 to 2025-01-25...
Processing batch 1 of 15 (500 records)...
Processing batch 2 of 15 (500 records)...
...
✓ Imported 7,234 notifications
```

If you have Shoutbomb FTP configured, import delivery reports:

```bash
# Import SMS/Voice delivery confirmations (takes 1-5 minutes)
php artisan notices:import-shoutbomb
```

Finally, create aggregated summary data for the dashboard:

```bash
# Generate daily summaries for fast dashboard queries (takes ~30 seconds)
php artisan notices:aggregate --all
```

**🎉 Success!** You now have data in the system. Visit `https://yourapp.com/notices` to see your dashboard.

---

### Optional: Import Historical Data

Want to analyze more history? Import more data:

```bash
# Import last 30 days
php artisan notices:import --days=30

# Import last 90 days
php artisan notices:import --days=90

# Import specific date range
php artisan notices:import --start-date=2024-01-01 --end-date=2024-12-31

# Import EVERYTHING (warning: may take hours for large systems)
php artisan notices:import --full
```

**💡 Performance tip:** Large imports work best during off-hours. The command processes data in batches to avoid memory issues.

---

### Set Up Automated Daily Imports

You probably don't want to run these commands manually every day. Let's automate them!

**Option 1: Use the Package's Built-In Schedule**

The package automatically registers scheduled tasks. Just make sure your Laravel scheduler is running:

```bash
# Add this to your crontab (one time setup)
* * * * * cd /path/to/your/laravel && php artisan schedule:run >> /dev/null 2>&1
```

The package will automatically run these tasks daily:
- **5:30 AM** - Import patron lists
- **6:30 AM** - Import invalid phone reports
- **8:30 AM** - Import morning notifications
- **9:45 PM** - Sync master `notifications` + `notification_events` from `notification_logs` (NotificationLog)
- **10:00 PM** - Aggregate the day's data

See [Import Schedule Documentation](docs/IMPORT_SCHEDULE.md) for details on timing and customization.

**Option 2: Manual Schedule Configuration**

If you want more control, add this to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Import yesterday's notifications at 1 AM
    $schedule->command('notices:import --days=1')
        ->dailyAt('01:00')
        ->withoutOverlapping();

    // Import Shoutbomb reports at 9 AM
    $schedule->command('notices:import-shoutbomb')
        ->dailyAt('09:00')
        ->withoutOverlapping();

    // Aggregate yesterday's data at midnight
    $schedule->command('notices:aggregate')
        ->dailyAt('00:30')
        ->withoutOverlapping();
}
```

**🎉 You're all set!** The system will now automatically import and process notification data daily.

---

## Usage

Here's a quick reference for common tasks after initial setup:

### Daily Operations

**Import Today's Notifications:**
```bash
php artisan notices:import --days=1
```

**Import Shoutbomb Reports:**
```bash
php artisan notices:import-shoutbomb
```

**Update Dashboard Statistics:**
```bash
php artisan notices:aggregate
```

### Advanced Import Options

**Import specific date range:**
```bash
php artisan notices:import --start-date=2025-01-01 --end-date=2025-01-31
```

**Import specific Shoutbomb report types:**
```bash
php artisan notices:import-shoutbomb --type=monthly          # Monthly statistics
php artisan notices:import-shoutbomb --type=daily-invalid    # Invalid phone numbers
php artisan notices:import-shoutbomb --type=daily-undelivered # Voice failures
```

**Import Shoutbomb reports from email (alternative to FTP):**
```bash
php artisan notices:import-email-reports --mark-read --limit=100
```

**Re-aggregate historical data (if you import backfill data):**
```bash
php artisan notices:aggregate --start-date=2024-01-01 --end-date=2024-12-31
```

### Testing & Diagnostics

**Test all connections:**
```bash
php artisan notices:test-connections
```

**Test specific connection:**
```bash
php artisan notices:test-connections --polaris
```

**Generate demo data for testing dashboard:**
```bash
php artisan notices:seed-demo --days=30
```

---

## Dashboard

The package includes a **ready-to-use web dashboard** - no additional configuration needed! Just visit `https://yourapp.com/notices` after importing data.

### What You'll See

**📊 Overview Page**
- Key metrics (total notifications, success rate, failure rate)
- 7-day trend charts
- Distribution by notification type (Hold, Overdue, Renewal, etc.)
- Distribution by delivery method (Email, SMS, Voice, Mail)

**📋 Notifications List**
- Searchable, filterable table of all notifications
- Filter by date, type, delivery method, patron, status
- Export to CSV for reports

**📈 Analytics**
- Success/failure rates over time
- Performance comparisons by type and method
- Detailed breakdowns and trend analysis

**📱 Shoutbomb Page** (if using Shoutbomb)
- Current subscriber counts
- Growth trends over time
- Keyword usage statistics (RHL, RA, OI commands)

**🔍 Verification System**
- Search by patron barcode, phone, email, or item barcode
- Complete timeline for each notification (Created → Submitted → Verified → Delivered)
- Patron-specific delivery history
- Export patron reports

**🚨 Troubleshooting**
- Analyze failures by reason (invalid phone, opt-out, system error)
- Identify gaps (submitted but not delivered, verified but not confirmed)
- Export failure reports for patron data cleanup

### Authentication

By default, the dashboard requires authentication using Laravel's `auth` middleware. Configure this in `config/notices.php`:

```php
'dashboard' => [
    'enabled' => true,
    'middleware' => ['web', 'auth'],  // Add your own middleware here
],
```

### Customization

Want to modify the dashboard appearance?

```bash
php artisan vendor:publish --tag=notices-views
```

This copies all views to `resources/views/vendor/notices/` where you can edit them.

**Or disable the dashboard entirely** and build your own using the [API](#api):

```php
// config/notices.php
'dashboard' => ['enabled' => false],
```

---

## API

The package provides a **RESTful API** for programmatic access to notification data - perfect for custom integrations or building your own dashboard.

### Quick Example

```bash
# Get notifications from the last 7 days
GET /api/notices/logs?days=7

# Get aggregated statistics
GET /api/notices/analytics/overview?days=30

# Get Shoutbomb subscriber data
GET /api/notices/shoutbomb/registrations/latest
```

**Authentication:** Uses Laravel Sanctum by default (Bearer token).

**📚 Full API documentation with all endpoints:** [docs/API.md](docs/API.md)

---

## How It Works

Understanding the data flow helps you troubleshoot issues and customize the package:

```
┌─────────────────┐
│  Polaris ILS    │  Creates notifications (holds, overdues, renewals)
│  (MSSQL)        │
└────────┬────────┘
         │ Import via notices:import
         ▼
┌────────────────────────┐
│ notification_logs      │  Cached Polaris NotificationLog
└────────┬───────────────┘
         │
         │  Project via notices:sync-from-logs
         ▼
┌────────────────────────┐
│ notifications          │  Master, channel-agnostic notices
│ notification_events    │  Lifecycle events (queued→delivered/failed)
└────────┬───────────────┘
         │
         ├──► Dashboard & verification views
         │
         └──► Matched with ▼
                ┌─────────────────┐
                │  Shoutbomb FTP  │  Delivery confirmations
                │  Email Reports  │  (SMS/Voice results)
                └────────┬────────┘
                         │ Import via notices:import-shoutbomb
                         ▼
                ┌─────────────────┐
                │ shoutbomb_      │  Delivery tracking
                │ deliveries      │
                └────────┬────────┘
                         │
                         ▼
                ┌─────────────────┐
                │  Verification   │  Matches notifications
                │  System         │  with delivery data
                └────────┬────────┘
                         │
                         ▼
                    📊 Dashboard
```

**Key Concept:** The package connects TWO separate systems (Polaris ILS and Shoutbomb delivery) to give you a complete picture of notification delivery.

**📚 Detailed architecture documentation:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

### Models

### Notification (master)

The `Notification` model represents a **channel-agnostic, vendor-agnostic** notification record for a patron + item, anchored to Polaris `NotificationLog` but enriched from PhoneNotices and Shoutbomb exports.

```php
use Dcplibrary\Notices\Models\Notification;

// All notifications for a patron
$notifications = Notification::with(['events', 'notificationLog'])
    ->where('patron_barcode', $barcode)
    ->orderByDesc('notice_date')
    ->get();

// All notifications for an item
$itemNotifications = Notification::with('events')
    ->where('item_record_id', $itemRecordId)
    ->orderByDesc('notice_date')
    ->get();
```

Key points:

- `notification_log_id` stores Polaris `NotificationLogID` and links back to the cached `notification_logs` table.
- Snapshots of patron/item/title and delivery context live on this table for fast lookups.
- `events()` gives you the full lifecycle (queued → exported → submitted → delivered/failed).

### NotificationEvent

Lifecycle events tied to a `Notification` (queued, exported, submitted, phonenotices_recorded, delivered, failed, verified).

```php
use Dcplibrary\Notices\Models\NotificationEvent;

$failedSms = NotificationEvent::where('event_type', NotificationEvent::TYPE_FAILED)
    ->where('delivery_option_id', 8) // SMS
    ->whereBetween('event_at', [$start, $end])
    ->get();
```

Each event records:

- `event_type` (queued/exported/submitted/phonenotices_recorded/delivered/failed/verified)
- `event_at` (timestamp)
- `delivery_option_id` (channel at time of event)
- `status_code`/`status_text` (channel-aware result, e.g. "SMS Delivered", "Email Failed – Invalid Address")
- `source_table`/`source_id` to trace back to raw tables (`notification_logs`, `polaris_phone_notices`, `notice_failure_reports`, etc.)

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

### Architecture & Technical Details
- **[Architecture Documentation](docs/ARCHITECTURE.md)** - Complete system architecture: controllers, services, commands, models, data flow, and view integration
- **[Master Notifications Data Model](docs/MASTER_NOTIFICATIONS.md)** - How `notifications` + `notification_events` unify data from NotificationLog, PhoneNotices, and Shoutbomb
- **[Doctrine Annotations](docs/DOCTRINE_ANNOTATIONS.md)** - Why this package doesn't use doctrine/annotations (uses Laravel Eloquent)
- **[Package Merge Guide](docs/PACKAGE_MERGE.md)** - Migration guide for NoticeFailureReport merge from shoutbomb-reports package

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
