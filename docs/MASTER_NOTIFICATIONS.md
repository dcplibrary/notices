# Master Notifications Data Model (Laravel Package)

**Package:** `dcplibrary/notices`  
**Last Updated:** November 25, 2025  
**Owner:** Brian Lashbrook

---

## Overview

The `notifications` and `notification_events` tables in this package provide a **channel-agnostic, vendor-agnostic master view** of every notification sent to a patron.

They sit on top of the raw sources documented elsewhere:

- Polaris SQL tables (`NotificationLog`, `NotificationHistory`, `HoldNotices`, etc.)
- Shoutbomb exports (holds/overdue/renew + patron lists)
- PhoneNotices.csv (Polaris native export)
- Shoutbomb failure/summary emails

This file explains how those sources are projected into the local Laravel data model.

---

## Tables

### 1. `notifications` (Master Notification)

**Purpose:** One row per _logical_ notification for a patron + item (or patron + batch), independent of delivery method vendor.

**Model:** `Dcplibrary\Notices\Models\Notification`

**Key fields:**

- Identity
  - `id` – local primary key
  - `notification_type_id` – Polaris notification type (2=Hold, 1=Overdue, 7=Courtesy, 8=Fine, 11=Bill, 12=2nd Overdue, 13=3rd Overdue, ...)
  - `notification_level` – 1=default/1st overdue, 2=2nd, 3=3rd (mainly for overdues)
  - `notification_log_id` – **Polaris `NotificationLogID`**, linking back to `notification_logs.polaris_log_id`

- Patron
  - `patron_barcode`
  - `patron_id` (Polaris internal)

- Item / hold
  - `item_barcode`
  - `item_record_id`
  - `bib_record_id`
  - `sys_hold_request_id`

- Dates
  - `notice_date` – primary date for the notification (from `NotificationLog.NotificationDateTime` date part)
  - `held_until` – hold till date (from holds exports / PhoneNotices)
  - `due_date` – due date (from PhoneNotices / overdue/renew exports)

- Delivery context
  - `delivery_option_id` – 1=Mail, 2=Email, 3=Voice, 8=SMS
  - `delivery_string` – raw target used (email or `phone@carrier` or phone number)
  - `reporting_org_id`
  - `site_code`, `site_name`
  - `pickup_area_description`

- Financial context
  - `account_balance`

- Snapshots (denormalized for point-in-time view)
  - `browse_title`, `call_number`
  - `patron_name_first`, `patron_name_last`
  - `patron_email`, `patron_phone`

**Relationships:**

- `Notification::events()` → hasMany `NotificationEvent`
- `Notification::notificationLog()` → belongsTo `NotificationLog` via `notification_log_id` → `notification_logs.polaris_log_id`
- `Notification::notes()` → morphMany `Note`

### 2. `notification_events` (Lifecycle Events)

**Purpose:** Timeline of what happened to each `Notification`.

**Model:** `Dcplibrary\Notices\Models\NotificationEvent`

**Key fields:**

- `notification_id` – FK to `notifications.id`
- `event_type` – `queued`, `exported`, `submitted`, `phonenotices_recorded`, `delivered`, `failed`, `verified`
- `event_at` – datetime of the event
- `delivery_option_id` – channel context at the time of the event
- `status_code` – arbitrary string code (e.g. `"12"` for NotificationStatusID, or Shoutbomb failure code)
- `status_text` – human readable description, **channel-aware** (e.g. `"SMS Delivered"`, `"Email Failed – Invalid Address"`)
- `source_table` – where this event originated (e.g. `notification_logs`, `polaris_phone_notices`, `notice_failure_reports`, `notifications_holds`)
- `source_id` – local PK in that source table (when available)
- `source_file` – raw filename/email subject when relevant
- `import_job_id` – FK to `import_jobs.id` (if tied to a specific import run)

**Relationships:**

- `NotificationEvent::notification()` → belongsTo `Notification`
- `NotificationEvent::notes()` → morphMany `Note`

---

## Projection from NotificationLog

**Source doc:** `docs/notification_verification_package/reference_docs/TABLE_NotificationLog.md`

NotificationLog in Polaris is the **source of truth** for:

- Email notifications
- Mail notifications
- Aggregated voice/SMS sends

In this package, `NotificationLog` is imported to the local `notification_logs` table and then projected into `notifications` + `notification_events` by:

- `Dcplibrary\Notices\Services\NotificationProjectionService`
- `notices:sync-from-logs` Artisan command

### NotificationProjectionService

**Service:** `src/Services/NotificationProjectionService.php`

Responsibilities:

1. **syncFromLog(NotificationLog $log): Notification**
   - Finds or creates a `Notification` where `notification_log_id = $log->polaris_log_id`.
   - Copies core fields:
     - `notification_type_id`, `patron_barcode`, `patron_id`
     - `notice_date` (from `notification_date`)
     - `delivery_option_id`, `delivery_string`, `reporting_org_id`
     - Patron snapshots from accessors on `NotificationLog`:
       - `patron_name_first`, `patron_name_last`, `patron_email`, `patron_phone`

2. **Creates/updates a `NotificationEvent`** for the log row:
   - `event_type` from simplified `NotificationLog.status`:
     - `completed` → `delivered`
     - `failed` → `failed`
     - else → `queued`
   - `event_at` = `notification_date`
   - `delivery_option_id` = `delivery_option_id`
   - `status_code` = `notification_status_id` (stringified)
   - `status_text` = channel-aware description (`Mail Delivered`, `Email Failed – Invalid Address`, `SMS Delivered`, `Voice Failed – Invalid Phone`, etc.)
   - `source_table` = `notification_logs`
   - `source_id` = local `notification_logs.id`

3. **syncRange(Carbon $start, Carbon $end): int**
   - Chunks through `NotificationLog` rows in the date range and calls `syncFromLog` for each.

### Artisan Command: `notices:sync-from-logs`

**File:** `src/Console/Commands/SyncNotificationsFromLogs.php`

Signature:

```bash
php artisan notices:sync-from-logs \
    [--from=YYYY-MM-DD] \
    [--to=YYYY-MM-DD] \
    [--days=1]
```

- If `--from` and `--to` are provided, uses that explicit range.
- If neither is provided, defaults to the **last N days** ending today (`--days`, default 1).
- Uses `NotificationProjectionService::syncRange()` under the hood.

### Scheduler Integration

In `NoticesServiceProvider::registerScheduledTasks()` a nightly job runs (configurable):

- **21:45** – `notices:sync-from-logs --days=1` (if `scheduler.sync_from_logs_enabled` is true)
- **22:00** – `notices:aggregate --yesterday` (daily aggregation)

This ensures that **after all imports are done**, the master `notifications` table is synchronized from `NotificationLog` before stats are aggregated.

---

## Enrichment from PhoneNotices & Shoutbomb Exports

The master `notifications` rows start their life anchored to NotificationLog but are enriched with more detail from other sources:

- **PhoneNotices (Polaris native export → `polaris_phone_notices`):**
  - Fills `item_record_id`, `item_barcode`, `browse_title`, `due_date`, `notification_type_id` (exact for overdues), `reporting_org_id`, `site_code`, `site_name`, `pickup_area_description`, `account_balance`.
  - Provides better patron name/email snapshots if NotificationLog is sparse.

- **Shoutbomb export tables:**
  - `notifications_holds` (holds_submitted_*.txt)
  - `notifications_overdue` (overdue_submitted_*.txt)
  - `notifications_renewal` (renew_submitted_*.txt)

These tables:

- Confirm which items were actually exported to Shoutbomb.
- Provide additional timeline points (`export_timestamp`, `hold_till_date`, `renewals`, `renewal_limit`).
- Can be used to add `NotificationEvent` rows of type `exported` or `submitted` with `source_table` pointing to these tables.

**Note:** The enrichment logic is being implemented in stages; see `NotificationImportService` and related commands for current coverage.

---

## Relationship to Existing Docs

- **DATA_SOURCE_FIELD_MAPPING_MATRIX.md** describes where each field comes from across all raw sources. The `notifications` table follows that matrix, using the **first-best source** semantics (e.g. title from whichever file sees it earliest).
- **TABLE_NotificationLog.md** explains the Polaris NotificationLog schema. That document, plus this one, show how `NotificationLog` is now projected into the Laravel data model.
- **POLARIS_PHONE_NOTICES.md** documents the native PhoneNotices.csv export, which maps into `polaris_phone_notices` and then into `notifications` + `notification_events`.

---

## Typical Query Patterns

### 1. Per-Patron Notification History

```php
use Dcplibrary\Notices\Models\Notification;

$notifications = Notification::with(['events', 'notificationLog'])
    ->where('patron_barcode', $barcode)
    ->orderByDesc('notice_date')
    ->get();
```

### 2. Per-Item Notification Timeline

```php
$notifications = Notification::with('events')
    ->where('item_record_id', $itemRecordId)
    ->orderByDesc('notice_date')
    ->get();
```

### 3. Channel-Aware Event Status

```php
use Dcplibrary\Notices\Models\NotificationEvent;

$failedEvents = NotificationEvent::where('event_type', NotificationEvent::TYPE_FAILED)
    ->whereIn('delivery_option_id', [3, 8]) // Voice/SMS
    ->whereBetween('event_at', [$start, $end])
    ->get();
```

---

## Migration Summary

**Migrations creating these tables:**

- `src/Database/Migrations/2025_11_25_000020_create_notifications_table.php`
- `src/Database/Migrations/2025_11_25_000021_create_notification_events_table.php`

Run in your Laravel app (that depends on this package):

```bash
php artisan migrate
```

---

## Next Steps

If you are extending or using this data model:

1. **Backfill from NotificationLog**
   - Run: `php artisan notices:sync-from-logs --days=7` (or a wider range) to seed `notifications`/`notification_events` from existing `notification_logs`.

2. **Wire Enrichment**
   - Extend services to:
     - Fill item/bib/hold fields from `polaris_phone_notices` and `notifications_*` tables.
     - Add additional `NotificationEvent` rows for `exported`, `submitted`, `phonenotices_recorded`, and `failed` (from `notice_failure_reports`).

3. **Update UI/Dashboard**
   - Point any new admin views or verification tools at `notifications` + `notification_events` instead of raw `notification_logs` only.

4. **Keep Docs in Sync**
   - When adding fields or events, update this file and cross-link to the relevant reference docs under `docs/`.