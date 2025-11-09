# Shoutbomb Submission Tracking

This document explains the Shoutbomb submission tracking feature, which tracks **what notifications were sent to Shoutbomb** (separate from delivery confirmations).

## Overview

Your Shoutbomb FTP contains **submission verification files** that show what notifications Polaris submitted to Shoutbomb for delivery. This is different from delivery reports (which track whether messages were actually delivered).

## What Gets Tracked

### Submission Files
Daily files showing what was submitted to Shoutbomb:
- `holds_submitted_{yyyy-mm-dd_hh-mm-ss}.txt`
- `overdue_submitted_{yyyy-mm-dd_hh-mm-ss}.txt`
- `renew_submitted_{yyyy-mm-dd_hh-mm-ss}.txt`

### Patron Lists
Daily files showing patron delivery preferences:
- `voice_patrons_submitted_{yyyy-mm-dd_hh-mm-ss}.txt` - Patrons who get voice calls
- `text_patrons_submitted_{yyyy-mm-dd_hh-mm-ss}.txt` - Patrons who get text messages

## File Formats

### Holds Submissions
Format (7 fields):
```
Title|CreationDate|HoldRequestID|PatronID|BranchID|HoldTillDate|PhoneNumber
```

Example:
```
Museum Pass|2025-05-15|830874|11677|3|2025-05-19|23307013757366
```

Based on `holds.sql`:
1. BTitle - Item title
2. CreationDate - When hold was created
3. SysHoldRequestID - Hold request ID
4. PatronID - Patron's internal ID
5. PickupOrganizationID - Branch ID
6. HoldTillDate - Hold expiration date
7. PBarcode - Phone number (appears to be substituted by Polaris)

### Overdue/Renew Submissions
Format (13 fields):
```
PatronID|ItemBarcode|Title|DueDate|ItemRecordID|Dummy1|Dummy2|Dummy3|Dummy4|Renewals|BibRecordID|RenewalLimit|PhoneNumber
```

Example:
```
598|33307006781769|I'm an immigrant too!|2025-09-08|740711|||||2|712138|2|23307014592648
```

Based on `overdue.sql` and `renew.sql`:
1. PatronID - Patron's internal ID
2. ItemBarcode - Item barcode
3. Title - Item title
4. DueDate - Due date
5. ItemRecordID - Item record ID
6-9. Dummy fields (empty)
10. Renewals - Number of renewals
11. BibliographicRecordID - Bib record ID
12. RenewalLimit - Renewal limit
13. PatronBarcode - Phone number (appears to be substituted by Polaris)

### Patron Lists
Format (2 fields):
```
PhoneNumber|PatronBarcode
```

Example:
```
23307014592648|123456
```

Based on `voice_patrons.sql` and `text_patrons.sql`:
1. PhoneVoice1 - Phone number (dashes removed)
2. Barcode - Patron barcode

## Database Schema

### shoutbomb_submissions Table

Stores all submission records:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| notification_type | enum | 'holds', 'overdue', 'renew' |
| patron_barcode | string | Patron barcode |
| phone_number | string | Phone number |
| title | string | Item title (nullable) |
| item_id | string | Item/hold ID (nullable) |
| branch_id | integer | Branch ID (nullable) |
| pickup_date | date | Pickup date (holds only) |
| expiration_date | date | Expiration/due date (nullable) |
| submitted_at | datetime | When submitted to Shoutbomb |
| source_file | string | Original filename |
| delivery_type | enum | 'voice' or 'text' |
| imported_at | timestamp | When imported |

### Indexes

- `notification_type` - Fast filtering by type
- `patron_barcode` - Lookup by patron
- `phone_number` - Lookup by phone
- `submitted_at` - Date range queries
- `delivery_type` - Filter voice vs text
- Composite indexes for common queries

## Usage

### Import from FTP

Import yesterday's submissions:
```bash
php artisan notifications:import-shoutbomb-submissions
```

Import specific date:
```bash
php artisan notifications:import-shoutbomb-submissions --date=2025-05-15
```

Import last 7 days:
```bash
php artisan notifications:import-shoutbomb-submissions --days=7
```

### Import from Local File (Testing)

```bash
php artisan notifications:import-shoutbomb-submissions \
  --file=/path/to/holds_submitted_2025-05-15_14-30-00.txt \
  --type=holds
```

Valid types: `holds`, `overdue`, `renew`

### Querying Data

```php
use Dcplibrary\Notifications\Models\ShoutbombSubmission;

// Get all holds from last 7 days
$holds = ShoutbombSubmission::holds()
    ->recent(7)
    ->get();

// Get voice notifications for a patron
$voice = ShoutbombSubmission::forPatron('123456')
    ->voice()
    ->get();

// Get overdues by date range
$overdues = ShoutbombSubmission::overdues()
    ->dateRange($startDate, $endDate)
    ->get();

// Get text message submissions
$texts = ShoutbombSubmission::text()
    ->orderBy('submitted_at', 'desc')
    ->get();
```

### Available Scopes

- `holds()` - Filter to hold notifications
- `overdues()` - Filter to overdue notifications
- `renewals()` - Filter to renewal notifications
- `voice()` - Filter to voice delivery
- `text()` - Filter to text delivery
- `recent($days)` - Get recent submissions
- `dateRange($start, $end)` - Filter by date range
- `forPatron($barcode)` - Filter by patron
- `ofType($type)` - Filter by notification type
- `byDeliveryType($type)` - Filter by delivery type

## How It Works

### Import Process

1. **Connect to FTP** - Connects to your Shoutbomb FTP server

2. **Download Patron Lists**
   - Downloads `voice_patrons_submitted_{date}.txt`
   - Downloads `text_patrons_submitted_{date}.txt`
   - Parses into lookup arrays (PatronBarcode => PhoneNumber)

3. **Download Submission Files**
   - Downloads `holds_submitted_{date}*.txt`
   - Downloads `overdue_submitted_{date}*.txt`
   - Downloads `renew_submitted_{date}*.txt`

4. **Parse Submissions**
   - Parses pipe-delimited format
   - Extracts timestamp from filename
   - Matches patron with delivery type (voice vs text)

5. **Store in Database**
   - Inserts in batches of 500
   - Tracks source file and import time

### Delivery Type Matching

The importer determines if a notification was voice or text by checking the patron lists:
- If patron barcode is in `voice_patrons` → delivery_type = 'voice'
- If patron barcode is in `text_patrons` → delivery_type = 'text'
- If in neither list → delivery_type = null

## Configuration

Update your `.env` file:

```env
# Disable the old Shoutbomb FTP reports (you don't have these)
SHOUTBOMB_ENABLED=false

# FTP credentials for submission files
SHOUTBOMB_FTP_HOST=ftp.example.com
SHOUTBOMB_FTP_PORT=21
SHOUTBOMB_FTP_USERNAME=your-username
SHOUTBOMB_FTP_PASSWORD=your-password
SHOUTBOMB_FTP_PASSIVE=true
```

## Scheduled Imports

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Import submissions daily at 3 AM
    $schedule->command('notifications:import-shoutbomb-submissions --days=1')
        ->dailyAt('03:00')
        ->withoutOverlapping();
}
```

## Differences from Delivery Reports

| Feature | Submissions | Delivery Reports |
|---------|-------------|------------------|
| **What it tracks** | What was sent to Shoutbomb | What was delivered to patrons |
| **Source** | FTP submission files | Email reports (opt-outs, failures) |
| **Status info** | No status (just submitted) | Delivered, Failed, Invalid, etc. |
| **Use case** | Verify what Polaris sent | Track actual delivery success |
| **Frequency** | Daily | Varies (daily, weekly, monthly) |

## Use Cases

### 1. Verify Polaris Submissions
Check that Polaris is actually sending notifications to Shoutbomb:
```php
$submittedToday = ShoutbombSubmission::where('submitted_at', '>=', today())->count();
```

### 2. Compare Voice vs Text
See the split between voice and text notifications:
```php
$stats = ShoutbombSubmission::recent(30)
    ->groupBy('delivery_type')
    ->selectRaw('delivery_type, count(*) as count')
    ->get();
```

### 3. Patron Notification History
See all notifications sent for a patron:
```php
$history = ShoutbombSubmission::forPatron('123456')
    ->orderBy('submitted_at', 'desc')
    ->get();
```

### 4. Branch Activity
Track which branches are sending the most notifications:
```php
$byBranch = ShoutbombSubmission::holds()
    ->groupBy('branch_id')
    ->selectRaw('branch_id, count(*) as count')
    ->orderBy('count', 'desc')
    ->get();
```

## Troubleshooting

### No files found on FTP

Check your FTP path configuration and credentials:
```bash
php artisan notifications:test-connections --shoutbomb -vvv
```

### Wrong field mappings

The parsers are based on the Polaris SQL schemas. If your files have different formats, you may need to adjust the parsers in:
- `src/Services/ShoutbombSubmissionParser.php`

### Patron delivery type not set

If `delivery_type` is null, it means the patron wasn't found in either the voice or text patron lists. This could mean:
- Patron lists weren't downloaded
- Patron opted out between submission and list generation
- PatronID vs Barcode mismatch

### Duplicate imports

The system doesn't currently prevent duplicate imports. To avoid duplicates, only import each date once or add unique constraints.

## Future Enhancements

Potential improvements:
- [ ] Add duplicate detection (check if file already imported)
- [ ] Track import history (which files were imported when)
- [ ] Add dashboard widgets for submission stats
- [ ] Correlate submissions with actual deliveries
- [ ] Alert if expected submissions are missing

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Run with verbose output: `php artisan notifications:import-shoutbomb-submissions -vvv`
3. Verify FTP connection: `php artisan notifications:test-connections --shoutbomb`

## Related Documentation

- [Main README](../README.md)
- [Deployment Checklist](DEPLOYMENT_CHECKLIST.md)
- [Docker Setup](DOCKER_SETUP.md)
