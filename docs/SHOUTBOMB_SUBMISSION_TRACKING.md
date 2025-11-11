# Shoutbomb Submission Tracking

This document explains the two-tier tracking system for Shoutbomb notifications.

## System Overview

### Primary System: SQL-Generated Submissions
The **official** notification submission system uses SQL-generated files uploaded to Shoutbomb FTP:

- `holds_submitted_{yyyy-mm-dd_hh-mm-ss}.txt` - Hold notifications
- `overdue_submitted_{yyyy-mm-dd_hh-mm-ss}.txt` - Overdue notifications
- `renew_submitted_{yyyy-mm-dd_hh-mm-ss}.txt` - Renewal notifications
- `voice_patrons_submitted_{yyyy-mm-dd}.txt` - Voice delivery patron list
- `text_patrons_submitted_{yyyy-mm-dd}.txt` - Text delivery patron list

**Database Table**: `shoutbomb_submissions`

### Verification System: PhoneNotices.csv
Polaris native export that serves as **corroboration** to verify the SQL submissions were processed correctly:

- `PhoneNotices.csv` - Polaris export with full patron and item details

**Database Table**: `polaris_phone_notices`

## Import Commands

### Primary Submissions (Official System)
```bash
# Import SQL-generated submission files
php artisan notices:import-shoutbomb-submissions

# Import specific date range
php artisan notices:import-shoutbomb-submissions --date=2025-01-15

# Import last N days
php artisan notices:import-shoutbomb-submissions --days=7

# Import from local file for testing
php artisan notices:import-shoutbomb-submissions --file=/path/to/holds_submitted_2025-01-15_14-30-45.txt --type=holds
```

### Verification/Corroboration
```bash
# Import PhoneNotices.csv for verification
php artisan notices:import-polaris-phone-notices

# Import from local file
php artisan notices:import-polaris-phone-notices --file=/path/to/PhoneNotices.csv
```

## Database Schema

### shoutbomb_submissions (Primary/Official)
Tracks what was officially submitted to Shoutbomb via SQL-generated files:

```sql
- id
- notification_type (holds, overdue, renew)
- patron_barcode
- phone_number
- title
- item_id
- branch_id
- pickup_date
- expiration_date
- submitted_at (timestamp from filename)
- source_file (original filename)
- delivery_type (voice/text from patron lists)
- imported_at
- created_at, updated_at
```

### polaris_phone_notices (Verification)
Polaris export for corroboration with full details:

```sql
- id
- delivery_type (voice/text from CSV field 1)
- language
- patron_barcode
- first_name, last_name
- phone_number
- email
- library_code, library_name
- item_barcode
- notice_date
- title
- organization_code
- language_code
- patron_id
- item_record_id
- bib_record_id
- source_file
- imported_at
- created_at, updated_at
```

## File Formats

### SQL-Generated Files (Official System)

**Holds** (7 fields):
```
BTitle|CreationDate|SysHoldRequestID|PatronID|PickupOrganizationID|HoldTillDate|PBarcode
Museum Pass|2025-05-15|830874|11677|3|2025-05-19|23307013757366
```

**Overdue/Renew** (13 fields):
```
PatronID|ItemBarcode|Title|DueDate|ItemRecordID|Dummy1|Dummy2|Dummy3|Dummy4|Renewals|BibRecordID|RenewalLimit|PatronBarcode
```

**Patron Lists** (2 fields):
```
PhoneNumber|PatronBarcode
5551234567|11677
```

### PhoneNotices.csv (Verification)

CSV format with 22+ fields from Polaris native export:
- Field 1: Delivery type (V=Voice, T=Text)
- Field 2: Language
- Field 5: Patron barcode
- Field 7: First name
- Field 8: Last name
- Field 9: Phone number
- Field 10: Email
- Field 11: Library code
- Field 12: Library name
- Field 13: Item barcode
- Field 14: Date
- Field 15: Title
- Field 16: Organization code
- Field 17: Language code
- Field 20: Patron ID
- Field 21: Item Record ID
- Field 22: Bibliographic Record ID

## Verification Workflow

1. **Import official submissions** from SQL-generated files
2. **Import PhoneNotices.csv** for corroboration
3. **Compare** the two sources to verify accuracy

### Comparison Example
```php
use Dcplibrary\Notices\Services\PolarisPhoneNoticeImporter;
use Carbon\Carbon;

$importer = app(PolarisPhoneNoticeImporter::class);
$comparison = $importer->compareWithSubmissions(Carbon::parse('2025-01-15'));

// Results show:
// - Count from official SQL submissions
// - Count from PhoneNotices.csv corroboration
// - Any differences for investigation
```

## Query Examples

### Official Submissions
```php
use Dcplibrary\Notices\Models\ShoutbombSubmission;

// Get all hold notifications for a patron
$holds = ShoutbombSubmission::holds()
    ->forPatron('11677')
    ->recent(30)
    ->get();

// Get voice vs text breakdown
$voiceCount = ShoutbombSubmission::voice()->count();
$textCount = ShoutbombSubmission::text()->count();

// Get submissions by date range
$submissions = ShoutbombSubmission::dateRange(
    Carbon::parse('2025-01-01'),
    Carbon::parse('2025-01-31')
)->get();
```

### Verification Data
```php
use Dcplibrary\Notices\Models\PolarisPhoneNotice;

// Get all notices for a patron (from Polaris export)
$notices = PolarisPhoneNotice::forPatron('11677')
    ->recent(30)
    ->get();

// Compare by library
$byLibrary = PolarisPhoneNotice::forLibrary('MAIN')
    ->whereDate('notice_date', '2025-01-15')
    ->count();

// Voice vs text from verification
$voiceNotices = PolarisPhoneNotice::voice()->count();
$textNotices = PolarisPhoneNotice::text()->count();
```

## Statistics

### Official Submission Stats
```php
use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;

$importer = app(ShoutbombSubmissionImporter::class);
$stats = $importer->getStats(
    Carbon::parse('2025-01-01'),
    Carbon::parse('2025-01-31')
);

// Returns:
// - total: Total submissions
// - by_type: Breakdown by holds/overdue/renew
// - by_delivery: Breakdown by voice/text
// - unique_patrons: Unique patron count
```

### Verification Stats
```php
use Dcplibrary\Notices\Services\PolarisPhoneNoticeImporter;

$importer = app(PolarisPhoneNoticeImporter::class);
$stats = $importer->getStats(
    Carbon::parse('2025-01-01'),
    Carbon::parse('2025-01-31')
);

// Returns:
// - total: Total phone notices
// - by_delivery_type: Breakdown by voice/text
// - by_library: Breakdown by library
// - unique_patrons: Unique patron count
// - unique_phones: Unique phone numbers
```

## Workflow Integration

### Daily Import Automation
```bash
#!/bin/bash
# Daily import script

# Import official submissions from yesterday
php artisan notices:import-shoutbomb-submissions --days=1

# Import PhoneNotices.csv for verification
php artisan notices:import-polaris-phone-notices

# Import delivery reports (what Shoutbomb actually delivered)
php artisan notices:import-shoutbomb-reports --days=1
```

### Cron Schedule
```cron
# Import submissions daily at 2 AM
0 2 * * * cd /var/www && php artisan notices:import-shoutbomb-submissions --days=1

# Import PhoneNotices.csv for verification at 2:30 AM
30 2 * * * cd /var/www && php artisan notices:import-polaris-phone-notices

# Import delivery reports at 3 AM
0 3 * * * cd /var/www && php artisan notices:import-shoutbomb-reports --days=1
```

## Complete Data Flow

```
┌─────────────────────────────────────────┐
│   POLARIS ILS (Library System)         │
└─────────────────┬───────────────────────┘
                  │
         ┌────────┴────────┐
         │                 │
         ▼                 ▼
┌─────────────────┐  ┌──────────────────┐
│  SQL Scripts    │  │ PhoneNotices.csv │
│  (Official)     │  │ (Verification)   │
└────────┬────────┘  └────────┬─────────┘
         │                    │
         │  UPLOAD TO FTP     │
         │                    │
         ▼                    ▼
┌──────────────────────────────────────┐
│     Shoutbomb FTP Server             │
│  - holds_submitted_*.txt             │
│  - overdue_submitted_*.txt           │
│  - renew_submitted_*.txt             │
│  - *_patrons_submitted_*.txt         │
│  - PhoneNotices.csv                  │
└──────────┬───────────────────────────┘
           │
           │  IMPORT
           ▼
┌──────────────────────────────────────┐
│    Laravel Notifications Package     │
│  ┌────────────────────────────────┐  │
│  │  shoutbomb_submissions         │  │ ← Official
│  │  (Primary tracking)            │  │
│  └────────────────────────────────┘  │
│  ┌────────────────────────────────┐  │
│  │  polaris_phone_notices       │  │ ← Verification
│  │  (Corroboration)               │  │
│  └────────────────────────────────┘  │
└──────────────────────────────────────┘
           │
           │  VERIFICATION/REPORTING
           ▼
     ┌──────────┐
     │Dashboard │
     └──────────┘
```

## Troubleshooting

### Discrepancies Between Systems

If you find differences between `shoutbomb_submissions` and `polaris_phone_notices`:

1. **Check import dates** - Ensure you're comparing the same date range
2. **Verify patron lists** - Voice/text assignments may differ
3. **Review logs** - Check for parsing errors in either system
4. **File timestamps** - SQL files have timestamp in filename, CSV may not

### Missing Data

**Official submissions not importing:**
- Check FTP connection
- Verify file naming pattern matches expected format
- Review parser for field count validation

**PhoneNotices.csv not found:**
- Confirm Polaris is generating the export
- Check FTP directory permissions
- Verify CSV has at least 22 fields

## API Integration

Both tracking systems are available via the notifications API:

```
GET /api/notices/submissions?date=2025-01-15
GET /api/notices/phone-notices?date=2025-01-15
GET /api/notices/compare?date=2025-01-15
```

See API documentation for full details.
