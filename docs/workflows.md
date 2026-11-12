# Common Workflows

Step-by-step workflows for common scenarios when using the Notices package.

## Table of Contents

- [Daily Operations](#daily-operations)
- [VPN/FTP Workflows](#vpnftp-workflows)
- [Troubleshooting Workflows](#troubleshooting-workflows)
- [Issue Resolution Workflows](#issue-resolution-workflows)
- [Maintenance Workflows](#maintenance-workflows)
  - [Cleanup Import/Export Files](#cleanup-importexport-files)

---

## Daily Operations

### Automated Daily Pipeline

**Scenario:** Set up automated daily notification imports and processing.

**Setup (one time):**

1. **Configure scheduled task** in `app/Console/Kernel.php`:
   ```php
   protected function schedule(Schedule $schedule)
   {
       // Run complete pipeline daily at 2 AM
       $schedule->command('notices:import')
           ->dailyAt('02:00')
           ->emailOutputOnFailure('admin@yourlibrary.org');
   }
   ```

2. **Ensure cron is running:**
   ```bash
   # Add to server crontab
   * * * * * cd /path/to/laravel && php artisan schedule:run >> /dev/null 2>&1
   ```

3. **Verify configuration:**
   - FTP credentials in `.env`
   - Microsoft Graph API credentials in `.env`
   - Import paths configured

**Daily workflow (automated):**
- 2:00 AM - Import runs automatically
- Email sent to admin if import fails
- Check dashboard in morning for any new issues

**Manual verification:**
```bash
# Test the complete pipeline manually
php artisan notices:import --date=yesterday
```

---

### Manual Daily Import

**Scenario:** Run daily import manually (e.g., weekends, holidays, or when automation is disabled).

**Steps:**

1. **Run complete pipeline:**
   ```bash
   php artisan notices:import
   ```

2. **Monitor output:**
   - Watch for any errors or warnings
   - Note number of records imported
   - Verify completion messages

3. **Check web interface:**
   - Navigate to `/notices/dashboard`
   - Review today's activity
   - Check for new delivery issues

4. **Review issues:**
   - Navigate to `/notices/issues`
   - Review any new issues detected today
   - Resolve or assign issues as needed

**Expected timeline:**
- Import: 2-5 minutes (depending on volume)
- Review: 5-10 minutes

---

### Daily Issue Review

**Scenario:** Review and resolve new delivery issues each day.

**Steps:**

1. **Open Delivery Issues page:**
   - Navigate to `/notices/issues`
   - Default view shows unresolved issues only

2. **Filter to today's issues:**
   - Set date range to "Today"
   - Review issue types and counts

3. **Review failed contacts:**
   - Filter to "Failed Contacts" issue type
   - Click each issue to view details
   - Note patron barcode and failed contact info

4. **Update Polaris records:**
   - Open Polaris patron records
   - Verify and update contact information
   - Document changes in patron notes

5. **Resolve issues in Notices:**
   - Return to Notices interface
   - Click each resolved issue
   - Click "Resolve Issue"
   - Add resolution notes (e.g., "Updated email in Polaris")

6. **Review LX Starter failures:**
   - Filter to "Not delivered to patron"
   - Check if notifications have since delivered
   - Auto-resolved issues will already be gone
   - Investigate remaining failures

7. **Document patterns:**
   - Note any recurring problems
   - Identify systemic issues
   - Schedule follow-up if needed

**Time estimate:** 15-30 minutes daily (depending on issue volume)

---

## VPN/FTP Workflows

### VPN Import Workflow

**Scenario:** You need to connect to VPN to access FTP, but lose internet access while on VPN.

**Steps:**

1. **Connect to VPN:**
   - Connect to library VPN
   - Verify FTP access

2. **Import FTP data only:**
   ```bash
   php artisan notices:import-ftp --date=2025-12-27
   ```

   **What happens:**
   - Connects to Polaris FTP server
   - Downloads holds, renewal, overdue files
   - Imports raw data to database
   - Caches files locally

3. **Disconnect from VPN:**
   - Disconnect from VPN
   - Reconnect to internet

4. **Complete the pipeline:**
   ```bash
   php artisan notices:import --date=2025-12-27
   ```

   **What happens:**
   - Uses cached FTP files (doesn't re-download)
   - Imports email failure reports (requires internet)
   - Deduplicates and reconciles data
   - Detects delivery issues

5. **Verify completion:**
   - Check dashboard for today's activity
   - Review any new issues

**Important notes:**
- Files are cached after FTP download
- `notices:import` will NOT error when FTP is inaccessible
- Safe to run `notices:import` multiple times

---

### Catch-Up After VPN Downtime

**Scenario:** VPN was unavailable for several days and you need to import missed data.

**Steps:**

1. **Connect to VPN:**
   - Ensure VPN connection is stable

2. **Import multiple days of FTP data:**
   ```bash
   # Import last 7 days
   php artisan notices:import-ftp --days=7

   # Or specific range
   php artisan notices:import-ftp --from=2025-12-20 --to=2025-12-27
   ```

   **This will:**
   - Download all files for date range
   - Cache them locally
   - Import all raw data

3. **Disconnect from VPN and reconnect to internet**

4. **Complete pipeline for all dates:**
   ```bash
   # Process same date range
   php artisan notices:import --days=7

   # Or specific range
   php artisan notices:import --from=2025-12-20 --to=2025-12-27
   ```

5. **Review accumulated issues:**
   - Navigate to `/notices/issues`
   - Set date range to cover catch-up period
   - Bulk resolve as appropriate

**Time estimate:** 5-10 minutes per day of catch-up

---

### List Available FTP Files

**Scenario:** Check what files are available on FTP before importing.

**Steps:**

1. **Connect to VPN (if required)**

2. **List files for specific date:**
   ```bash
   php artisan notices:import-ftp --list --date=2025-12-27
   ```

3. **List all available files:**
   ```bash
   php artisan notices:import-ftp --list
   ```

4. **Review output:**
   - Shows available files on FTP server
   - Displays file names, sizes, dates
   - Does not download or import anything

**Use cases:**
- Verify files are being generated
- Check file naming patterns
- Troubleshoot missing files
- Verify FTP connectivity

---

## Troubleshooting Workflows

### Notification Not Found

**Scenario:** Patron claims they didn't receive notification, need to verify.

**Steps:**

1. **Search for patron:**
   - Navigate to `/notices/list`
   - Enter patron barcode in search
   - Or enter patron name

2. **Check notification history:**
   - Click patron barcode to view history
   - Look for expected notification type
   - Check date range when notification should have been sent

3. **If notification found:**
   - Click notification to view details
   - Check status (Pending, Delivered, Failed)
   - Review delivery method
   - Check sent date

4. **If status is "Delivered":**
   - Electronic delivery: Notification was successfully sent
   - Mail: Notification was generated (may not have been printed yet)
   - Review with patron (check email spam, voicemail, etc.)

5. **If status is "Failed":**
   - Click to view failure details
   - Check for related delivery issue
   - Review failed delivery string
   - Update patron contact info in Polaris

6. **If status is "Pending":**
   - Mail: Normal, will be printed by staff
   - Electronic: Check for LX Starter failure issue
   - May need to investigate LX Starter

7. **If notification not found:**
   - Verify patron had item(s) that should trigger notification
   - Check Polaris notification preferences
   - Verify patron has valid delivery preference
   - Check if notification was generated in Polaris

**Documentation:**
- Screenshot notification details
- Document findings
- Update patron record if needed

---

### Investigating Delivery Failures

**Scenario:** Multiple notifications failing for same patron or delivery method.

**Steps:**

1. **Identify the pattern:**
   - Navigate to `/notices/issues`
   - Filter by patron or delivery method
   - Look for recurring failed delivery strings

2. **Check failed delivery string:**
   - Click issue to view details
   - Note the failed contact info (email/phone)
   - Check "Affected Patrons" to see all patrons with same failed string

3. **Verify in Polaris:**
   - Look up patron in Polaris
   - Check current contact information
   - Compare with failed string

4. **Determine cause:**
   - Email bouncing: Invalid/old email, mailbox full, server blocking
   - Phone failing: Disconnected, wrong number, landline instead of mobile
   - Address failing: Invalid address, patron moved

5. **Take corrective action:**
   - Contact patron to get updated information
   - Update patron record in Polaris
   - Change delivery preference if needed
   - Document in patron notes

6. **Resolve in Notices:**
   - Return to delivery issue
   - Click "Resolve Issue"
   - Add notes about what was done
   - Future notifications will use updated info

**For systemic issues:**
- Check LX Starter logs
- Verify LX Starter is processing notifications
- Check FTP uploads to LX Starter
- Contact LX Starter support if needed

---

### Re-importing Specific Date

**Scenario:** Import failed or data needs to be re-processed for specific date.

**Steps:**

1. **Re-import FTP data:**
   ```bash
   php artisan notices:import-ftp --date=2025-12-27
   ```

   **Notes:**
   - Will re-download files from FTP
   - Safe to re-run (won't create duplicates)

2. **Re-import email data:**
   ```bash
   php artisan notices:import-email --date=2025-12-27
   ```

   **Notes:**
   - Will re-fetch emails from Microsoft 365
   - Safe to re-run (won't create duplicates)

3. **Re-run complete pipeline:**
   ```bash
   php artisan notices:import --date=2025-12-27
   ```

   **Notes:**
   - Runs all steps (import, deduplicate, reconcile, detect)
   - Uses cached files if available
   - Safe to re-run multiple times

4. **Verify results:**
   - Check dashboard for updated counts
   - Review notifications for that date
   - Verify issues were detected correctly

---

### Investigating Missing Notifications

**Scenario:** Expected notifications not appearing in system.

**Steps:**

1. **Check Polaris exports:**
   - Verify NotificationLog has records
   - Check Shoutbomb export files were generated
   - Verify export file naming and date format

2. **Check FTP files:**
   ```bash
   php artisan notices:import-ftp --list --date=2025-12-27
   ```

   - Verify files are on FTP server
   - Check file sizes (empty files indicate no notifications)

3. **Check import logs:**
   ```sql
   SELECT * FROM notification_import_batches
   WHERE date_for = '2025-12-27'
   ORDER BY imported_at DESC;
   ```

4. **Check raw imported data:**
   ```sql
   SELECT COUNT(*) FROM polaris_hold_notifications
   WHERE export_datetime >= '2025-12-27 00:00:00'
     AND export_datetime <  '2025-12-28 00:00:00';
   ```

5. **Check deduplicated notifications:**
   ```sql
   SELECT COUNT(*) FROM notifications
   WHERE first_exported_at >= '2025-12-27 00:00:00'
     AND first_exported_at <  '2025-12-28 00:00:00';
   ```

6. **Common causes:**
   - FTP export failed in Polaris
   - Import command didn't run
   - Wrong date specified
   - Files not transferred to FTP
   - Deduplication removed them (check raw tables)

---

## Issue Resolution Workflows

### Resolving Failed Email Addresses

**Scenario:** Patron has invalid email address causing repeated failures.

**Steps:**

1. **Identify failed email:**
   - Navigate to `/notices/issues`
   - Filter to "Failed Contacts"
   - Find issues with email addresses

2. **Review issue details:**
   - Click issue to view details
   - Note the failed email address
   - Check affected patron barcode

3. **Contact patron:**
   - Call or email patron (if alternate contact available)
   - Request updated email address
   - Verify spelling and format

4. **Update Polaris:**
   - Open patron record in Polaris
   - Update email address
   - Verify delivery preference is set to Email
   - Save changes

5. **Resolve issue:**
   - Return to Notices issue
   - Click "Resolve Issue"
   - Add notes: "Updated email to patron.new@email.com"
   - Confirm resolution

6. **Verify next notification:**
   - Wait for next notification to patron
   - Check that it delivers successfully
   - If still failing, investigate further

---

### Resolving Invalid Phone Numbers

**Scenario:** Voice or SMS notifications failing due to invalid phone numbers.

**Steps:**

1. **Identify failed phone:**
   - Navigate to `/notices/issues`
   - Filter to "Failed Contacts"
   - Find issues with phone numbers

2. **Review issue details:**
   - Click issue to view details
   - Note the failed phone number
   - Check if it's Voice (3) or SMS (8)

3. **Determine cause:**
   - Disconnected number
   - Wrong number
   - Landline for SMS (requires mobile)
   - Invalid format

4. **Contact patron:**
   - Call patron or send letter
   - Request updated phone number
   - Verify number and type (mobile vs landline)

5. **Update Polaris:**
   - Open patron record in Polaris
   - Update phone number(s)
   - Ensure correct phone marked for notifications
   - If landline only, consider switching to Email or Mail

6. **Resolve issue:**
   - Return to Notices issue
   - Click "Resolve Issue"
   - Add notes: "Updated phone to (555) 123-4567 - mobile"
   - Confirm resolution

---

### Bulk Resolving False Positives

**Scenario:** Multiple issues that don't require individual action (e.g., temporary LX Starter delay that resolved itself).

**Steps:**

1. **Identify false positives:**
   - Navigate to `/notices/issues`
   - Toggle "Show Resolved" to see if issues auto-resolved
   - If not, filter to specific issue type or date

2. **Verify they're false positives:**
   - Spot-check a few issues
   - Verify notifications actually delivered
   - Confirm no action needed

3. **Select issues for bulk resolution:**
   - Check boxes for all false positive issues
   - Or use filter to narrow to specific set

4. **Bulk resolve:**
   - Click "Resolve Selected" button
   - Review confirmation modal
   - Add resolution notes: "False positive - temporary LX Starter delay"
   - Click "Resolve All"

5. **Verify resolution:**
   - Issues should disappear from active list
   - Toggle "Show Resolved" to verify they're marked resolved
   - Check resolution notes were saved

---

### Resolving Null Preference Issues

**Scenario:** Patrons have no delivery preference set in Polaris.

**Steps:**

1. **Identify null preference issues:**
   - Navigate to `/notices/issues`
   - Filter to issue type: "No delivery preference set"

2. **Review patron records:**
   - Note patron barcodes
   - May need to handle in batch

3. **Contact patrons:**
   - Send letter or email (if alternate contact available)
   - Ask patron to choose delivery preference
   - Explain options (Mail, Email, Voice, SMS)

4. **Update Polaris:**
   - For each patron who responds:
   - Open patron record in Polaris
   - Set delivery preference
   - Update contact info if needed
   - Save changes

5. **Resolve issues:**
   - Bulk select all updated patrons
   - Click "Resolve Selected"
   - Add notes: "Updated delivery preferences per patron request"
   - Confirm resolution

6. **For non-responders:**
   - Consider default preference (usually Mail)
   - Or follow library policy
   - Document decision

---

## Maintenance Workflows

### Cleanup Import/Export Files

**Scenario:** Periodically remove old cached Polaris/Shoutbomb export files to reclaim disk space and keep storage tidy.

**CLI Steps:**

1. **Preview what will be deleted (recommended):**
   ```bash
   php artisan notices:cleanup-import-logs --from=2026-01-01 --to=2026-01-07 --dry-run
   ```
   - Shows list of matching files
   - Displays total size
   - Logs a `cleanup.import_logs.dry_run` entry in `activity_log`

2. **Delete files after review:**
   ```bash
   php artisan notices:cleanup-import-logs --from=2026-01-01 --to=2026-01-07
   ```
   - Prompts for confirmation before deleting
   - Logs planned operation and final results (deleted/failed counts)

3. **Automate periodic cleanup (optional):**
   ```php
   // In app/Console/Kernel.php
   protected function schedule(Schedule $schedule)
   {
       // Cleanup last 30 days of cached exports weekly
       $schedule->command('notices:cleanup-import-logs --days=30 --force')
           ->weeklyOn(1, '03:00'); // Mondays at 3 AM
   }
   ```

**Web UI Button (example wiring):**

If you expose a "Clean up import logs" button in the web interface (for example under `/notices/settings` in an admin-only section), it should:

1. Trigger a backend controller action that runs the Artisan command, e.g.:
   ```php
   Artisan::call('notices:cleanup-import-logs', [
       '--days'   => 30,
       '--force'  => true,
       '--no-interaction' => true,
   ]);
   ```
2. Show a progress indicator and final status message in the UI:
   - "Scanning cached import/export files..."
   - "Deleted 42 files (1.2 GB) from the last 30 days."
3. Optionally surface a link to recent cleanup entries from `activity_log` so admins can audit what was removed.

**Best practices:**
- Always start with `--dry-run` when testing new ranges
- Avoid `--all` unless you intend to clear the entire cache (pair it with `--force`)
- Schedule cleanup during off-hours to avoid interfering with imports
- Use the activity log to verify what was deleted and when

---

### Weekly Review

**Scenario:** Weekly check of notification system health.

**Steps:**

1. **Review dashboard:**
   - Check total notifications for week
   - Compare to previous weeks
   - Note any unusual patterns

2. **Review resolved issues:**
   - Navigate to `/notices/issues`
   - Toggle "Show Resolved"
   - Set date range to "Last 7 days"
   - Review resolution notes
   - Identify patterns

3. **Review active issues:**
   - Toggle off "Show Resolved"
   - Check if any issues are aging
   - Prioritize old issues for resolution
   - Identify systemic problems

4. **Check delivery method distribution:**
   - Navigate to `/notices/list`
   - Filter by each delivery method
   - Verify expected distribution
   - Investigate any major shifts

5. **Verify automation:**
   - Check that daily imports ran
   - Review activity logs for errors
   - Verify scheduled task is working

**Documentation:**
- Note any concerns
- Schedule follow-up for systemic issues
- Update procedures if needed

---

### Monthly Analysis

**Scenario:** Monthly review for reporting and trend analysis.

**Steps:**

1. **Export data for reporting:**
   - Query database for monthly statistics
   - Count notifications by type and delivery method
   - Calculate success/failure rates
   - Identify top issue types

2. **Compare to previous months:**
   - Track trends over time
   - Identify seasonal patterns
   - Note any degradation in delivery rates

3. **Review patron feedback:**
   - Compile patron complaints
   - Cross-reference with notification data
   - Identify gaps in service

4. **System health check:**
   - Verify all integrations working
   - Check FTP connectivity
   - Verify Microsoft Graph API access
   - Test LX Starter delivery

5. **Update procedures:**
   - Document any process improvements
   - Update staff training materials
   - Revise patron communication

**Reporting metrics:**
- Total notifications sent
- Delivery rate by method
- Issue resolution rate
- Average time to resolution
- Top issue types

---

### Verifying Import File Storage

**Scenario:** Check where imported files are stored locally.

**File storage locations:**

All FTP-downloaded files are cached in subdirectories under `storage/app/notices-exports/`:

```
storage/app/notices-exports/
├── holds/                  # holds_YYYY-MM-DD_HH-MM-SS.txt
├── renew/                  # renew_YYYY-MM-DD_HH-MM-SS.txt
├── overdue/                # overdue_YYYY-MM-DD_HH-MM-SS.txt
├── notification-log/       # notification_log_YYYY-MM-DD_HH-MM-SS.txt
├── notification-history/   # notification_history_YYYY-MM-DD_HH-MM-SS.txt
└── phone-notices/          # PhoneNotices_YYYY-MM-DD_HH-MM-SS.csv
```

**To check cached files:**
```bash
ls -lh storage/app/notices-exports/holds/
ls -lh storage/app/notices-exports/notification-log/
ls -lh storage/app/notices-exports/notification-history/
ls -lh storage/app/notices-exports/phone-notices/
```

**Storage path configuration:**

The base storage path can be configured in `config/notices.php`:
```php
'import' => [
    'storage' => env('NOTICES_IMPORT_STORAGE', storage_path('app/notices-exports')),
],
```

---

### Database Maintenance

**Scenario:** Periodic cleanup and optimization.

**Steps:**

1. **Archive old resolved issues:**
   ```sql
   -- Archive issues older than 90 days
   -- (Check with your retention policy first)
   ```

2. **Optimize tables:**
   ```bash
   php artisan db:optimize
   ```

3. **Check database size:**
   ```sql
   SELECT
       table_name,
       ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
   FROM information_schema.TABLES
   WHERE table_schema = 'your_database'
   AND table_name LIKE 'polaris_%' OR table_name LIKE 'notifications%'
   ORDER BY (data_length + index_length) DESC;
   ```

4. **Review retention policy:**
   - How long to keep raw import data?
   - How long to keep resolved issues?
   - Archive vs. delete strategy

**Recommended retention:**
- Raw imports: 90 days
- Notifications: 1 year
- Resolved issues: 6 months
- Active issues: Indefinite

---

### Updating Lookup Tables

**Scenario:** Polaris adds new notification types or delivery options.

**Steps:**

1. **Sync lookup tables:**
   ```bash
   php artisan notices:sync-lookup-tables
   ```

2. **Verify in settings:**
   - Navigate to `/notices/settings`
   - Check for new entries
   - Configure labels if needed
   - Set visibility preferences

3. **Test with new types:**
   - Wait for notifications of new type
   - Verify they import correctly
   - Verify they display correctly in interface

**When to run:**
- After Polaris updates
- When new notification types are configured
- When delivery options change
- After package updates

---

## See Also

- [Commands Reference](commands.md) - Detailed command documentation
- [Web Interface Guide](user-guide.md) - Using the web interface
