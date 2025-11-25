# Automated Import Schedule

This document explains the automated import schedule for the notices dashboard, which synchronizes data from Polaris and Shoutbomb throughout the day.

## Schedule Overview

The import schedule is designed to align with Polaris and Shoutbomb export times, ensuring data is imported shortly after it becomes available.

### Daily Timeline

```
┌─────────────────────────────────────────────────────────────────┐
│ OVERNIGHT PROCESSING (1:30 AM - 5:00 AM)                        │
└─────────────────────────────────────────────────────────────────┘
  1:30 AM  Conflict Resolution Script 1 (Polaris)
  1:45 AM  Conflict Resolution Script 2 (Polaris)
  4:00 AM  Voice Patrons Export (Polaris → FTP)
  5:00 AM  Text Patrons Export (Polaris → FTP)

┌─────────────────────────────────────────────────────────────────┐
│ MORNING IMPORTS (5:30 AM - 9:30 AM)                             │
└─────────────────────────────────────────────────────────────────┘
  5:30 AM  ✅ Import patron lists (voice + text)
           Command: notices:import-ftp-files --from=today --to=today

  6:01 AM  Daily Invalid Phone Report (Shoutbomb → Email)

  6:30 AM  ✅ Import invalid phone reports
           Command: notices:import-email-reports --type=invalid --mark-read

  8:00 AM  Hold Notifications Export #1 (Polaris → FTP)
  8:03 AM  Renewal Reminders Export (Polaris → FTP)
  8:04 AM  Overdue Notices Export (Polaris → FTP)
  8:04 AM  PhoneNotices.csv Export (Polaris → FTP)

  8:30 AM  ✅ Import morning notifications + PhoneNotices
           Command: notices:import-polaris --days=1

  9:00 AM  Hold Notifications Export #2 (Polaris → FTP)

  9:30 AM  ✅ Import second morning holds
           Command: notices:import-ftp-files --from=today --to=today --type=holds

┌─────────────────────────────────────────────────────────────────┐
│ AFTERNOON IMPORTS (1:00 PM - 5:30 PM)                           │
└─────────────────────────────────────────────────────────────────┘
  1:00 PM  Hold Notifications Export #3 (Polaris → FTP)

  1:30 PM  ✅ Import afternoon holds
           Command: notices:import-ftp-files --from=today --to=today --type=holds

  4:10 PM  Daily Voice Failure Report (Shoutbomb → Email)

  4:30 PM  ✅ Import voice failure reports
           Command: notices:import-email-reports --type=voice-failures --mark-read

  5:00 PM  Hold Notifications Export #4 (Polaris → FTP)

  5:30 PM  ✅ Import evening holds
           Command: notices:import-ftp-files --from=today --to=today --type=holds

┌─────────────────────────────────────────────────────────────────┐
│ END OF DAY PROCESSING (10:00 PM)                                │
└─────────────────────────────────────────────────────────────────┘
  10:00 PM ✅ Aggregate daily data
           Command: notices:aggregate --yesterday
```

### Monthly Tasks

```
1st of month, 1:14 PM  Monthly Statistics Report (Shoutbomb → Email)

2nd of month, 2:00 PM  ✅ Import monthly statistics
                       Command: notices:import-email-reports --type=monthly-stats --mark-read
```

## Import Details

### 1. Patron Lists (5:30 AM)

**Purpose:** Import patron delivery preferences (voice vs. text)

**Source Files:**
- `voice_patrons.txt` - Patrons registered for voice notifications
- `text_patrons.txt` - Patrons registered for text notifications

**Export Timing:**
- Voice: 4:00 AM
- Text: 5:00 AM

**Import Buffer:** 30 minutes for FTP upload

**Configuration:**
```php
'scheduler.import_patron_lists_enabled' => true
```

### 2. Invalid Phone Reports (6:30 AM)

**Purpose:** Import opt-out and invalid phone number reports from Shoutbomb

**Source:** Email from Shoutbomb with subject line "Invalid patron phone number..."

**Export Timing:** ~6:01 AM

**Import Buffer:** 30 minutes for email delivery

**Configuration:**
```php
'scheduler.import_invalid_reports_enabled' => true
```

### 3. Morning Notifications (8:30 AM)

**Purpose:** Import morning hold, renewal, and overdue notifications, plus Polaris PhoneNotices

**Source Files:**
- `holds.txt` - First morning hold export
- `renew.txt` - Renewal reminders (3-4 days before due)
- `overdue.txt` - All currently overdue items
- `PhoneNotices.csv` - Native Polaris phone notification export

**Export Timing:**
- 8:00 AM - Holds #1
- 8:03 AM - Renewals
- 8:04 AM - Overdues
- 8:04-8:05 AM - PhoneNotices

**Import Buffer:** 25-30 minutes for export completion and FTP upload

**Configuration:**
```php
'scheduler.import_morning_notifications_enabled' => true
```

### 4. Second Morning Holds (9:30 AM)

**Purpose:** Import holds processed overnight

**Source Files:**
- `holds.txt` - Second morning hold export

**Export Timing:** 9:00 AM

**Import Buffer:** 30 minutes

**Configuration:**
```php
'scheduler.import_morning_holds_enabled' => true
```

### 5. Afternoon Holds (1:30 PM)

**Purpose:** Import holds from midday processing

**Source Files:**
- `holds.txt` - Afternoon hold export

**Export Timing:** 1:00 PM

**Import Buffer:** 30 minutes

**Rationale:** Holds are exported 4x daily because items become available throughout the day as they're checked in and processed.

**Configuration:**
```php
'scheduler.import_afternoon_holds_enabled' => true
```

### 6. Voice Failure Reports (4:30 PM)

**Purpose:** Import voice call failure reports from Shoutbomb

**Source:** Email from Shoutbomb with voice delivery failures

**Export Timing:** ~4:10 PM

**Import Buffer:** 20 minutes for email delivery

**Configuration:**
```php
'scheduler.import_voice_failures_enabled' => true
```

### 7. Evening Holds (5:30 PM)

**Purpose:** Import final hold export of the day

**Source Files:**
- `holds.txt` - Evening hold export

**Export Timing:** 5:00 PM

**Import Buffer:** 30 minutes

**Configuration:**
```php
'scheduler.import_evening_holds_enabled' => true
```

### 8. Daily Aggregation (10:00 PM)

**Purpose:** Aggregate all imported data for dashboard and reporting

**Process:**
- Consolidates data from all imports
- Generates summary statistics
- Prepares data for dashboard display
- Runs after all daily imports are complete

**Configuration:**
```php
'scheduler.aggregation_enabled' => true
```

### 9. Monthly Statistics (2nd of month, 2:00 PM)

**Purpose:** Import comprehensive monthly report from Shoutbomb

**Source:** Email from Shoutbomb with 15+ page monthly report

**Export Timing:** 1st of month, ~1:14 PM

**Import Buffer:** Next day import to ensure email has arrived

**Report Contents:**
- Notification counts by type and branch
- Registered patron counts
- Daily call volumes
- Opted-out patrons
- Invalid phone numbers
- Keyword usage frequency
- New registrations/cancellations

**Configuration:**
```php
'scheduler.import_monthly_stats_enabled' => true
```

## Configuration

### Enabling/Disabling Tasks

All scheduled tasks can be individually enabled or disabled via the settings table:

```php
// Enable all tasks (recommended for production)
'scheduler.import_patron_lists_enabled' => true,
'scheduler.import_invalid_reports_enabled' => true,
'scheduler.import_morning_notifications_enabled' => true,
'scheduler.import_morning_holds_enabled' => true,
'scheduler.import_afternoon_holds_enabled' => true,
'scheduler.import_voice_failures_enabled' => true,
'scheduler.import_evening_holds_enabled' => true,
'scheduler.aggregation_enabled' => true,
'scheduler.import_monthly_stats_enabled' => true,

// Legacy unified import (disabled by default)
'scheduler.import_enabled' => false,
```

### Testing Individual Tasks

You can manually run any scheduled task:

```bash
# Run patron list import
php artisan notices:import-ftp-files --from=today --to=today

# Run morning notifications import
php artisan notices:import-polaris --days=1

# Run email reports import
php artisan notices:import-email-reports --type=invalid --mark-read

# Run aggregation
php artisan notices:aggregate --yesterday
```

### Viewing Scheduled Tasks

```bash
# List all scheduled tasks
php artisan schedule:list

# Run scheduler (normally done by cron)
php artisan schedule:run
```

## Why This Schedule?

### Multiple Hold Imports

Holds are imported **4 times daily** (8:30 AM, 9:30 AM, 1:30 PM, 5:30 PM) because:

- Items become available throughout the day as they're checked in
- Patrons expect timely notifications when holds are ready
- Multiple exports ensure patrons are notified quickly
- Reduces wait time between item availability and notification

### Buffer Times

Each import has a **20-30 minute buffer** after the export to ensure:

- Files have completed uploading to FTP
- Emails have been delivered
- Export processes have fully completed
- Reduces likelihood of importing incomplete data

### End of Day Aggregation

Aggregation runs at **10:00 PM** to:

- Allow all daily imports to complete
- Generate summary statistics for next day's dashboard
- Prepare data for reporting
- Run during off-peak hours

## Troubleshooting

### Import Not Running

1. **Check if task is enabled:**
   ```sql
   SELECT * FROM settings WHERE setting_key LIKE 'scheduler%enabled';
   ```

2. **Check Laravel scheduler:**
   ```bash
   php artisan schedule:list
   ```

3. **Check cron is running:**
   ```bash
   crontab -l
   # Should have: * * * * * php /path/to/artisan schedule:run
   ```

### Import Failing

1. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Run manually to see errors:**
   ```bash
   php artisan notices:import-polaris --days=1 -vvv
   ```

3. **Check FTP connectivity:**
   - Verify LOCAL_FTP_HOST in .env
   - Test FTP connection
   - Check file permissions

### Data Missing

1. **Check import timing:**
   - May need to adjust buffer times
   - Verify Polaris export times haven't changed

2. **Check data source:**
   - Verify exports are running on Polaris/Shoutbomb side
   - Check FTP server has files

3. **Run import manually:**
   - Force import with wider date range
   - Check for errors in output

## Related Documentation

- **[shoutbomb_process_explanation.md](shoutbomb/shoutbomb_process_explanation.md)** - Detailed Shoutbomb process timeline
- **[POLARIS_PHONE_NOTICES.md](shoutbomb/POLARIS_PHONE_NOTICES.md)** - Polaris PhoneNotices export specification
- **[SHOUTBOMB_REPORTS_INCOMING.md](shoutbomb/SHOUTBOMB_REPORTS_INCOMING.md)** - Shoutbomb report formats and timing

---

**Last Updated:** November 25, 2025
**Schedule Version:** 1.0
**Author:** System Integration Team
