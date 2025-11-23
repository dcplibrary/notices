# Notices Package Architecture Documentation

## Table of Contents

1. [Overview](#overview)
2. [Controllers](#controllers)
3. [Services](#services)
4. [Commands](#commands)
5. [Models](#models)
6. [Data Flow](#data-flow)
7. [View Integration](#view-integration)
8. [Plugin Architecture](#plugin-architecture)

---

## Overview

The Notices package is a Laravel application that tracks, verifies, and analyzes patron notifications from the Polaris ILS (Integrated Library System). It integrates with Shoutbomb (SMS/Voice provider) to verify delivery status across multiple channels: Email, SMS, Voice, and Mail.

### Key Capabilities

- Import notification logs from Polaris MSSQL database
- Track Shoutbomb SMS/Voice submissions and deliveries
- Verify complete notification lifecycle (Created → Submitted → Verified → Delivered)
- Pre-aggregate data for fast dashboard performance
- Provide troubleshooting tools for failed notifications
- Export data in CSV/JSON/SQL formats
- RESTful API for programmatic access

---

## Controllers

Controllers handle HTTP requests and render views or return JSON responses. They are thin layers that delegate business logic to services.

### DashboardController (`src/Http/Controllers/DashboardController.php`)

**Purpose:** Main dashboard interface providing visualization and analytics.

**Routes:**
- `GET /notices` - Dashboard overview
- `GET /notices/list` - Searchable notification list
- `GET /notices/list/{id}` - Individual notification detail
- `GET /notices/analytics` - Analytics with charts
- `GET /notices/shoutbomb` - Shoutbomb statistics
- `GET /notices/verification` - Verification search
- `GET /notices/verification/{id}` - Notification timeline
- `GET /notices/verification/patron/{barcode}` - Patron history
- `GET /notices/troubleshooting` - Failure analysis
- `POST /notices/troubleshooting/export` - Export failures to CSV
- `POST /notices/verification/export` - Export verification results
- `POST /notices/verification/patron/{barcode}/export` - Export patron history

**Data Sources:**
- `DailyNotificationSummary` - Pre-aggregated statistics
- `NotificationLog` - Individual notification records
- `NoticeVerificationService` - Verification status and timeline
- `NotificationType` and `DeliveryMethod` - Reference data for filters

**View Interactions:**
- Passes aggregated statistics to `dashboard.index` view
- Passes paginated notification lists to `dashboard.list` view
- Passes verification timeline to `dashboard.verification.show` view
- Passes failure breakdowns to `dashboard.troubleshooting` view
- Generates CSV downloads for exports (no view, direct download)

**Example Data Flow:**
```php
// Dashboard index
1. User visits /notices
2. Controller queries DailyNotificationSummary::getAggregatedTotals($startDate, $endDate)
3. Controller passes totals, breakdowns, and date range to view
4. View renders statistics, charts, and filters
5. View displays data using Blade components and JavaScript charting
```

---

### SettingsController (`src/Http/Controllers/SettingsController.php`)

**Purpose:** Manage application settings, reference data, and configuration.

**Routes:**
- `GET /notices/settings` - List all settings
- `GET /notices/settings/{id}` - View/edit specific setting
- `POST /notices/settings/{id}` - Update setting
- `DELETE /notices/settings/{id}` - Delete setting
- `GET /notices/settings/reference-data` - Manage reference data
- `POST /notices/settings/reference-data` - Update reference data
- `GET /notices/settings/sync` - Sync management interface
- `GET /notices/settings/export` - Export & backup interface
- `POST /notices/settings/tools/normalize-phones` - Normalize phone numbers

**Data Sources:**
- `NotificationSetting` - Database-backed settings
- `NotificationType`, `DeliveryMethod`, `NotificationStatus` - Reference tables
- `SyncLog` - Recent sync operations

**View Interactions:**
- Passes editable settings to `settings.index` view
- Passes reference data with enable/disable controls to `settings.reference-data` view
- Passes sync status and logs to `settings.sync` view
- Provides forms for editing settings with validation

**Access Control:**
- All routes require "Computer Services" group membership
- Settings marked as `is_editable = false` cannot be modified via UI

---

### SyncController (`src/Http/Controllers/SyncController.php`)

**Purpose:** Orchestrate data synchronization from external sources.

**Routes (all return JSON):**
- `POST /notices/sync/all` - Run full sync pipeline
- `POST /notices/sync/polaris` - Import from Polaris only
- `POST /notices/sync/shoutbomb` - Import Shoutbomb submissions
- `POST /notices/sync/shoutbomb-reports` - Import delivery reports
- `POST /notices/sync/shoutbomb-to-logs` - Sync phone notices to logs
- `POST /notices/sync/aggregate` - Run aggregation
- `GET /notices/sync/test-connections` - Test database/FTP connections
- `GET /notices/sync/logs` - View sync operation logs

**Data Sources:**
- Delegates to service classes for actual imports
- Creates `SyncLog` records to track operations
- Returns JSON with status, record counts, and errors

**Services Used:**
- `PolarisImportService` - Import from Polaris
- `ShoutbombSubmissionImporter` - Import submissions
- `ShoutbombFTPService` - Import delivery reports
- `PolarisPhoneNoticeImporter` - Import phone notices
- `NotificationAggregatorService` - Run aggregation

**View Interactions:**
- No views rendered (JSON API)
- Used via AJAX from settings/sync page
- Displays results in JavaScript toast notifications

**Example Data Flow:**
```php
// Full sync
1. User clicks "Sync All" button in settings
2. JavaScript POSTs to /notices/sync/all
3. Controller calls SyncAllCommand pipeline:
   a. PolarisImportService->importNotifications()
   b. ShoutbombSubmissionImporter->import()
   c. SyncShoutbombToLogs command
   d. NotificationAggregatorService->aggregateYesterday()
4. Controller creates SyncLog for each step
5. Returns JSON: {status: 'success', records: {...}, errors: [...]}
6. JavaScript updates UI with results
```

---

### ExportController (`src/Http/Controllers/ExportController.php`)

**Purpose:** Handle data export and backup operations.

**Routes:**
- `GET /notices/export/reference-data` - Export reference data as JSON
- `GET /notices/export/reference-data-sql` - Export reference data as SQL
- `POST /notices/export/notification-data` - Export notification logs (CSV/JSON)
- `POST /notices/export/database-backup` - Generate SQL backup

**Data Sources:**
- `NotificationType`, `DeliveryMethod`, `NotificationStatus` - Reference data
- `NotificationLog` - Notification data with relationships
- Direct database queries for SQL dumps

**View Interactions:**
- No views rendered (direct file downloads)
- Returns downloadable files with appropriate headers
- CSV exports include UTF-8 BOM for Excel compatibility

**Services Used:**
- `NoticeExportService` - Generate CSV exports

---

### API Controllers (`src/Http/Controllers/Api/`)

**Purpose:** Provide RESTful API access to notification data.

**Controllers:**
1. **NotificationController** - CRUD operations on notification logs
2. **SummaryController** - Aggregated summary data
3. **AnalyticsController** - Time series and trend data
4. **ShoutbombController** - Shoutbomb-specific endpoints
5. **VerificationController** - Verification status and timeline

**Authentication:**
- Protected by `auth:sanctum` middleware
- Requires API token

**Response Format:**
- All responses are JSON
- Pagination for list endpoints
- Includes metadata (total, per_page, current_page)
- Error responses follow standard format

---

## Services

Services contain the core business logic and data processing. Controllers and commands delegate to services.

### PolarisImportService (`src/Services/PolarisImportService.php`)

**Purpose:** Import notification logs from Polaris MSSQL database.

**Key Methods:**
- `importNotifications($days, $startDate, $endDate)` - Import by date range
- `importHistorical($startDate, $endDate)` - Full historical import (month-by-month)
- `testConnection()` - Test Polaris DB connectivity

**Data Flow:**
```
Polaris.NotificationLogRecords (MSSQL)
  ↓ Query with date filter
  ↓ Transform columns to local schema
  ↓ Skip existing records (check polaris_log_id)
  ↓ Batch insert (default: 1000 records)
  ↓ Store in notification_logs table
```

**Imported Data:**
- `polaris_log_id` - Primary key from Polaris
- `patron_id`, `patron_barcode` - Patron identifiers
- `notification_date` - When notification was created
- `notification_type_id` - Type (Hold Ready, Overdue, etc.)
- `delivery_option_id` - Delivery method (Email, SMS, Voice, Mail)
- `notification_status_id` - Detailed status code
- `delivery_string` - Phone number or email address
- Item counts: `holds_count`, `overdues_count`, `bills_count`, etc.

**Usage:**
- Called by `ImportNotifications` command
- Called by `SyncController` for manual imports
- Called by scheduler for hourly imports

**Error Handling:**
- Displays MSSQL driver installation instructions if connection fails
- Logs errors to Laravel log
- Continues processing on individual record failures

---

### ShoutbombFTPService (`src/Services/ShoutbombFTPService.php`)

**Purpose:** Download delivery reports from Shoutbomb FTP server.

**Key Methods:**
- `connect()` - Establish FTP connection (supports SSL, passive mode)
- `importMonthlyReports()` - Download monthly summary reports
- `importWeeklyReports()` - Download weekly breakdown reports
- `importDailyInvalidReports()` - Download invalid phone number reports
- `importDailyUndeliveredReports()` - Download undelivered voice reports
- `downloadFile($remoteFile)` - Download specific file
- `listFiles($directory)` - List files in FTP directory
- `testConnection()` - Test FTP connectivity

**Data Flow:**
```
Shoutbomb FTP Server
  ↓ Connect via FTP/FTPS
  ↓ List files in /reports directory
  ↓ Download CSV files
  ↓ Save to local storage
  ↓ Parse CSV with ShoutbombFileParser
  ↓ Import to shoutbomb_deliveries table
```

**Configuration:**
- `services.shoutbomb.ftp.host` - FTP server
- `services.shoutbomb.ftp.username` - FTP username
- `services.shoutbomb.ftp.password` - FTP password
- `services.shoutbomb.ftp.ssl` - Use FTPS
- `services.shoutbomb.ftp.passive` - Passive mode

**Report Types:**
1. **Monthly Reports** - Summary of deliveries for the month
2. **Weekly Reports** - Detailed breakdown by week
3. **Daily Invalid Reports** - Invalid phone numbers
4. **Daily Undelivered Reports** - Failed voice calls

---

### ShoutbombSubmissionImporter (`src/Services/ShoutbombSubmissionImporter.php`)

**Purpose:** Import Shoutbomb submission data from SQL files generated by Polaris.

**Key Methods:**
- `import()` - Find and process all submission files
- `importFile($filePath)` - Import specific file
- `parseSubmissionFile($filePath)` - Parse SQL format

**Data Flow:**
```
Polaris generates SQL files
  ↓ Files stored in configured directory
  ↓ Service finds *.sql files
  ↓ Parse with ShoutbombSubmissionParser
  ↓ Extract patron barcode, phone, item, notification type
  ↓ Determine delivery type from filename (voice/text)
  ↓ Import to shoutbomb_submissions table
  ↓ Track processed files to avoid duplicates
```

**File Naming Convention:**
- `holds_text_*.sql` - Hold notices via SMS
- `holds_voice_*.sql` - Hold notices via voice
- `overdue_text_*.sql` - Overdue notices via SMS
- `overdue_voice_*.sql` - Overdue notices via voice

**Configuration:**
- `services.shoutbomb.submission_files_path` - Directory containing SQL files

---

### PolarisPhoneNoticeImporter (`src/Services/PolarisPhoneNoticeImporter.php`)

**Purpose:** Import PhoneNotices.csv from Polaris (verification data).

**Key Methods:**
- `import($filePath)` - Import PhoneNotices.csv
- `parseFile($filePath)` - Parse CSV format

**Data Flow:**
```
Polaris exports PhoneNotices.csv
  ↓ Contains patron info, phone, item details
  ↓ Service parses CSV
  ↓ Import to polaris_phone_notices table
  ↓ This data proves notice was sent to Shoutbomb
```

**Imported Data:**
- `delivery_type` - voice or text
- `patron_barcode`, `first_name`, `last_name`
- `phone_number`, `email`
- `library_code`, `library_name`
- `item_barcode`, `title`
- `notice_date`

**Purpose in Verification:**
- This is the "Verified" step in the lifecycle
- Proves that Polaris sent the notice to Shoutbomb
- Used to match against `notification_logs`

---

### NoticeVerificationService (`src/Services/NoticeVerificationService.php`)

**Purpose:** Verify the complete lifecycle of a notification.

**Lifecycle Stages:**
1. **Created** - Exists in `notification_logs` (from Polaris)
2. **Submitted** - Matched in `shoutbomb_submissions` (sent to Shoutbomb)
3. **Verified** - Found in `polaris_phone_notices` (Polaris confirms submission)
4. **Delivered** - Status in `shoutbomb_deliveries` (Shoutbomb confirms delivery)

**Key Methods:**
- `verify($log)` - Verify single notification, return `VerificationResult`
- `verifyByPatron($barcode, $startDate, $endDate)` - Verify all notices for patron
- `getFailedNotices($startDate, $endDate)` - Get failed deliveries
- `getFailuresByReason($startDate, $endDate)` - Group failures by reason
- `getMismatches($startDate, $endDate)` - Detect verification gaps
- `getTroubleshootingSummary($startDate, $endDate)` - Summary statistics

**Matching Logic:**

**Step 1: Created**
```php
// Already in notification_logs
$log = NotificationLog::find($id);
```

**Step 2: Submitted**
```php
// Match to shoutbomb_submissions
ShoutbombSubmission::where('patron_barcode', $log->patron_barcode)
    ->whereDate('submitted_at', $log->notification_date)
    ->where('notification_type', $this->mapNotificationType($log->notification_type_id))
    ->first();
```

**Step 3: Verified**
```php
// Match to polaris_phone_notices
PolarisPhoneNotice::where('patron_barcode', $log->patron_barcode)
    ->whereDate('notice_date', $log->notification_date)
    ->first();
```

**Step 4: Delivered**
```php
// Match to shoutbomb_deliveries
ShoutbombDelivery::where('phone_number', $log->delivery_string)
    ->whereBetween('sent_date', [
        $log->notification_date->subHours(24),
        $log->notification_date->addHours(24)
    ])
    ->first();

// Check delivery.status: 'Delivered' vs 'Failed'
// If failed, capture failure_reason
```

**Status Determination:**
- **SUCCESS:** Created + Submitted + Verified + (Delivered OR no delivery record within 48h)
- **FAILED:** Any step failed OR delivery.status = 'Failed'
- **PENDING:** Created but missing subsequent steps

**Plugin Architecture:**
- Uses `PluginRegistry` to delegate verification
- `ShoutbombPlugin` handles SMS/Voice verification
- Email/Mail use default verification logic
- Extensible for new channels

**Used By:**
- `DashboardController` - Display verification timeline
- `VerificationController` (API) - Return verification status
- Troubleshooting dashboard - Identify failure patterns

---

### NotificationAggregatorService (`src/Services/NotificationAggregatorService.php`)

**Purpose:** Pre-aggregate notification data for fast dashboard performance.

**Key Methods:**
- `aggregateDate($date)` - Aggregate single date
- `aggregateDateRange($startDate, $endDate)` - Aggregate range
- `aggregateYesterday()` - Typical nightly job
- `reAggregateAll()` - Full historical re-aggregation

**Aggregation Logic:**
```php
// Group by date + notification_type_id + delivery_option_id
NotificationLog::query()
    ->selectRaw('
        DATE(notification_date) as summary_date,
        notification_type_id,
        delivery_option_id,
        COUNT(*) as total_sent,
        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as total_success,
        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as total_pending,
        SUM(holds_count) as total_holds,
        SUM(overdues_count) as total_overdues,
        COUNT(DISTINCT patron_barcode) as unique_patrons
    ')
    ->whereDate('notification_date', $date)
    ->groupBy('summary_date', 'notification_type_id', 'delivery_option_id')
    ->get();

// Upsert into daily_notification_summary
```

**Benefits:**
- Dashboard queries are instant (read from summary table)
- No complex JOINs or aggregations at request time
- Historical data can be re-aggregated if import logic changes

**Scheduled:**
- Runs daily at 12:30 AM via scheduler
- Called automatically after imports

---

### SettingsManager (`src/Services/SettingsManager.php`)

**Purpose:** Manage database-backed settings (dynamic configuration).

**Key Methods:**
- `get($key, $default = null, $scope = 'global', $scopeId = null)` - Get setting value
- `set($key, $value, $scope = 'global', $scopeId = null)` - Set setting value
- `has($key, $scope = 'global', $scopeId = null)` - Check if setting exists
- `forget($key, $scope = 'global', $scopeId = null)` - Delete setting
- `all($scope = 'global', $scopeId = null)` - Get all settings for scope

**Setting Scopes:**
- `global` - Application-wide settings
- `branch` - Branch-specific settings (scopeId = branch_id)
- `patron` - Patron-specific settings (scopeId = patron_id)

**Setting Types:**
- `string` - Plain text
- `int` - Integer
- `float` - Decimal number
- `bool` - Boolean (true/false)
- `json` - JSON object
- `encrypted` - Encrypted value (for passwords, API keys)

**Configuration Cascade:**
1. Check `notification_settings` table for scope + scopeId
2. Fall back to `notification_settings` for global scope
3. Fall back to `config('notices.setting_key')`
4. Return $default

**Used By:**
- `SettingsController` - Display/edit settings
- All services - Read configuration values
- Commands - Access dynamic configuration

---

### PolarisQueryService (`src/Services/PolarisQueryService.php`)

**Purpose:** Query Polaris database for supplemental data.

**Key Methods:**
- `getPatronDetails($patronId)` - Get patron name, email, phone
- `getItemDetails($itemRecordId)` - Get item title, barcode, call number
- `getBibliographicRecord($bibId)` - Get bibliographic record
- `getStaffLink($recordType, $recordId)` - Generate Polaris web link

**Caching:**
- Uses Laravel cache to avoid repeated queries
- Cache TTL: 1 hour (configurable)

**Used By:**
- `DashboardController` - Display patron/item details
- `NoticeVerificationService` - Enrich verification data
- `NoticeExportService` - Include patron/item names in exports

---

### NoticeExportService (`src/Services/NoticeExportService.php`)

**Purpose:** Generate CSV/JSON exports for verification and troubleshooting data.

**Key Methods:**
- `exportVerificationResults($startDate, $endDate)` - Export verification results
- `exportPatronHistory($barcode, $startDate, $endDate)` - Export patron history
- `exportFailures($startDate, $endDate)` - Export failure reports

**CSV Format:**
- Includes UTF-8 BOM for Excel compatibility
- Headers in first row
- Metadata in comments (exported by, timestamp)

**Columns Included:**
- Notification ID, Date, Type, Delivery Method
- Patron Barcode, Patron Name
- Verification Status (Created, Submitted, Verified, Delivered)
- Delivery Status (Success, Failed, Pending)
- Failure Reason (if failed)
- Item Details (Title, Barcode)

**Used By:**
- `DashboardController` - Export from verification/troubleshooting pages
- `ExportController` - Batch exports

---

## Commands

Artisan commands provide CLI interface for imports, syncs, and maintenance tasks.

### SyncAllCommand (`php artisan notices:sync-all`)

**Purpose:** Run full data synchronization pipeline.

**Pipeline:**
1. Import from Polaris (`notices:import-polaris`)
2. Import Shoutbomb submissions (`notices:import-shoutbomb-submissions`)
3. Import Shoutbomb reports (`notices:import-shoutbomb-reports`)
4. Sync phone notices to logs (`notices:sync-shoutbomb-to-logs`)
5. Run aggregation (`notices:aggregate`)

**Options:**
- `--days=30` - Days to sync for Shoutbomb
- `--skip-polaris` - Skip Polaris import
- `--skip-shoutbomb` - Skip Shoutbomb import
- `--skip-aggregate` - Skip aggregation

**Services Used:**
- `PolarisImportService`
- `ShoutbombSubmissionImporter`
- `ShoutbombFTPService`
- `PolarisPhoneNoticeImporter`
- `NotificationAggregatorService`

**Logging:**
- Creates `SyncLog` record for each step
- Logs errors but continues processing
- Displays progress bar and statistics

**Scheduled:**
- Can be scheduled via Laravel scheduler
- Typical: Run daily at 6:00 AM

---

### ImportNotifications (`php artisan notices:import-polaris`)

**Purpose:** Import notifications from Polaris database.

**Options:**
- `--days=1` - Import last N days
- `--start-date=2024-01-01` - Start date (Y-m-d)
- `--end-date=2024-01-31` - End date (Y-m-d)
- `--full` - Import all historical data (interactive)

**Services Used:**
- `PolarisImportService->importNotifications()`
- `NotificationAggregatorService->aggregateDateRange()` (automatic)

**Progress:**
- Displays progress bar
- Shows record count and time elapsed
- Displays skip count (duplicates)

**Scheduled:**
- Runs hourly via scheduler

---

### ImportShoutbombSubmissions (`php artisan notices:import-shoutbomb-submissions`)

**Purpose:** Import SQL submission files from Polaris.

**Services Used:**
- `ShoutbombSubmissionImporter->import()`

**File Processing:**
- Finds *.sql files in configured directory
- Parses each file with `ShoutbombSubmissionParser`
- Tracks processed files in database
- Skips already-processed files

**Scheduled:**
- Runs daily at 5:30 AM

---

### ImportShoutbombReports (`php artisan notices:import-shoutbomb-reports`)

**Purpose:** Import delivery reports from Shoutbomb FTP.

**Options:**
- `--type=all` - Report type (all, monthly, weekly, daily)

**Services Used:**
- `ShoutbombFTPService->importMonthlyReports()`
- `ShoutbombFTPService->importWeeklyReports()`
- `ShoutbombFTPService->importDailyInvalidReports()`
- `ShoutbombFTPService->importDailyUndeliveredReports()`

**Scheduled:**
- Runs daily at 9:00 AM

---

### SyncShoutbombToLogs (`php artisan notices:sync-shoutbomb-to-logs`)

**Purpose:** Sync Shoutbomb phone notices to notification_logs.

**What It Does:**
1. Find records in `polaris_phone_notices` not in `notification_logs`
2. Create corresponding `notification_log` records
3. Link to Polaris phone notices
4. Fill in patron/item details

**Options:**
- `--days=30` - Number of days to sync
- `--force` - Overwrite existing records

**Why This Matters:**
- Sometimes phone notices are created directly by Polaris without going through NotificationLogRecords
- This ensures all phone notices are tracked in the main notification_logs table

**Scheduled:**
- Runs as part of `sync-all` pipeline

---

### AggregateNotifications (`php artisan notices:aggregate`)

**Purpose:** Run data aggregation for analytics.

**Options:**
- `--days=1` - Days to aggregate (default: yesterday)
- `--start-date=2024-01-01` - Start date
- `--end-date=2024-01-31` - End date
- `--all` - Re-aggregate all historical data

**Services Used:**
- `NotificationAggregatorService->aggregateDate()`
- `NotificationAggregatorService->aggregateDateRange()`
- `NotificationAggregatorService->reAggregateAll()`

**Scheduled:**
- Runs daily at 12:30 AM

---

### TestConnections (`php artisan notices:test-connections`)

**Purpose:** Test external connections.

**Tests:**
1. Polaris MSSQL connection
2. Shoutbomb FTP connection

**Services Used:**
- `PolarisImportService->testConnection()`
- `ShoutbombFTPService->testConnection()`

**Output:**
- Connection status (success/failed)
- Error messages with troubleshooting tips
- MSSQL driver installation instructions (if needed)

---

### InstallCommand (`php artisan notices:install`)

**Purpose:** Interactive package setup wizard.

**Steps:**
1. Run migrations
2. Seed reference data (notification types, delivery methods, statuses)
3. Publish configuration file
4. Test Polaris connection
5. Test Shoutbomb FTP connection
6. Offer to generate demo data
7. Display next steps

**Interactive Prompts:**
- Confirm each step before proceeding
- Display helpful error messages
- Provide guidance on configuration

---

### Additional Commands

- `notices:import-polaris-phone-notices` - Import PhoneNotices.csv
- `notices:import-email-reports` - Import reports from email inbox
- `notices:backfill-status` - Backfill simplified status field
- `notices:diagnose` - Diagnose data quality issues
- `notices:inspect-delivery-methods` - Analyze delivery method usage
- `notices:list-shoutbomb-files` - List files on Shoutbomb FTP
- `notices:seed-demo` - Generate demo data
- `notices:normalize-phones` - Normalize phone number formatting

---

## Models

Models represent database tables and encapsulate data access logic.

### NotificationLog (`src/Models/NotificationLog.php`)

**Purpose:** Main notification tracking table. Represents a notification sent by Polaris to a patron.

**Table:** `notification_logs`

**Primary Key:** `id`

**Foreign Keys:**
- `polaris_log_id` → Polaris.NotificationLogRecords
- `notification_type_id` → notification_types
- `delivery_option_id` → delivery_methods
- `notification_status_id` → notification_statuses

**Key Fields:**
- `patron_id` - Polaris patron ID
- `patron_barcode` - Patron barcode
- `notification_date` - When notification was sent
- `notification_type_id` - Type (Hold Ready, Overdue, etc.)
- `delivery_option_id` - Delivery method (Email, SMS, Voice, Mail)
- `notification_status_id` - Detailed status code
- `status` - Simplified status (completed, failed, pending)
- `delivery_string` - Phone number or email address
- `phone`, `email` - Contact details
- `holds_count` - Number of holds on notification
- `overdues_count` - Number of overdue items
- `bills_count` - Number of bills
- `renews_count` - Number of renewals
- `checkins_count` - Number of check-ins

**Relationships:**
```php
// Belongs to reference data
belongsTo(NotificationType::class, 'notification_type_id')
belongsTo(DeliveryMethod::class, 'delivery_option_id')
belongsTo(NotificationStatus::class, 'notification_status_id')

// Has many verification records
hasMany(PolarisPhoneNotice::class, 'patron_barcode', 'patron_barcode')
hasMany(ShoutbombSubmission::class, 'patron_barcode', 'patron_barcode')
hasMany(ShoutbombDelivery::class, 'phone_number', 'delivery_string')
```

**Scopes:**
```php
whereType($typeId) // Filter by notification type
whereDeliveryMethod($methodId) // Filter by delivery method
whereStatus($status) // Filter by simplified status
whereDateRange($start, $end) // Filter by date range
completed() // Only completed notifications
failed() // Only failed notifications
pending() // Only pending notifications
```

**Virtual Attributes:**
```php
// Computed attributes
$log->patron_name // From PolarisPhoneNotice or Polaris DB
$log->patron_email // From related data
$log->patron_phone // From related data
$log->items // Collection of items on notification
$log->total_items // Sum of all item counts
```

**Used By:**
- `DashboardController` - List and detail views
- `NoticeVerificationService` - Verification starting point
- `NotificationAggregatorService` - Source for aggregation
- `PolarisImportService` - Insert imported records

---

### DailyNotificationSummary (`src/Models/DailyNotificationSummary.php`)

**Purpose:** Pre-aggregated daily statistics for fast dashboard queries.

**Table:** `daily_notification_summary`

**Primary Key:** `id`

**Unique Constraint:** `summary_date + notification_type_id + delivery_option_id`

**Key Fields:**
- `summary_date` - Date of aggregation
- `notification_type_id` - Notification type
- `delivery_option_id` - Delivery method
- `total_sent` - Total notifications sent
- `total_success` - Total successful deliveries
- `total_failed` - Total failed deliveries
- `total_pending` - Total pending deliveries
- `total_holds` - Total holds
- `total_overdues` - Total overdues
- `total_bills` - Total bills
- `total_renews` - Total renewals
- `total_checkins` - Total check-ins
- `unique_patrons` - Distinct patron count
- `success_rate` - Percentage successful
- `failure_rate` - Percentage failed
- `aggregated_at` - When aggregated

**Static Methods:**
```php
// Get aggregated totals for date range
DailyNotificationSummary::getAggregatedTotals($startDate, $endDate)

// Returns:
[
    'total_sent' => 1000,
    'total_success' => 950,
    'total_failed' => 50,
    'total_pending' => 0,
    'success_rate' => 95.0,
    'failure_rate' => 5.0,
    'unique_patrons' => 500,
    // ... item totals
]

// Get breakdown by notification type
DailyNotificationSummary::getBreakdownByType($startDate, $endDate)

// Returns array grouped by notification_type_id

// Get breakdown by delivery method
DailyNotificationSummary::getBreakdownByDelivery($startDate, $endDate)

// Returns array grouped by delivery_option_id
```

**Used By:**
- `DashboardController` - Display statistics
- `SummaryController` (API) - Return aggregated data
- `AnalyticsController` (API) - Time series data

**Benefits:**
- Instant dashboard queries (no aggregation at request time)
- Can query years of data without performance issues
- Supports drill-down by type and delivery method

---

### ShoutbombSubmission (`src/Models/ShoutbombSubmission.php`)

**Purpose:** Represents a notification submitted to Shoutbomb (from SQL files).

**Table:** `shoutbomb_submissions`

**Primary Key:** `id`

**Key Fields:**
- `notification_type` - holds, overdue, renew
- `patron_barcode` - Patron barcode
- `phone_number` - Phone number
- `title` - Item title
- `item_record_id` - Polaris item ID
- `bibliographic_record_id` - Polaris bib ID
- `pickup_date` - Pickup date (for holds)
- `expiration_date` - Expiration date (for holds)
- `submitted_at` - When submitted to Shoutbomb
- `delivery_type` - voice or text
- `source_file` - Original SQL filename

**Relationships:**
```php
// Can match to notification_logs
belongsTo(NotificationLog::class, 'patron_barcode', 'patron_barcode')
```

**Scopes:**
```php
whereType($type) // Filter by notification type (holds, overdue, renew)
whereDeliveryType($type) // Filter by delivery type (voice, text)
whereDateRange($start, $end) // Filter by submitted_at
```

**Used By:**
- `NoticeVerificationService` - Match to notification_logs (Step 2: Submitted)
- `ShoutbombSubmissionImporter` - Insert imported records

**Purpose in Verification:**
- Proves that notification was SENT to Shoutbomb
- Matches to `notification_logs` by patron_barcode + date + type

---

### PolarisPhoneNotice (`src/Models/PolarisPhoneNotice.php`)

**Purpose:** Represents a record from PhoneNotices.csv (Polaris export).

**Table:** `polaris_phone_notices` (formerly `shoutbomb_phone_notices`)

**Primary Key:** `id`

**Key Fields:**
- `delivery_type` - voice or text
- `patron_barcode` - Patron barcode
- `first_name`, `last_name` - Patron name
- `phone_number` - Phone number
- `email` - Email address
- `library_code`, `library_name` - Home library
- `item_barcode` - Item barcode
- `title` - Item title
- `notice_date` - When notice was created
- `source_file` - PhoneNotices.csv

**Relationships:**
```php
// Can match to notification_logs
belongsTo(NotificationLog::class, 'patron_barcode', 'patron_barcode')
```

**Scopes:**
```php
whereDeliveryType($type) // Filter by delivery type
whereDateRange($start, $end) // Filter by notice_date
wherePatron($barcode) // Filter by patron barcode
```

**Used By:**
- `NoticeVerificationService` - Match to notification_logs (Step 3: Verified)
- `PolarisPhoneNoticeImporter` - Insert imported records
- `NotificationLog` - Virtual attribute `patron_name`

**Purpose in Verification:**
- VERIFICATION data - proves notice was sent to Shoutbomb from Polaris perspective
- This file is generated by Polaris after submitting to Shoutbomb
- Confirms that Polaris successfully handed off to Shoutbomb

---

### ShoutbombDelivery (`src/Models/ShoutbombDelivery.php`)

**Purpose:** Represents actual delivery status from Shoutbomb reports.

**Table:** `shoutbomb_deliveries`

**Primary Key:** `id`

**Key Fields:**
- `patron_barcode` - Patron barcode
- `phone_number` - Phone number
- `delivery_type` - SMS, Voice
- `message_type` - Type of notification
- `sent_date` - When delivered/attempted
- `status` - Delivered, Failed, Invalid
- `carrier` - Phone carrier
- `failure_reason` - Why it failed (if failed)
- `report_file` - Source report filename
- `report_type` - monthly, weekly, daily

**Scopes:**
```php
delivered() // status = 'Delivered'
failed() // status = 'Failed'
invalid() // status = 'Invalid'
whereDeliveryType($type) // Filter by delivery type
whereDateRange($start, $end) // Filter by sent_date
```

**Used By:**
- `NoticeVerificationService` - Match to notification_logs (Step 4: Delivered)
- `ShoutbombFTPService` - Insert imported records
- Troubleshooting dashboard - Show failures by reason

**Purpose in Verification:**
- Tracks actual DELIVERY outcome from Shoutbomb
- Provides failure reasons for troubleshooting
- Matches to `notification_logs` by phone_number + date

---

### NotificationType (`src/Models/NotificationType.php`)

**Purpose:** Reference data for notification types.

**Table:** `notification_types`

**Primary Key:** `notification_type_id` (matches Polaris IDs)

**Key Fields:**
- `notification_type_id` - Primary key (1, 2, 3, etc.)
- `description` - Display name (e.g., "Hold Ready for Pickup")
- `label` - Custom label override (optional)
- `enabled` - Show/hide in dashboard
- `display_order` - Sort order

**Common Types:**
- 1: Hold Ready for Pickup
- 2: Overdue Notice
- 3: Bill Notice
- 4: Renewal Notice
- 5: Check-in Notice
- 12: Due Soon Notice

**Scopes:**
```php
enabled() // Only enabled types
ordered() // Sort by display_order
```

**Used By:**
- `DashboardController` - Filter dropdowns
- `SettingsController` - Reference data management
- `NotificationLog` - Relationship

---

### DeliveryMethod (`src/Models/DeliveryMethod.php`)

**Purpose:** Reference data for delivery methods.

**Table:** `delivery_methods`

**Primary Key:** `delivery_option_id` (matches Polaris IDs)

**Key Fields:**
- `delivery_option_id` - Primary key (1, 2, 3, 4)
- `delivery_option` - System name (e.g., "Phone-Text")
- `description` - Display name (e.g., "SMS")
- `label` - Custom label override (optional)
- `enabled` - Show/hide in dashboard
- `display_order` - Sort order

**Common Methods:**
- 1: Email
- 2: SMS (Phone-Text)
- 3: Voice (Phone-Voice)
- 4: Mail

**Scopes:**
```php
enabled() // Only enabled methods
ordered() // Sort by display_order
```

**Used By:**
- `DashboardController` - Filter dropdowns
- `SettingsController` - Reference data management
- `NotificationLog` - Relationship

---

### NotificationStatus (`src/Models/NotificationStatus.php`)

**Purpose:** Reference data for detailed status codes.

**Table:** `notification_statuses`

**Primary Key:** `notification_status_id` (matches Polaris IDs)

**Key Fields:**
- `notification_status_id` - Primary key
- `description` - Status description
- `label` - Custom label override
- `category` - Simplified category (completed, failed, pending)
- `enabled` - Show/hide
- `display_order` - Sort order

**Categories:**
- **completed:** Successfully sent/delivered
- **failed:** Delivery failed
- **pending:** In progress or queued

**Used By:**
- `NotificationLog` - Relationship
- `BackfillNotificationStatus` command - Map status_id to simplified status

---

### NotificationSetting (`src/Models/NotificationSetting.php`)

**Purpose:** Database-backed settings (dynamic configuration).

**Table:** `notification_settings`

**Primary Key:** `id`

**Unique Constraint:** `scope + scope_id + group + key`

**Key Fields:**
- `scope` - global, branch, patron, etc.
- `scope_id` - ID for scoped settings
- `group` - Setting group (integrations, scheduler, etc.)
- `key` - Setting key
- `value` - Setting value (JSON encoded if complex)
- `type` - Data type (string, int, bool, float, json, encrypted)
- `description` - Help text
- `validation_rules` - Laravel validation rules
- `is_editable` - Can be edited via UI
- `is_sensitive` - Hide value in UI
- `updated_by` - Who last updated
- `updated_at` - When updated

**Scopes:**
```php
global() // scope = 'global'
forScope($scope, $scopeId) // Filter by scope
editable() // is_editable = true
```

**Used By:**
- `SettingsManager` - Get/set settings
- `SettingsController` - Display/edit settings
- All services - Read configuration

**Example Settings:**
- `integrations.shoutbomb_reports.enabled` - Enable Shoutbomb reports integration
- `scheduler.import_polaris.enabled` - Enable hourly Polaris import
- `scheduler.import_polaris.schedule` - Cron expression for import
- `dashboard.default_date_range` - Default date range for dashboard

---

### SyncLog (`src/Models/SyncLog.php`)

**Purpose:** Tracks sync/import operations.

**Table:** `sync_logs`

**Primary Key:** `id`

**Key Fields:**
- `operation_type` - sync_all, import_polaris, import_shoutbomb, aggregate, etc.
- `status` - running, completed, completed_with_errors, failed
- `started_at` - When operation started
- `completed_at` - When operation completed
- `records_processed` - Count of records
- `details` - JSON with operation details (e.g., date range, record counts)
- `error_message` - Error message if failed
- `user_id` - Who triggered it (null if scheduled)

**Scopes:**
```php
recent($limit = 10) // Recent operations
latestFor($operationType) // Latest operation of specific type
successful() // Only completed operations
failed() // Only failed operations
```

**Used By:**
- `SyncController` - Log all sync operations
- `SettingsController` - Display sync history
- Commands - Log scheduled operations

**Example Details:**
```json
{
    "date_range": "2024-01-01 to 2024-01-31",
    "records_imported": 10000,
    "records_skipped": 50,
    "time_elapsed": "2m 30s"
}
```

---

### Polaris Models (`src/Models/Polaris/`)

**Purpose:** Direct read-only models for Polaris database queries.

**Connection:** `polaris` (MSSQL)

**Models:**

1. **Patron** (`Polaris.Patrons`)
   - Query patron details (name, email, phone)
   - Used by `PolarisQueryService`

2. **ItemRecord** (`Polaris.ItemRecords`)
   - Query item details (title, barcode, call number)
   - Used by `PolarisQueryService`

3. **BibliographicRecord** (`Polaris.BibliographicRecords`)
   - Query bibliographic records
   - Used by `PolarisQueryService`

4. **PolarisNotificationLog** (`Polaris.NotificationLogRecords`)
   - Query notification logs directly
   - Used by `PolarisImportService`

**Note:** These models are read-only. Updates are not supported.

---

## Data Flow

### Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         DATA SOURCES                                 │
├─────────────────────────────────────────────────────────────────────┤
│  1. Polaris MSSQL Database (NotificationLogRecords)                 │
│  2. Shoutbomb FTP Server (Submission SQL files)                     │
│  3. Shoutbomb FTP Server (Delivery CSV reports)                     │
│  4. Polaris File Export (PhoneNotices.csv)                          │
│  5. Email Inbox (Shoutbomb reports via email)                       │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│                      IMPORT LAYER (Services)                         │
├─────────────────────────────────────────────────────────────────────┤
│  PolarisImportService         → notification_logs                    │
│  ShoutbombSubmissionImporter  → shoutbomb_submissions               │
│  ShoutbombFTPService          → shoutbomb_deliveries                │
│  PolarisPhoneNoticeImporter   → polaris_phone_notices               │
│  EmailReportService           → shoutbomb_deliveries                │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    SYNC & ENRICHMENT LAYER                           │
├─────────────────────────────────────────────────────────────────────┤
│  SyncShoutbombToLogs:                                                │
│    - Match polaris_phone_notices → notification_logs                │
│    - Fill in patron names, item details                             │
│    - Create missing notification_log records                         │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   VERIFICATION LAYER (Service)                       │
├─────────────────────────────────────────────────────────────────────┤
│  NoticeVerificationService:                                          │
│    - Match across tables:                                            │
│      notification_logs → shoutbomb_submissions                      │
│                       → polaris_phone_notices                       │
│                       → shoutbomb_deliveries                        │
│    - Build timeline: Created → Submitted → Verified → Delivered     │
│    - Determine status: success, failed, pending                      │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   AGGREGATION LAYER (Service)                        │
├─────────────────────────────────────────────────────────────────────┤
│  NotificationAggregatorService:                                      │
│    - Group by date + type + delivery                                │
│    - Calculate totals, rates, unique patrons                        │
│    - Store in daily_notification_summary                            │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER (Controllers)                  │
├─────────────────────────────────────────────────────────────────────┤
│  Web Controllers:                                                    │
│    - DashboardController   → Blade views (charts, tables, filters)  │
│    - SettingsController    → Settings views                         │
│    - SyncController        → JSON responses for AJAX                │
│    - ExportController      → CSV/JSON/SQL downloads                 │
│                                                                      │
│  API Controllers:                                                    │
│    - NotificationController → JSON (notification CRUD)              │
│    - SummaryController      → JSON (aggregated data)                │
│    - AnalyticsController    → JSON (time series)                    │
│    - ShoutbombController    → JSON (Shoutbomb data)                 │
│    - VerificationController → JSON (verification status)            │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Detailed Verification Flow

The verification process is the most complex part of the system:

```
┌────────────────────────────────────────────────────────────┐
│ STEP 1: CREATED                                            │
├────────────────────────────────────────────────────────────┤
│ Source: notification_logs table                            │
│ Imported from: Polaris.NotificationLogRecords              │
│                                                             │
│ Contains:                                                   │
│   - patron_barcode                                          │
│   - notification_date                                       │
│   - notification_type_id                                    │
│   - delivery_option_id                                      │
│   - status (completed/failed/pending)                       │
│                                                             │
│ Verification: Record exists in notification_logs           │
└────────────────────────────────────────────────────────────┘
                        ↓
┌────────────────────────────────────────────────────────────┐
│ STEP 2: SUBMITTED (Shoutbomb only)                         │
├────────────────────────────────────────────────────────────┤
│ Source: shoutbomb_submissions table                        │
│ Imported from: Polaris-generated SQL files                 │
│                                                             │
│ Matching Logic:                                             │
│   WHERE patron_barcode = notification_logs.patron_barcode   │
│   AND notification_type matches (holds/overdue)             │
│   AND DATE(submitted_at) = DATE(notification_date)          │
│                                                             │
│ Verification: Matching record found                        │
└────────────────────────────────────────────────────────────┘
                        ↓
┌────────────────────────────────────────────────────────────┐
│ STEP 3: VERIFIED (Shoutbomb only)                          │
├────────────────────────────────────────────────────────────┤
│ Source: polaris_phone_notices table                        │
│ Imported from: PhoneNotices.csv (Polaris export)           │
│                                                             │
│ Matching Logic:                                             │
│   WHERE patron_barcode = notification_logs.patron_barcode   │
│   AND DATE(notice_date) = DATE(notification_date)           │
│   AND item_barcode matches (if available)                   │
│                                                             │
│ Purpose: Proves Polaris sent notice to Shoutbomb           │
│ Verification: Matching record found                        │
└────────────────────────────────────────────────────────────┘
                        ↓
┌────────────────────────────────────────────────────────────┐
│ STEP 4: DELIVERED (Shoutbomb only)                         │
├────────────────────────────────────────────────────────────┤
│ Source: shoutbomb_deliveries table                         │
│ Imported from: Shoutbomb FTP delivery reports              │
│                                                             │
│ Matching Logic:                                             │
│   WHERE phone_number = notification_logs.delivery_string    │
│   AND sent_date BETWEEN (notification_date - 24h,           │
│                          notification_date + 24h)           │
│                                                             │
│ Status Check:                                               │
│   - status = 'Delivered' → SUCCESS                          │
│   - status = 'Failed' → FAILED (capture failure_reason)     │
│   - status = 'Invalid' → FAILED (invalid phone)             │
│                                                             │
│ Verification: Check delivery status                        │
└────────────────────────────────────────────────────────────┘
                        ↓
┌────────────────────────────────────────────────────────────┐
│ OVERALL STATUS DETERMINATION                               │
├────────────────────────────────────────────────────────────┤
│ SUCCESS:                                                    │
│   - Created ✓                                               │
│   - Submitted ✓ (if Shoutbomb)                              │
│   - Verified ✓ (if Shoutbomb)                               │
│   - Delivered ✓ OR no delivery record within 48h           │
│                                                             │
│ FAILED:                                                     │
│   - Any step failed                                         │
│   - OR delivery.status = 'Failed'                           │
│   - Capture failure_reason for troubleshooting              │
│                                                             │
│ PENDING:                                                    │
│   - Created but missing subsequent steps                    │
│   - May resolve later (e.g., waiting for delivery report)   │
└────────────────────────────────────────────────────────────┘
```

---

### Scheduled Operations

The system supports automated imports via Laravel scheduler:

```php
// Typical schedule (configured in NoticesServiceProvider):

// Hourly: Import from Polaris
Schedule::command('notices:import-polaris --days=1')
    ->hourly()
    ->appendOutputTo(storage_path('logs/import-polaris.log'));

// Daily 5:30 AM: Import Shoutbomb submissions
Schedule::command('notices:import-shoutbomb-submissions')
    ->dailyAt('05:30')
    ->appendOutputTo(storage_path('logs/import-shoutbomb.log'));

// Daily 9:00 AM: Import Shoutbomb delivery reports
Schedule::command('notices:import-shoutbomb-reports')
    ->dailyAt('09:00')
    ->appendOutputTo(storage_path('logs/import-reports.log'));

// Daily 9:30 AM: Import email reports (optional)
Schedule::command('notices:import-email-reports')
    ->dailyAt('09:30')
    ->appendOutputTo(storage_path('logs/import-email.log'));

// Daily 10:00 AM: Sync phone notices to logs
Schedule::command('notices:sync-shoutbomb-to-logs --days=7')
    ->dailyAt('10:00')
    ->appendOutputTo(storage_path('logs/sync-to-logs.log'));

// Daily 12:30 AM: Run aggregation (for yesterday)
Schedule::command('notices:aggregate --days=1')
    ->dailyAt('00:30')
    ->appendOutputTo(storage_path('logs/aggregate.log'));

// Alternative: Run full sync pipeline daily
Schedule::command('notices:sync-all')
    ->dailyAt('06:00')
    ->appendOutputTo(storage_path('logs/sync-all.log'));
```

---

## View Integration

Views are Blade templates that display data from controllers.

### View Directory Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php           # Main layout (includes nav, styles, scripts)
│   └── dashboard.blade.php     # Dashboard layout (extends app)
├── components/
│   ├── stat-card.blade.php     # Statistic display card
│   ├── chart.blade.php         # Chart.js wrapper
│   ├── filter-form.blade.php   # Date range filter
│   └── pagination.blade.php    # Custom pagination
├── dashboard/
│   ├── index.blade.php         # Main dashboard (stats, trends, charts)
│   ├── list.blade.php          # Notification list (searchable table)
│   ├── show.blade.php          # Notification detail view
│   ├── analytics.blade.php     # Analytics page (multiple charts)
│   ├── shoutbomb.blade.php     # Shoutbomb statistics
│   ├── verification/
│   │   ├── index.blade.php     # Verification search
│   │   ├── show.blade.php      # Notification timeline
│   │   └── patron.blade.php    # Patron history
│   └── troubleshooting.blade.php # Failure analysis
├── settings/
│   ├── index.blade.php         # Settings list
│   ├── edit.blade.php          # Edit setting
│   ├── reference-data.blade.php # Manage reference data
│   ├── sync.blade.php          # Sync management
│   └── export.blade.php        # Export & backup
└── errors/
    ├── 403.blade.php           # Forbidden
    ├── 404.blade.php           # Not found
    └── 500.blade.php           # Server error
```

---

### How Controllers Pass Data to Views

**Example: Dashboard Index**

```php
// DashboardController::index()
public function index(Request $request)
{
    // 1. Get date range from request (default: last 30 days)
    $startDate = $request->input('start_date', now()->subDays(30));
    $endDate = $request->input('end_date', now());

    // 2. Query aggregated data
    $totals = DailyNotificationSummary::getAggregatedTotals($startDate, $endDate);
    $breakdownByType = DailyNotificationSummary::getBreakdownByType($startDate, $endDate);
    $breakdownByDelivery = DailyNotificationSummary::getBreakdownByDelivery($startDate, $endDate);
    $dailyTrends = DailyNotificationSummary::getDailyTrends($startDate, $endDate);

    // 3. Get reference data for filters
    $notificationTypes = NotificationType::enabled()->ordered()->get();
    $deliveryMethods = DeliveryMethod::enabled()->ordered()->get();

    // 4. Pass data to view
    return view('dashboard.index', [
        'totals' => $totals,
        'breakdownByType' => $breakdownByType,
        'breakdownByDelivery' => $breakdownByDelivery,
        'dailyTrends' => $dailyTrends,
        'notificationTypes' => $notificationTypes,
        'deliveryMethods' => $deliveryMethods,
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);
}
```

**View: dashboard.index.blade.php**

```blade
@extends('layouts.dashboard')

@section('content')
<div class="container">
    <!-- Date Range Filter -->
    <x-filter-form
        :start-date="$startDate"
        :end-date="$endDate"
        :notification-types="$notificationTypes"
        :delivery-methods="$deliveryMethods"
    />

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-3">
            <x-stat-card
                title="Total Sent"
                :value="$totals['total_sent']"
                icon="envelope"
            />
        </div>
        <div class="col-md-3">
            <x-stat-card
                title="Success Rate"
                :value="$totals['success_rate'] . '%'"
                icon="check-circle"
                :trend="$totals['success_rate'] >= 95 ? 'up' : 'down'"
            />
        </div>
        <!-- More stat cards -->
    </div>

    <!-- Charts -->
    <div class="row">
        <div class="col-md-6">
            <x-chart
                type="pie"
                title="Breakdown by Type"
                :data="$breakdownByType"
            />
        </div>
        <div class="col-md-6">
            <x-chart
                type="bar"
                title="Breakdown by Delivery Method"
                :data="$breakdownByDelivery"
            />
        </div>
    </div>

    <!-- Daily Trends -->
    <div class="row">
        <div class="col-md-12">
            <x-chart
                type="line"
                title="Daily Trends"
                :data="$dailyTrends"
            />
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Initialize Chart.js charts
    // Handle filter form submission
    // Auto-refresh every 5 minutes
</script>
@endpush
```

---

### How Imported Data Flows to Views

**Step-by-Step Flow:**

1. **Import Stage**
   - Command runs: `php artisan notices:import-polaris --days=1`
   - Service executes: `PolarisImportService->importNotifications()`
   - Data inserted into: `notification_logs` table

2. **Aggregation Stage**
   - Command runs: `php artisan notices:aggregate --days=1`
   - Service executes: `NotificationAggregatorService->aggregateDate()`
   - Data aggregated into: `daily_notification_summary` table

3. **Request Stage**
   - User visits: `/notices`
   - Controller method: `DashboardController::index()`
   - Query executes: `DailyNotificationSummary::getAggregatedTotals()`

4. **Rendering Stage**
   - Controller passes data to: `dashboard.index` view
   - View renders: Statistics, charts, filters
   - JavaScript enhances: Interactive charts, AJAX updates

**Example Data Transformation:**

```
// Raw Polaris Data
NotificationLogRecords table:
| PatronID | NotificationDate | NotificationTypeID | DeliveryOptionID |
|----------|------------------|-------------------|-----------------|
| 123456   | 2024-01-15       | 1 (Hold)          | 2 (SMS)         |

                    ↓ PolarisImportService

// Imported Data
notification_logs table:
| id | polaris_log_id | patron_barcode | notification_date | notification_type_id | delivery_option_id | status    |
|----|---------------|----------------|-------------------|---------------------|-------------------|-----------|
| 1  | 789           | 123456         | 2024-01-15        | 1                   | 2                 | completed |

                    ↓ NotificationAggregatorService

// Aggregated Data
daily_notification_summary table:
| summary_date | notification_type_id | delivery_option_id | total_sent | total_success | success_rate |
|--------------|---------------------|-------------------|-----------|--------------|-------------|
| 2024-01-15   | 1 (Hold)            | 2 (SMS)           | 100       | 95           | 95.0        |

                    ↓ DashboardController

// View Data
$totals = [
    'total_sent' => 100,
    'total_success' => 95,
    'success_rate' => 95.0,
    // ...
];

                    ↓ Blade Template

// Rendered HTML
<div class="stat-card">
    <h3>Total Sent</h3>
    <p class="stat-value">100</p>
</div>
<div class="stat-card">
    <h3>Success Rate</h3>
    <p class="stat-value trend-up">95.0%</p>
</div>
```

---

### View Components

**Stat Card Component (`resources/views/components/stat-card.blade.php`)**

```blade
@props(['title', 'value', 'icon' => null, 'trend' => null])

<div class="stat-card">
    @if($icon)
        <i class="fa fa-{{ $icon }}"></i>
    @endif

    <h3 class="stat-title">{{ $title }}</h3>

    <p class="stat-value @if($trend) trend-{{ $trend }} @endif">
        {{ $value }}
    </p>

    @if($trend)
        <span class="trend-indicator">
            <i class="fa fa-arrow-{{ $trend === 'up' ? 'up' : 'down' }}"></i>
        </span>
    @endif
</div>
```

**Chart Component (`resources/views/components/chart.blade.php`)**

```blade
@props(['type', 'title', 'data'])

<div class="chart-container">
    <h4>{{ $title }}</h4>
    <canvas id="chart-{{ Str::slug($title) }}" data-type="{{ $type }}" data-chart-data="{{ json_encode($data) }}"></canvas>
</div>

@push('scripts')
<script>
    // Initialize Chart.js
    const ctx = document.getElementById('chart-{{ Str::slug($title) }}');
    const chartData = JSON.parse(ctx.dataset.chartData);

    new Chart(ctx, {
        type: '{{ $type }}',
        data: chartData,
        options: {
            responsive: true,
            // ... more options
        }
    });
</script>
@endpush
```

---

### AJAX Interactions

**Sync Button (settings/sync.blade.php)**

```blade
<button id="sync-all-btn" class="btn btn-primary">
    Sync All
</button>

<div id="sync-status"></div>

@push('scripts')
<script>
document.getElementById('sync-all-btn').addEventListener('click', function() {
    const statusDiv = document.getElementById('sync-status');
    statusDiv.innerHTML = '<p class="loading">Syncing...</p>';

    fetch('/notices/sync/all', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            statusDiv.innerHTML = `
                <p class="success">Sync completed!</p>
                <ul>
                    <li>Polaris: ${data.records.polaris} records</li>
                    <li>Shoutbomb: ${data.records.shoutbomb} records</li>
                    <li>Aggregated: ${data.records.aggregated} summaries</li>
                </ul>
            `;
        } else {
            statusDiv.innerHTML = `<p class="error">Sync failed: ${data.message}</p>`;
        }
    })
    .catch(error => {
        statusDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
    });
});
</script>
@endpush
```

---

## Plugin Architecture

The system uses a plugin pattern to support multiple notification channels.

### Plugin Interface

```php
// src/Contracts/NotificationPlugin.php

interface NotificationPlugin
{
    /**
     * Get the delivery option IDs this plugin handles
     */
    public function handlesDeliveryOptions(): array;

    /**
     * Verify a notification
     */
    public function verify(NotificationLog $log): VerificationResult;

    /**
     * Get plugin name
     */
    public function getName(): string;

    /**
     * Get plugin version
     */
    public function getVersion(): string;
}
```

### Plugin Registry

```php
// src/Services/PluginRegistry.php

class PluginRegistry
{
    protected array $plugins = [];

    /**
     * Register a plugin
     */
    public function register(NotificationPlugin $plugin): void
    {
        foreach ($plugin->handlesDeliveryOptions() as $deliveryOptionId) {
            $this->plugins[$deliveryOptionId] = $plugin;
        }
    }

    /**
     * Get plugin for delivery option
     */
    public function getPlugin(int $deliveryOptionId): ?NotificationPlugin
    {
        return $this->plugins[$deliveryOptionId] ?? null;
    }

    /**
     * Check if delivery option has plugin
     */
    public function hasPlugin(int $deliveryOptionId): bool
    {
        return isset($this->plugins[$deliveryOptionId]);
    }
}
```

### Shoutbomb Plugin Implementation

```php
// src/Plugins/ShoutbombPlugin.php

class ShoutbombPlugin implements NotificationPlugin
{
    public function handlesDeliveryOptions(): array
    {
        return [
            2, // SMS (Phone-Text)
            3, // Voice (Phone-Voice)
        ];
    }

    public function verify(NotificationLog $log): VerificationResult
    {
        $result = new VerificationResult();

        // Step 1: Created (already exists in notification_logs)
        $result->setCreated(true, $log->notification_date);

        // Step 2: Submitted to Shoutbomb
        $submission = ShoutbombSubmission::where('patron_barcode', $log->patron_barcode)
            ->whereDate('submitted_at', $log->notification_date)
            ->first();

        if ($submission) {
            $result->setSubmitted(true, $submission->submitted_at);
        }

        // Step 3: Verified in PhoneNotices.csv
        $phoneNotice = PolarisPhoneNotice::where('patron_barcode', $log->patron_barcode)
            ->whereDate('notice_date', $log->notification_date)
            ->first();

        if ($phoneNotice) {
            $result->setVerified(true, $phoneNotice->notice_date);
        }

        // Step 4: Delivered by Shoutbomb
        $delivery = ShoutbombDelivery::where('phone_number', $log->delivery_string)
            ->whereBetween('sent_date', [
                $log->notification_date->subHours(24),
                $log->notification_date->addHours(24)
            ])
            ->first();

        if ($delivery) {
            if ($delivery->status === 'Delivered') {
                $result->setDelivered(true, $delivery->sent_date);
            } else {
                $result->setDelivered(false, $delivery->sent_date);
                $result->setFailureReason($delivery->failure_reason);
            }
        }

        // Determine overall status
        $result->determineStatus();

        return $result;
    }

    public function getName(): string
    {
        return 'Shoutbomb SMS/Voice Plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

### Using Plugins in Verification Service

```php
// src/Services/NoticeVerificationService.php

class NoticeVerificationService
{
    public function __construct(
        protected PluginRegistry $pluginRegistry
    ) {}

    public function verify(NotificationLog $log): VerificationResult
    {
        // Check if there's a plugin for this delivery option
        if ($this->pluginRegistry->hasPlugin($log->delivery_option_id)) {
            $plugin = $this->pluginRegistry->getPlugin($log->delivery_option_id);
            return $plugin->verify($log);
        }

        // Default verification for Email, Mail, etc.
        return $this->defaultVerification($log);
    }

    protected function defaultVerification(NotificationLog $log): VerificationResult
    {
        $result = new VerificationResult();

        // Email and Mail only have "Created" status
        // Mark as created if exists in notification_logs
        $result->setCreated(true, $log->notification_date);

        // Check status field for success/failure
        if ($log->status === 'completed') {
            $result->setDelivered(true, $log->notification_date);
        } elseif ($log->status === 'failed') {
            $result->setDelivered(false, $log->notification_date);
        }

        $result->determineStatus();

        return $result;
    }
}
```

### Registering Plugins

```php
// src/NoticesServiceProvider.php

public function boot()
{
    // Register plugins
    $this->app->singleton(PluginRegistry::class, function ($app) {
        $registry = new PluginRegistry();

        // Register Shoutbomb plugin
        $registry->register(new ShoutbombPlugin());

        // Future: Register other plugins
        // $registry->register(new EmailPlugin());
        // $registry->register(new MailPlugin());

        return $registry;
    });
}
```

### Benefits of Plugin Architecture

1. **Extensibility:** New notification channels can be added without modifying core code
2. **Separation of Concerns:** Each channel's verification logic is isolated
3. **Testability:** Plugins can be tested independently
4. **Maintainability:** Changes to one channel don't affect others
5. **Flexibility:** Plugins can be enabled/disabled at runtime

---

## Summary

This architecture documentation provides a comprehensive overview of the Notices package:

- **Controllers** handle HTTP requests and delegate to services
- **Services** contain business logic for imports, verification, and aggregation
- **Commands** provide CLI interface for scheduled operations
- **Models** represent database tables and encapsulate data access
- **Views** display data using Blade templates and JavaScript
- **Plugins** enable extensibility for new notification channels

The data flows from external sources (Polaris, Shoutbomb) through import services, gets enriched and verified through matching algorithms, aggregated for performance, and finally presented through web dashboards and RESTful APIs.
