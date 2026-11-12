# Commands Reference

Complete documentation for all Artisan commands provided by the Notices package.

## Table of Contents

- [Primary Commands](#primary-commands)
  - [notices:import](#noticesimport)
- [Individual Import Commands](#individual-import-commands)
  - [notices:import-ftp](#noticesimport-ftp)
  - [notices:import-email](#noticesimport-email)
  - [notices:import-confirmations](#noticesimport-confirmations)
- [Utility Commands](#utility-commands)
  - [notices:install](#noticesinstall)
  - [notices:sync-lookup-tables](#noticessync-lookup-tables)
  - [notices:cleanup-import-logs](#noticescleanup-import-logs)
- [Date Options](#date-options)

---

## Primary Commands

### `notices:import`

Run the complete daily pipeline for importing and processing notifications.

**Signature:**
```bash
php artisan notices:import [options]
```

**Options:**
- `--date=YYYY-MM-DD` - Process specific date
- `--from=YYYY-MM-DD` - Start date for range
- `--to=YYYY-MM-DD` - End date for range
- `--days=N` - Number of days back from today (inclusive)

**Examples:**
```bash
# Process specific date
php artisan notices:import --date=2025-12-27

# Process date range
php artisan notices:import --from=2025-12-20 --to=2025-12-27

# Process last 7 days
php artisan notices:import --days=7

# Process today (default)
php artisan notices:import
```

**What it does:**

This command runs the complete daily pipeline in the following order:

1. **Import FTP exports** - Downloads and imports holds, renewals, and overdue notification files from Polaris/Shoutbomb FTP server
2. **Import notification log** - Imports NotificationLog exports (source of truth for Mail and Email)
3. **Import notification history** - Imports NotificationHistory exports for additional context
4. **Ingest emails** - Fetches failure and summary reports from Microsoft 365 via Graph API
5. **Deduplicate** - Merges duplicate notifications across exports
6. **Reconcile** - Updates notification statuses based on all available data
7. **Detect failures** - Identifies delivery failures and creates issue records

**When to use:**
- Daily automated runs via scheduled tasks
- Catching up on missed days after downtime
- Re-processing complete data for specific dates

**Notes:**
- Defaults to today if no date options provided
- Uses cached FTP files if already downloaded (won't re-download)
- Requires both FTP access and internet connectivity for complete run
- Safe to re-run multiple times for same date (idempotent)

---

## Individual Import Commands

Use these commands when you need to run specific parts of the pipeline separately.

### `notices:import-ftp`

Import Shoutbomb exports from FTP server only.

**Signature:**
```bash
php artisan notices:import-ftp [options]
```

**Options:**
- `--date=YYYY-MM-DD` - Process specific date
- `--from=YYYY-MM-DD` - Start date for range
- `--to=YYYY-MM-DD` - End date for range
- `--days=N` - Number of days back from today (inclusive)
- `--list` - List available files without importing

**Examples:**
```bash
# Import FTP files for specific date
php artisan notices:import-ftp --date=2025-12-27

# Import date range
php artisan notices:import-ftp --from=2025-12-20 --to=2025-12-27

# Import last 3 days
php artisan notices:import-ftp --days=3

# List available files without importing
php artisan notices:import-ftp --list
php artisan notices:import-ftp --list --date=2025-12-27
```

**What it does:**

Imports Shoutbomb notification exports from the FTP server:
- **Holds files** - Hold notifications (`holds_YYYY-MM-DD_HH-MM-SS.txt`)
- **Renewal files** - Renewal/almost overdue notifications (`renew_YYYY-MM-DD_HH-MM-SS.txt`)
- **Overdue files** - Overdue notifications (`overdue_YYYY-MM-DD_HH-MM-SS.txt`)

The command:
1. Connects to Polaris FTP server using configured credentials
2. Downloads files matching the date pattern
3. Caches files locally in storage directory
4. Imports raw notification data into database tables
5. Creates import batch records for tracking

**When to use:**
- When on VPN to access FTP but without internet for email import
- When testing FTP connectivity
- When you need to re-import specific dates
- When troubleshooting FTP-specific issues

**List mode (`--list`):**
- Connects to FTP and lists available files
- Does not download or import anything
- Useful for checking what files are available
- Can filter by date or show all files

**Important notes:**
- Downloaded files are cached locally
- Running `notices:import` later will use cached files (won't re-download)
- Requires VPN connection to access Polaris FTP server
- Files are organized by type (holds, renew, overdue) in local storage

**Configuration required:**
```env
POLARIS_USE_FTP=true
POLARIS_FTP_HOST=your-ftp-host
POLARIS_FTP_USERNAME=your-username
POLARIS_FTP_PASSWORD=your-ftp-password
```

---

### `notices:import-email`

Import Shoutbomb failure and summary reports from email via Microsoft Graph.

**Signature:**
```bash
php artisan notices:import-email [options]
```

**Options:**
- `--date=YYYY-MM-DD` - Process specific date
- `--from=YYYY-MM-DD` - Start date for range
- `--to=YYYY-MM-DD` - End date for range
- `--days=N` - Number of days back from today (inclusive)

**Examples:**
```bash
# Import emails for specific date
php artisan notices:import-email --date=2025-12-27

# Import date range
php artisan notices:import-email --from=2025-12-20 --to=2025-12-27

# Import last 7 days
php artisan notices:import-email --days=7
```

**What it does:**

Connects to Microsoft 365 mailbox and ingests Shoutbomb reports:

1. **Connects to Microsoft Graph API** - Authenticates using configured credentials
2. **Fetches emails** - Retrieves emails from specified mailbox folder for date range
3. **Parses failure reports** - Extracts data about:
   - Invalid phone numbers
   - Undelivered voice calls
   - Email delivery errors
   - SMS delivery failures
4. **Parses summary reports** - Extracts statistics from:
   - Monthly summary reports
   - Weekly summary reports
   - Daily delivery confirmations
5. **Creates records** - Stores data in:
   - `email_reports` table - Complete email metadata and content
   - `delivery_confirmations` table - Individual delivery confirmations/failures

**When to use:**
- After running `notices:import-ftp` while on VPN (to complete the pipeline)
- When re-processing email data for specific dates
- When troubleshooting email ingestion issues
- When you need to update failure data without re-importing FTP files

**Requirements:**
- Internet access
- Configured Microsoft Graph API credentials
- Access to mailbox with Shoutbomb reports

**Configuration required:**
```env
MICROSOFT_TENANT_ID=your-tenant-id
MICROSOFT_CLIENT_ID=your-client-id
MICROSOFT_CLIENT_SECRET=your-client-secret
MICROSOFT_MAILBOX=reports@yourlibrary.org
# Optional: the mail folder holding Shoutbomb reports (default: Shoutbomb_Reports)
MICROSOFT_FOLDER_SHOUTBOMB=Shoutbomb_Reports
```

**Email types processed:**
- **Failure reports** - Invalid phones, undelivered calls, error messages
- **Monthly summaries** - Statistics for previous month
- **Weekly summaries** - Statistics for previous week
- **Daily confirmations** - Per-day delivery confirmations

---

### `notices:import-confirmations`

Detect notification failures by correlating delivery confirmations.

**Signature:**
```bash
php artisan notices:import-confirmations [date]
```

**Arguments:**
- `date` - Date to analyze (YYYY-MM-DD), defaults to yesterday

**Examples:**
```bash
# Analyze specific date
php artisan notices:import-confirmations 2025-12-27

# Analyze yesterday (default)
php artisan notices:import-confirmations
```

**What it does:**

Analyzes notification data to detect delivery failures:

1. **Correlates confirmations** - Matches delivery confirmations from emails with notification records
2. **Detects failures** - Identifies:
   - Patrons not receiving expected notifications
   - Invalid phone numbers from voice/SMS failures
   - Invalid email addresses from email failures
   - LX Starter failures (electronic delivery stuck pending)
   - Null delivery preferences
3. **Creates issue records** - Generates `PatronDeliveryIssue` records for each problem found
4. **Marks failed strings** - Creates `FailedDeliveryString` records for invalid contact info

**When to use:**
- After both FTP and email imports complete
- When investigating specific delivery problems
- Typically runs automatically as part of `notices:import`
- When re-analyzing failure detection for a specific date

**Issue types detected:**
- `failed_delivery_string` - Invalid email, phone, or mailing address
- `lx_starter_failure` - Electronic notification not delivered by LX Starter
- `null_preference` - Patron has null delivery preference
- `manual_review` - Requires staff investigation
- `system_error` - Technical failure during processing

**Important notes:**
- Should run AFTER both `notices:import-ftp` and `notices:import-email`
- Defaults to yesterday because confirmation data arrives next day
- Creates issues only for unresolved problems (won't duplicate existing issues)
- Auto-resolves issues when conditions change (e.g., notification delivered)

---

## Utility Commands

### `notices:install`

Sets the package up in a host application. Run it once after `composer require`, and again whenever you want to change a setting.

```bash
php artisan notices:install
```

It asks for the settings the package reads and writes them to the application's `.env` file:

| Group | Settings |
| --- | --- |
| Microsoft Graph | `MICROSOFT_TENANT_ID`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`, `MICROSOFT_MAILBOX`, `MICROSOFT_FOLDER_SHOUTBOMB`, `MICROSOFT_EMAIL_FROM` |
| Polaris exports | `POLARIS_USE_FTP`, and when FTP is used `POLARIS_FTP_HOST`, `POLARIS_FTP_PORT`, `POLARIS_FTP_USERNAME`, `POLARIS_FTP_PASSWORD`, `POLARIS_FTP_PATH` |
| General | `NOTICES_TIMEZONE` |

Then it offers to run the database migrations and seed the Polaris lookup tables.

- Only these keys are touched. Every other line in `.env` is left as it was.
- Existing values are shown as defaults. Press Enter to keep one.
- Secrets are not echoed, and a secret left blank keeps the value already saved.
- An optional setting left blank is not added to the file.

**Options:**

| Option | Effect |
| --- | --- |
| `--no-migrate` | Do not run migrations |
| `--no-seed` | Do not seed the lookup tables |
| `--publish-config` | Also publish `config/notices.php` into the application |

If the application caches its configuration, run `php artisan config:clear` afterwards so the new values are read.

### `notices:sync-lookup-tables`

Recreate Polaris lookup tables in local database.

**Signature:**
```bash
php artisan notices:sync-lookup-tables
```

**No options or arguments.**

**Example:**
```bash
php artisan notices:sync-lookup-tables
```

**What it does:**

Seeds static Polaris lookup tables with reference data:
- **NotificationTypes** - Hold, Overdue, Renewal, etc.
- **DeliveryOptions** - Mail (1), Email (2), Voice (3), SMS (8)
- **Other lookup tables** - Static reference data from Polaris

The data is sourced from package documentation under `docs/polaris/lookup_tables/`.

**When to use:**
- After initial installation
- After running database migrations
- When lookup table data needs to be refreshed
- When Polaris adds new notification types or delivery options

**Important notes:**
- Safe to run multiple times (idempotent)
- Does not delete existing data
- Updates existing records by ID
- Uses `--force` flag to run in production

---

### `notices:cleanup-import-logs`

Clean up locally stored Polaris/Shoutbomb import/export files by date range.

**Signature:**
```bash
php artisan notices:cleanup-import-logs [options]
```

**Options:**
- `--date=YYYY-MM-DD` - Delete files for specific date
- `--from=YYYY-MM-DD` - Start date for range (inclusive)
- `--to=YYYY-MM-DD` - End date for range (inclusive)
- `--days=N` - Number of days back from today (inclusive)
- `--all` - Ignore date range and operate on all matching files
- `--dry-run` - List matching files and total size without deleting
- `--force` - Skip interactive confirmation (for non-interactive/web runs)

**Examples:**
```bash
# List files for a specific day without deleting
php artisan notices:cleanup-import-logs --date=2026-01-07 --dry-run

# Delete files for a specific day (with interactive confirmation)
php artisan notices:cleanup-import-logs --date=2026-01-07

# Delete files for a date range
php artisan notices:cleanup-import-logs --from=2026-01-01 --to=2026-01-07

# Delete last 7 days non-interactively (e.g. from a web button)
php artisan notices:cleanup-import-logs --days=7 --force --no-interaction

# Delete all cached import/export files
php artisan notices:cleanup-import-logs --all --force --no-interaction
```

**What it does:**

Operates on the directory configured by `notices.import.storage` (`NOTICES_IMPORT_STORAGE`) (defaults to `storage/app/notices-exports`) and removes cached Polaris/Shoutbomb export files, including:
- `holds_YYYY-MM-DD_*.txt`
- `renew_YYYY-MM-DD_*.txt`
- `overdue_YYYY-MM-DD_*.txt`
- `notification_log_YYYY-MM-DD_*.txt`
- `notification_history_YYYY-MM-DD_*.txt`
- `PhoneNotices_YYYY-MM-DD_*.csv`

The command:
1. Resolves a date range using the same precedence rules as other import commands
2. Locates matching files under the import storage directory
3. Shows a tabular summary with file name, size, and last modified timestamp
4. Logs both the planned operation and final outcome to `activity_log`
5. Deletes files only after confirmation (or immediately when `--force` is used)

**When to use:**
- Periodically cleaning up old import/export files to reclaim disk space
- After importing historical data for large date ranges
- As a maintenance task exposed via a web UI button (using `--force --no-interaction`)
- When troubleshooting import issues and you want to clear and re-fetch from FTP

**Important notes:**
- `--dry-run` is safe and never deletes anything
- If an invalid date range is provided, the command aborts without deleting files
- `--all` should typically be paired with `--force` to avoid accidental bulk deletion
- All runs (including dry-runs and no-op runs) are recorded in `activity_log` for auditing

---

## Date Options

All import commands support flexible date specifications with consistent behavior.

### Option Precedence

Options are processed in this order (highest to lowest priority):

1. **`--date=YYYY-MM-DD`** - Single date (overrides all others)
2. **`--from=YYYY-MM-DD --to=YYYY-MM-DD`** - Explicit date range
3. **`--days=N`** - Relative range from today
4. **Default** - Today (or yesterday for `notices:import-confirmations`)

### Date Option Examples

#### Single Date
```bash
# Process December 27, 2025
php artisan notices:import --date=2025-12-27
```

#### Date Range
```bash
# Process December 20-27, 2025 (inclusive)
php artisan notices:import --from=2025-12-20 --to=2025-12-27

# Only --from (defaults to to today)
php artisan notices:import --from=2025-12-20

# Only --to (processes just that one day)
php artisan notices:import --to=2025-12-27
```

#### Relative Range
```bash
# Last 7 days (inclusive)
php artisan notices:import --days=7

# Last 30 days
php artisan notices:import --days=30
```

#### Defaults
```bash
# Today (for import commands)
php artisan notices:import

# Yesterday (for confirmation detection)
php artisan notices:import-confirmations
```

### How Date Ranges Work

When you specify a date range:
1. Command calculates all dates between `--from` and `--to` (inclusive)
2. Runs the operation for each date sequentially
3. Logs activity for each date separately
4. Displays progress for each date

Example: `--from=2025-12-20 --to=2025-12-22` processes:
- 2025-12-20
- 2025-12-21
- 2025-12-22

### Date Format

All dates must be in `YYYY-MM-DD` format:
- ✅ `2025-12-27`
- ✅ `2025-01-05`
- ❌ `12/27/2025`
- ❌ `27-12-2025`
- ❌ `2025-12-27 10:30:00`

Invalid date formats will produce a warning and the command may use default dates.

---

## Exit Codes

All commands follow Laravel conventions:

- **0 (SUCCESS)** - Command completed successfully
- **1 (FAILURE)** - Command encountered an error

Check exit codes in scheduled tasks:
```bash
php artisan notices:import --date=2025-12-27
if [ $? -eq 0 ]; then
    echo "Import successful"
else
    echo "Import failed"
fi
```

---

## Activity Logging

All commands automatically log their activity to the `activity_logs` table:

- **Command invocation** - Records command name, date range, and options
- **Daily operations** - Records results for each date processed
- **Import counts** - Tracks number of records imported
- **Errors** - Logs any errors or warnings encountered

View activity logs through the web interface or query the database:
```sql
SELECT * FROM activity_logs
WHERE action LIKE 'import.%'
ORDER BY created_at DESC
LIMIT 20;
```

---

## Scheduled Tasks

Set up automated daily imports in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run complete pipeline daily at 2 AM
    $schedule->command('notices:import')
        ->dailyAt('02:00')
        ->emailOutputOnFailure('admin@yourlibrary.org');

    // Alternative: Run components separately
    $schedule->command('notices:import-ftp')
        ->dailyAt('01:00');

    $schedule->command('notices:import-email')
        ->dailyAt('01:30');

    $schedule->command('notices:import-confirmations')
        ->dailyAt('02:00');
}
```

---

## See Also

- [Common Workflows](workflows.md) - Step-by-step workflows for common scenarios
- [Web Interface Guide](user-guide.md) - Using the web interface to view and manage notifications
