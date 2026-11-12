# Web Interface Guide

Complete guide to using the Notices package web interface for viewing, searching, and managing notifications and delivery issues.

## Table of Contents

- [Overview](#overview)
- [Pages](#pages)
  - [Dashboard](#dashboard)
  - [Notifications List](#notifications-list)
  - [Delivery Issues](#delivery-issues)
  - [Patron History](#patron-history)
  - [Settings](#settings)
- [Search and Filtering](#search-and-filtering)
- [Issue Management](#issue-management)
- [Delivery Options](#delivery-options)

---

## Overview

The web interface is accessible at `/notices` in your Laravel application. All routes are prefixed with `/notices/` and use the `web` middleware group.

Implementation reference:
- Route manifests: `routes/web.php` (feature files under `routes/web/*`)
- Full textual architecture: [ARCHITECTURE_TEXT_DIAGRAM.md](development/architecture.md)

**Navigation:**
- `/notices` or `/notices/dashboard` - Dashboard overview
- `/notices/list` - All notifications
- `/notices/issues` - Delivery issues
- `/notices/patron/{barcode}` - Patron history
- `/notices/settings` - Configuration

---

## Pages

### Dashboard

**URL:** `/notices/dashboard`

The dashboard provides an at-a-glance overview of notification activity and delivery health.

**What you'll see:**

**Stat Cards (1/4 width each):**
- **Total Notifications** - All notifications for selected date range
- **Delivered** - Successfully delivered notifications
- **Pending** - Awaiting delivery or confirmation
- **Failed** - Delivery failures with documented reasons

**Import Status Monitoring:**
- Real-time import tracking with visual indicators (green/yellow/red)
- Last import time for each source (FTP Holds, Renewal, Overdue, Email Reports)
- Schedule adherence checking ("Up to date" or "Behind schedule")
- Failed files list (if any)

**Orphaned Notifications Card (Polaris → LX Starter Failures):**
- Shows notifications exported but never sent by LX Starter
- **Date Range Filter** - Dropdown to select lookback period:
  - Last 24 hours
  - Last 3 days
  - Last 7 days
  - Last 14 days
  - **Last 30 days (default)**
- Mini trend chart showing failure patterns over selected period
- Pattern analysis (e.g., "40% of failures occur on Wednesdays around 2:00 PM")
- Click card to view detailed analysis with clusters and time-based patterns

**Active Delivery Issues:**
- Count of unresolved delivery problems by type
- Quick links to issue management pages

**Use cases:**
- Daily check-in to monitor notification health
- Quick access to recent issues
- Monitor import pipeline health
- Spot trends in orphaned notifications
- Overview before diving into specific problems

**Date Range Selection:**
- Quick filters: Today, Yesterday, Last 7 days, Last 30 days, This month
- Custom date range picker
- Defaults to yesterday (current-day data is typically incomplete)

---

### Notifications List

**URL:** `/notices/list`

Complete searchable list of all notifications across all delivery methods.

#### Display Columns

| Column | Description |
|--------|-------------|
| **Patron** | Patron name (Last, First) and barcode (clickable link to patron history) |
| **Sent Date** | When notification was sent/generated |
| **Type** | Notification type (Hold, Overdue, Renewal, etc.) |
| **Delivery** | Delivery method with icon (Mail, Email, Voice, SMS) |
| **Items** | Number of items in notification |
| **Status** | Current status (Pending, Delivered, Failed) |

#### Search and Filters

**Search box** - Searches across:
- Patron barcode (e.g., `20000902861548`)
- Patron name (first or last name)
- Item title
- Item barcode

**Filters:**
- **Date range** - Filter by sent date
- **Delivery method** - Mail, Email, Voice, SMS, or All
- **Status** - Pending, Delivered, Failed, or All
- **Notification type** - Hold, Overdue, Renewal, etc.

#### Viewing Details

Click any row to open the notification detail modal showing:

**Details tab:**
- Patron information (name, barcode)
- Delivery method and status
- Notification type
- Timestamps (generated, sent, delivered/failed)
- Item information (title, barcode, author)

**Raw Data tab:**
- Complete JSON payload from Polaris export
- All fields from original notification log
- Useful for troubleshooting and data verification

**Timeline tab:**
- Chronological history of notification events
- Import timestamps
- Status changes
- Related issues

#### Use Cases

- Search for specific patron's notifications
- Find all notifications for a specific item
- Verify notification was sent
- Check delivery status for recent notifications
- Export data for reporting (future feature)

---

### Delivery Issues

**URL:** `/notices/issues`

Active delivery problems requiring staff attention or investigation.

#### Display Columns

| Column | Description |
|--------|-------------|
| **☐ Checkbox** | Select for bulk resolution |
| **Patron** | Patron name (Last, First) and barcode (clickable) |
| **Item** | Item browse title and barcode |
| **Issue** | User-friendly issue description |
| **Delivery** | Delivery method (Mail, Email, Voice, SMS) |
| **Detected** | When issue was first detected |

#### Issue Types

| Type | Description | Causes |
|------|-------------|--------|
| **Failed contact: {string}** | Invalid email/phone/address | Email bounced, phone invalid, address incorrect |
| **Not delivered to patron** | LX Starter failure | Electronic delivery stuck pending, not processed by LX Starter |
| **No delivery preference set** | Null delivery preference | Patron has no delivery method selected in Polaris |
| **Requires manual review** | Flagged for staff | Unusual situation requiring investigation |
| **System processing error** | Technical failure | Database error, API failure, processing exception |

#### Search and Filters

**Search box** - Searches across:
- Patron barcode
- Patron name
- Item title
- Item barcode

**Filters:**
- **Date range** - Filter by detection date
- **Issue type** - Failed contacts, LX failures, null preferences, etc.
- **Delivery method** - Mail, Email, Voice, SMS, or All
- **Show Resolved toggle** - Show/hide resolved issues (default: hide)

#### Issue Management

**Individual resolution:**
1. Click an issue row to open detail modal
2. Review issue details and related notification
3. Click "Resolve Issue" button
4. Add optional resolution notes
5. Issue is marked resolved with timestamp

**Bulk resolution:**
1. Check boxes for issues to resolve
2. Click "Resolve Selected" button
3. Review selected issues in confirmation modal
4. Add optional resolution notes (applied to all)
5. Click "Resolve All" to confirm
6. All selected issues are marked resolved

**Viewing resolved issues:**
- Toggle "Show Resolved" button to include resolved issues
- Resolved issues display with strikethrough and resolution date
- View resolution notes in issue detail modal

#### Viewing Details

Click any row to open the issue detail modal with tabs:

**Issue Details tab:**
- Issue type and description
- Detection date
- Patron information
- Item information
- Related delivery attempt information
- Resolution status and notes (if resolved)

**Related Notification tab:**
- Full notification details
- Raw notification data
- Notification timeline
- All items in notification (if multiple)

#### Use Cases

- Daily review of new delivery problems
- Investigate why patron didn't receive notification
- Identify patrons with invalid contact information
- Update patron records in Polaris based on failures
- Resolve issues after fixing underlying problem
- Track resolution of delivery problems over time

#### Important Notes

**Mail notifications:**
- Mail (delivery option 1) should remain "Pending" - this is normal
- Mail is printed manually by staff and doesn't go through LX Starter
- Mail notifications are NOT flagged as "LX Starter failure"

**Electronic delivery (Email, Voice, SMS):**
- These go through LX Starter for automated delivery
- If stuck "Pending" for more than expected time, flagged as "LX Starter failure"
- Usually indicates LX Starter didn't receive or process the notification

**Failed delivery strings:**
- Tracks specific email addresses or phone numbers that fail repeatedly
- Same failed contact across multiple notifications creates one failed string record
- View all affected patrons via "Affected Patrons" link
- Update contact info in Polaris, then resolve the failed string

---

### Patron History

**URL:** `/notices/patron/{barcode}`

Complete notification timeline for a specific patron across all delivery methods.

#### How to Access

- Click patron barcode from Notifications List
- Click patron barcode from Delivery Issues
- Direct URL: `/notices/patron/20000902861548`

#### Display

**Patron header:**
- Patron name
- Patron barcode
- Total notification count
- Active issues count

**Notifications timeline:**
- All notifications for patron (newest first)
- Grouped by date
- Shows all delivery methods
- Status indicators (Pending, Delivered, Failed)
- Related issues highlighted

**Delivery breakdown:**
- Count by delivery method (Mail, Email, Voice, SMS)
- Count by notification type (Hold, Overdue, Renewal)
- Count by status (Pending, Delivered, Failed)

#### Use Cases

- Investigate patron complaint about missing notifications
- Verify notification delivery history
- Identify patterns in delivery failures for specific patron
- Check if patron preferences are working correctly
- Provide accountability to patron ("Yes, we sent it on X date")

---

### Settings

**URL:** `/notices/settings`

Configure display preferences, maintenance tools, and customize lookup table labels.

#### Display Settings

**Notification Types:**
- Customize display names for notification types
- Hide unused notification types from dropdowns
- Add internal notes for staff reference

**Delivery Options:**
- Customize display names (though defaults should be fine)
- Add notes about each delivery method

**How to customize:**
1. Find the lookup table entry
2. Click to edit inline
3. Update label, enabled status, or notes
4. Changes save automatically
5. New labels appear throughout the interface

#### Use Cases

- Simplify notification type names for staff
- Hide deprecated or unused types
- Document internal policies or procedures
- Customize interface for your library's terminology

#### Maintenance Tools (optional)

Instances may add an admin-only "Maintenance" card on the Settings page that exposes safe, one-click maintenance operations. A common example is a **"Clean up cached import/export files"** button that:

1. Calls the `notices:cleanup-import-logs` command from a controller using `Artisan::call()`.
2. Uses consistent flags (e.g., `--days=30 --force --no-interaction`) so behavior is predictable.
3. Shows visual progress and a final confirmation message in the UI.
4. Relies on `activity_log` entries (`cleanup.import_logs.*`) for auditing.

**Example controller action (simplified):**
```php
Artisan::call('notices:cleanup-import-logs', [
    '--days'          => 30,
    '--force'         => true,
    '--no-interaction'=> true,
]);

// Read Artisan::output() or derive a summary from activity_log
// and return a JSON response for the web view to display.
```

This keeps destructive operations server-side while giving staff a convenient, consistent UI entry point.

---

## Search and Filtering

### Search Behavior

The unified search box searches across multiple fields simultaneously:

**Patron fields:**
- Patron barcode - Exact or partial match
- First name - Partial match, case-insensitive
- Last name - Partial match, case-insensitive

**Item fields:**
- Item barcode - Exact or partial match
- Title - Partial match, case-insensitive

**Search tips:**
- Partial matches work: `"smith"` finds `"Smith, John"` and `"Blacksmith, Jane"`
- Numbers are matched exactly: `"233070"` finds all barcodes starting with `233070`
- Searches are case-insensitive
- Notifications/Issues list search uses materialized indexed columns
  (`search_patron_first_name`, `search_patron_last_name`, `search_title`, `search_item_barcode`)
  instead of scanning full `raw_payload` in hot paths.

### Filter Combinations

Filters work together (AND logic):
- Search + Date range + Status = all must match
- Multiple filters narrow results
- Clear filters to broaden search

### Date Range Filtering

**Date range picker:**
- Click date range filter to open picker
- Select start and end dates
- Or use preset ranges (Today, Last 7 days, Last 30 days, etc.)
- Clear to show all dates

---

## Issue Management

### Issue Lifecycle

1. **Detection** - Issue detected during import/reconciliation
2. **Active** - Issue appears in Delivery Issues list (unresolved)
3. **Investigation** - Staff reviews issue details
4. **Action** - Staff updates Polaris or resolves problem
5. **Resolution** - Issue marked resolved in interface
6. **Closed** - Issue hidden by default (visible with "Show Resolved" toggle)

### Auto-Resolution

Some issues resolve automatically when conditions change:

**LX Starter failures:**
- Auto-resolves when notification status changes from Pending to Delivered
- Auto-resolves when notification is marked Failed (different issue type)

**Failed delivery strings:**
- Remains active until manually resolved
- Update patron contact info in Polaris
- Resolve the issue in Notices interface
- Future notifications to that patron will succeed

### Resolution Notes

Add notes when resolving issues to document actions taken:

**Good resolution notes:**
- "Updated email address in Polaris - patron confirmed new address"
- "Phone number disconnected - patron switched to email delivery"
- "Patron prefers mail - updated delivery preference in Polaris"
- "False positive - notification delivered correctly"

**Why notes matter:**
- Track what action was taken
- Help other staff understand resolution
- Provide audit trail
- Identify patterns in failures

---

## Delivery Options

Understanding the four delivery methods and how they behave:

### Mail (ID: 1)

**How it works:**
- Polaris generates notification in NotificationLog
- Notification appears in Notices as "Pending"
- Staff manually prints and mails notification
- Notification stays "Pending" until manually resolved

**Important:**
- "Pending" status is normal for mail
- NOT flagged as LX Starter failure (doesn't use LX Starter)
- Requires manual staff intervention

**When to investigate:**
- Patron claims they didn't receive mailed notification
- Check if notification was generated (should be in list)
- Verify mailing address in Polaris
- Confirm staff printed and mailed it

### Email (ID: 2)

**How it works:**
- Polaris generates notification in NotificationLog
- LX Starter sends email automatically
- Confirmation received via email report
- Status updates to "Delivered" or "Failed"

**Important:**
- Should deliver within minutes to hours
- If stuck "Pending" longer than expected, flagged as LX Starter failure
- Email bounces create "Failed delivery string" issues

**When to investigate:**
- Notification stuck "Pending" - check LX Starter
- "Failed" status - verify email address in Polaris
- Patron claims they didn't receive - check spam folder

### Voice (ID: 3)

**How it works:**
- Shoutbomb export file sent to LX Starter via FTP
- LX Starter makes automated phone call
- Confirmation received via email report
- Status updates based on call completion

**Important:**
- Delivery depends on patron answering phone
- Voicemail counts as delivered
- Invalid phone numbers create "Failed delivery string" issues

**When to investigate:**
- Check if phone number is valid
- Verify patron has voicemail set up
- Check for phone number changes

### SMS (ID: 8)

**How it works:**
- Shoutbomb export file sent to LX Starter via FTP
- LX Starter sends text message
- Confirmation received via email report
- Status updates based on delivery confirmation

**Important:**
- Most reliable electronic delivery method
- Requires valid mobile number
- Carrier issues can cause failures

**When to investigate:**
- Verify phone number is mobile (not landline)
- Check for carrier issues
- Confirm patron has texting plan

---

## Common Tasks

### Finding a Specific Notification

1. Go to Notifications List (`/notices/list`)
2. Enter patron barcode or name in search box
3. Optionally filter by date range
4. Click notification to view details

### Investigating Delivery Failure

1. Go to Delivery Issues (`/notices/issues`)
2. Search for patron or item
3. Click issue to view details
4. Review related notification in modal
5. Take corrective action in Polaris
6. Resolve issue with notes

### Checking Patron's Notification History

1. Go to Patron History (`/notices/patron/{barcode}`)
2. Review all notifications for patron
3. Check for patterns in delivery method or failures
4. Verify expected notifications were sent

### Bulk Resolving Issues

1. Go to Delivery Issues (`/notices/issues`)
2. Filter to specific issue type or date range
3. Check boxes for issues to resolve
4. Click "Resolve Selected"
5. Add resolution notes
6. Confirm bulk resolution

---

## Tips and Best Practices

**Daily workflow:**
1. Check Dashboard for overview
2. Review new Delivery Issues
3. Resolve issues with invalid contact info
4. Update patron records in Polaris
5. Document resolutions with notes

**Weekly review:**
1. Check "Show Resolved" to see resolution patterns
2. Identify recurring problems
3. Update procedures or patron communication
4. Review Mail notifications to ensure staff is processing

**Monthly analysis:**
1. Review delivery method distribution
2. Compare success rates across methods
3. Identify patrons with chronic delivery issues
4. Consider patron communication improvements

---

## See Also

- [Commands Reference](commands.md) - Artisan commands for importing and processing
- [Common Workflows](workflows.md) - Step-by-step workflows for common scenarios
