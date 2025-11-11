# Complete Polaris Reference Data Update

**Date:** 2025-11-10
**Branch:** `claude/setup-scheduled-tasks-011CUzRwzv34VdBZVC4HPhWE`
**Status:** ✅ Complete - Ready for Testing

## Summary

This update resolves the "Unknown" values appearing throughout the notification dashboards by adding complete Polaris reference data and fixing critical configuration path bugs.

## Problems Identified

### 1. Incomplete Reference Data (config/notices.php)
- **Notification Types**: Only 7 of 22 types were defined
- **Delivery Options**: Only 4 of 9 options were defined
- **Notification Statuses**: Only 3 of 16 statuses were defined

This caused any notifications using the missing IDs to display as "Unknown" in:
- Dashboard overview page
- Analytics page
- Notifications list page
- Daily summaries

### 2. Critical Config Path Bug
Two models were using the wrong configuration namespace:
- `NotificationLog.php` was calling `config('notifications.*')` instead of `config('notices.*')`
- `DailyNotificationSummary.php` had the same issue

**Impact**: Even if IDs were in the config, they would NEVER be found due to this bug, causing ALL lookups to return "Unknown".

## Changes Made

### 1. Updated config/notices.php

#### Notification Types (7 → 22 types)
```php
'notification_types' => [
    0 => 'Combined',
    1 => '1st Overdue',
    2 => 'Hold Ready',
    3 => 'Hold Cancel',
    4 => 'Recall',
    5 => 'All',
    6 => 'Route',
    7 => 'Almost Overdue',
    8 => 'Fine Notice',
    9 => 'Inactive Reminder',
    10 => 'Expiration Reminder',
    11 => 'Bill',
    12 => '2nd Overdue',
    13 => '3rd Overdue',
    14 => 'Serial Claim',
    15 => 'Polaris Fusion',
    16 => 'Course Reserves',
    17 => 'Borrow-By-Mail Failure',
    18 => '2nd Hold',
    19 => 'Missing Part',
    20 => 'Manual Bill',
    21 => '2nd Fine Notice',
],
```

#### Delivery Options (4 → 9 options)
```php
'delivery_options' => [
    1 => 'Mail',
    2 => 'Email',
    3 => 'Phone 1 (Voice)',
    4 => 'Phone 2 (Voice)',
    5 => 'Phone 3 (Voice)',
    6 => 'FAX',
    7 => 'EDI',
    8 => 'SMS',
    9 => 'Mobile App',
],
```

#### Notification Statuses (3 → 16 statuses)
```php
'notification_statuses' => [
    1 => 'Call completed - Voice',
    2 => 'Call completed - Answering machine',
    3 => 'Call not completed - Hang up',
    4 => 'Call not completed - Busy',
    5 => 'Call not completed - No answer',
    6 => 'Call not completed - No ring',
    7 => 'Call failed - No dial tone',
    8 => 'Call failed - Intercept tones heard',
    9 => 'Call failed - Probable bad phone number',
    10 => 'Call failed - Maximum retries exceeded',
    11 => 'Call failed - Undetermined error',
    12 => 'Email Completed',
    13 => 'Email Failed - Invalid address',
    14 => 'Email Failed',
    15 => 'Mail Printed',
    16 => 'Sent',
],
```

### 2. Fixed Model Config Path Bugs

**Files Modified:**
- `src/Models/NotificationLog.php` (lines 71, 79, 87)
- `src/Models/DailyNotificationSummary.php` (lines 69, 77)

**Change:**
```php
// Before (WRONG - would never find values)
config("notifications.notification_types.{$id}", 'Unknown')

// After (CORRECT)
config("notices.notification_types.{$id}", 'Unknown')
```

### 3. Created Documentation

**File:** `docs/sql/README.md`

Documents:
- What SQL files should be uploaded for validation
- Sample queries for extracting reference data from Polaris
- Current configuration status
- Validation commands to run

## Testing Instructions

### Step 1: Verify Configuration Changes

```bash
# Check the updated config
php artisan tinker
>>> config('notices.notification_types');
>>> config('notices.delivery_options');
>>> config('notices.notification_statuses');
```

You should see all 22 types, 9 delivery options, and 16 statuses.

### Step 2: Check Existing Data

Run the diagnostic command to see if any Unknown values remain:

```bash
php artisan notices:diagnose-data
```

**Expected Result:** Should show far fewer (or zero) Unknown values compared to before.

### Step 3: Verify Dashboard Display

Visit these pages and verify that "Unknown" no longer appears:

1. **Dashboard Overview** (`/notices`)
   - Check notification type labels
   - Check delivery method labels

2. **Analytics Page** (`/notices/analytics`)
   - Delivery Method Distribution chart should show proper labels
   - Notification Type Distribution chart should show proper labels

3. **Notifications List** (`/notices/notifications`)
   - All columns should show proper names instead of "Unknown"
   - Use filters to test different types and delivery methods

### Step 4: Sync Voice/SMS Data (IMPORTANT)

To include Voice/SMS notifications from Shoutbomb in all dashboards:

```bash
# Dry run to see what would be synced
php artisan notices:sync-shoutbomb-to-logs --days=7 --dry-run

# Actually sync the data
php artisan notices:sync-shoutbomb-to-logs --days=7

# Re-aggregate to update statistics
php artisan notices:aggregate
```

After this, Voice and SMS notifications should appear in:
- Dashboard overview
- Analytics charts
- Notifications list

### Step 5: Inspect Delivery Methods

```bash
php artisan notices:inspect-delivery-methods
```

This will show the breakdown of Voice and SMS in both Polaris and Shoutbomb data sources.

## Data Source Architecture

Understanding how data flows is crucial:

### notification_logs (Primary Display Table)
- **Source:** Polaris ILS + Synced Shoutbomb data
- **Used By:** Dashboard, Analytics, Notifications list
- **Contains:** Email, Mail, and Voice/SMS from Polaris
- **After Sync:** Also contains Voice/SMS from Shoutbomb PhoneNotices.csv

### shoutbomb_submissions
- **Source:** Holds/Overdues/Renewals submission files
- **Used By:** Shoutbomb statistics page
- **Contains:** What was SENT TO Shoutbomb for processing

### polaris_phone_notices
- **Source:** PhoneNotices.csv from Shoutbomb
- **Used By:** Sync command
- **Contains:** What was DELIVERED BY Shoutbomb (Voice/SMS)
- **Markers:** 'v' = voice, 't' = text

### shoutbomb_deliveries
- **Source:** Delivery report files from Shoutbomb
- **Used By:** Verification system
- **Contains:** Detailed delivery results

## Voice/SMS Integration

The key to making Voice/SMS visible everywhere is the sync command:

```bash
php artisan notices:sync-shoutbomb-to-logs
```

**What it does:**
1. Reads PhoneNotices.csv data from `polaris_phone_notices` table
2. Converts each record to `notification_logs` format
3. Maps delivery type: 'v' → ID 3 (Phone 1 Voice), 't' → ID 8 (SMS)
4. Maps notification type: Defaults to ID 2 (Hold Ready)
5. Sets status: ID 12 (Success) for all
6. Skips duplicates to allow re-running safely

**Note:** PhoneNotices.csv doesn't include notification type, so it defaults to "Hold Ready" (ID 2). This could be enhanced by matching against `shoutbomb_submissions` in the future.

## Scheduled Tasks

The following tasks are now configured and ready to run:

| Task | Schedule | Command | Config Setting |
|------|----------|---------|----------------|
| Import Polaris notifications | Hourly | `notices:import --days=1` | `scheduler.import_polaris_enabled` |
| Import Shoutbomb reports | Daily 9:00 AM | `notices:import-shoutbomb` | `scheduler.import_shoutbomb_enabled` |
| Import Shoutbomb submissions | Daily 5:30 AM | `notices:import-shoutbomb-submissions` | `scheduler.import_submissions_enabled` |
| Import email reports | Daily 9:30 AM | `notices:import-email-reports --mark-read` | `scheduler.import_email_enabled` |
| Aggregate data | Daily 12:30 AM | `notices:aggregate` | `scheduler.aggregate_enabled` |

**To activate:** Run the seeder and set up Laravel's scheduler in cron:

```bash
# Seed scheduler settings
php artisan db:seed --class=NoticesSettingsSeeder

# Add to crontab
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Reference Data Source

All reference data was extracted from:
- `docs/archive/Polaris_Complete_Notification_System_Guide.md`
- Official Polaris ILS database documentation

## Remaining Tasks

1. **Upload Polaris SQL files** (optional but recommended)
   - Upload to `docs/sql/` directory
   - See `docs/sql/README.md` for recommended files
   - Will help validate that config matches production data

2. **Test Voice/SMS integration**
   - Run `notices:sync-shoutbomb-to-logs`
   - Verify Voice/SMS appears in Analytics charts
   - Check dashboard shows correct totals

3. **Monitor scheduled tasks**
   - Ensure cron is set up
   - Check logs for successful imports
   - Verify data updates daily

## Expected Outcomes

After deploying these changes:

✅ No more "Unknown" notification types
✅ No more "Unknown" delivery options
✅ No more "Unknown" statuses
✅ Voice and SMS notifications visible in all dashboards
✅ Analytics charts show accurate delivery method distribution
✅ Notifications list displays proper labels
✅ Daily summaries have correct type/delivery names
✅ Scheduled tasks run automatically

## Rollback Plan

If issues occur, you can rollback:

```bash
git checkout claude/setup-scheduled-tasks-011CUzRwzv34VdBZVC4HPhWE~2
```

However, the changes are purely additive (adding more config entries and fixing bugs), so rollback should not be necessary.

## Questions or Issues?

If you encounter any problems:

1. Run diagnostic command: `php artisan notices:diagnose-data`
2. Check delivery methods: `php artisan notices:inspect-delivery-methods`
3. Review logs for import errors
4. Verify config is published: `php artisan config:clear`

---

**Next Step:** Merge this branch and deploy to production to eliminate all "Unknown" values and integrate Voice/SMS notifications into all dashboards.
