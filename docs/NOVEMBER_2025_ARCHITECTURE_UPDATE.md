# November 2025 Architecture Update

**Date:** November 26, 2025
**Author:** Brian Lashbrook
**Purpose:** Document major architectural changes for future AI assistant context

---

## Overview

This document captures the significant architectural changes made on November 25-26, 2025, to transform the notification system from a simple data import tool into a comprehensive, channel-agnostic notification verification and analytics platform.

**Key Achievement:** Successfully unified disparate data sources (NotificationLog, PhoneNotices, Shoutbomb exports, email reports) into a single, coherent master data model with smart accessors and field precedence logic.

---

## Major Architectural Changes

### 1. Master Notifications Data Model

**Created:** `notifications` and `notification_events` tables

**Purpose:** Provide a **channel-agnostic, vendor-agnostic master view** of every notification sent to a patron, sitting above all raw data sources.

**Key Concepts:**

- **Projection Pattern**: `NotificationLog` (from Polaris) is the source of truth, projected into `notifications` table via `NotificationProjectionService`
- **Lifecycle Tracking**: `notification_events` table tracks the complete lifecycle of each notification (queued → exported → submitted → phonenotices_recorded → delivered/failed → verified)
- **Channel-Aware Status**: Event status text is context-aware (e.g., "SMS Delivered" vs "Voice Failed – Invalid Phone")
- **Point-in-Time Snapshots**: Denormalized patron/item data captured at notification time

**Documentation:**
- `docs/MASTER_NOTIFICATIONS.md` - Complete specification
- `docs/ARCHITECTURE.md` - System architecture overview

**Code:**
- Models: `src/Models/Notification.php`, `src/Models/NotificationEvent.php`
- Migrations: `src/Database/Migrations/2025_11_25_000020_create_notifications_table.php`, `2025_11_25_000021_create_notification_events_table.php`
- Service: `src/Services/NotificationProjectionService.php`
- Command: `src/Console/Commands/SyncNotificationsFromLogs.php` (`notices:sync-from-logs`)

### 2. Enhanced PolarisPhoneNotice with Enrichment Fields

**Migration:** `2025_11_26_000002_expand_polaris_phone_notices_for_enrichment.php`

**New Fields Added:**
```php
notification_type_id   // 1=Overdue, 2=Hold, 7=Courtesy, etc.
delivery_option_id     // 1=Mail, 2=Email, 3=Voice, 8=SMS
sys_hold_request_id    // Links to Polaris hold requests
account_balance        // Patron's account balance at notification time
```

**Why This Matters:**
- PhoneNotices.csv was previously just a validation baseline
- Now serves as the **primary enrichment source** for notification records
- Provides critical linking data (hold IDs, notification types, delivery methods)
- Enables better cross-referencing between NotificationLog and Shoutbomb exports

### 3. Smart Accessor Pattern

**Location:** `src/Models/NotificationLog.php`

**Pattern:** Accessors that intelligently pull data with precedence logic:

```php
// Accessor methods in NotificationLog
public function getPatronNameAttribute()
public function getPatronFirstNameAttribute()
public function getPatronLastNameAttribute()
public function getPatronEmailAttribute()
public function getPatronPhoneAttribute()
public function getItemsAttribute()
```

**Precedence Logic:**
1. **First**: Check `PolarisPhoneNotice` for enriched data
2. **Fallback**: Query Polaris database directly for real-time data

**Benefits:**
- Views and controllers use simple property access: `$notification->patron_email`
- No need to know about data source complexity
- Automatic fallback if enrichment data missing
- Point-in-time snapshots when available, real-time when needed

### 4. Comprehensive Field Mapping & Transformation Rules

**Documentation:** `docs/DATA_SOURCE_FIELD_MAPPING_MATRIX.md` (1050+ lines)

**Key Specifications:**

**Phone Number Standardization:**
```php
// Input: "555-123-4567" or "(555) 123-4567" or "555.123.4567"
// Output: "5551234567" (digits only)
REGEXP_REPLACE(phone, '[^0-9]', '')
```

**Notification Type Inference:**
```php
// Inferred from export filename:
holds*.txt   → notification_type_id = 2 (Hold)
renew*.txt   → notification_type_id = 7 (Renewal Reminder)
overdue*.txt → notification_type_id IN (1,7,8,11,12,13) - requires PhoneNotices enrichment
```

**Delivery Option Inference:**
```php
// Inferred from patron list membership:
IF patron_barcode IN voice_patrons*.txt THEN delivery_option_id = 3 (Voice)
ELSE IF patron_barcode IN text_patrons*.txt THEN delivery_option_id = 8 (SMS)
```

**Date Transformations:**
```php
// PhoneNotices.csv MMDDYYYY → Carbon
"11262025" → Carbon::createFromFormat('mdY', $value)

// Shoutbomb YYYY-MM-DD HH:MM:SS → Carbon
"2025-11-26 08:30:00" → Carbon::parse($value)
```

**Why This Matters:**
- **No more guessing** where fields come from
- **Standardized transformations** prevent data inconsistencies
- **Documented precedence** when multiple sources provide same field
- **Inference rules** fill gaps when data is incomplete

### 5. Enrichment Workflow

**Documented:** `docs/DATA_SOURCE_FIELD_MAPPING_MATRIX.md` (Lines 31-75)

**Critical Order:**

```
1. Import patron lists (voice_patrons*.txt, text_patrons*.txt)
   ↓ Determines delivery_option_id for each patron

2. Import PhoneNotices.csv (validation baseline)
   ↓ Provides item details, notification types, phone numbers

3. Import notification exports (holds*.txt, overdue*.txt, renew*.txt)
   ↓ Cross-reference with patron lists for delivery_option_id
   ↓ Cross-reference with PhoneNotices for notification_type_id

4. Import failure reports (email attachments)
   ↓ Link failures back to notifications via phone/patron

5. Enrich NotificationLog records
   ↓ Fill missing fields using PolarisPhoneNotice data

6. Project to master notifications table
   ↓ Create normalized view via NotificationProjectionService
```

**Why Order Matters:**
- Patron lists must be imported FIRST to enable delivery method inference
- PhoneNotices provides validation baseline for cross-referencing
- Enrichment happens progressively as more data arrives
- Projection to master table happens AFTER all daily imports

### 6. Complete Import Schedule

**Documentation:** `docs/IMPORT_SCHEDULE.md`

**Daily Timeline:**

```
5:30 AM  → Import patron lists (voice/text preferences)
6:30 AM  → Import invalid phone reports (daily email)
8:30 AM  → Import morning notifications + PhoneNotices.csv
9:30 AM  → Import second morning hold export
1:30 PM  → Import afternoon holds
4:30 PM  → Import voice failure reports (daily email)
5:30 PM  → Import evening holds
9:45 PM  → Sync from NotificationLog (projection to master tables)
10:00 PM → Daily aggregation (statistics generation)
```

**Why Multiple Hold Imports:**
- Items become available throughout the day as checked in
- Patrons expect timely notifications
- 4x daily exports ensure quick turnaround (8:00 AM, 9:00 AM, 1:00 PM, 5:00 PM)

**Buffer Times:**
- 20-30 minute buffers after each Polaris/Shoutbomb export
- Ensures FTP upload completion and email delivery
- Prevents importing incomplete data

**Scheduler Configuration:**
- All tasks individually configurable via `settings` table
- Each task has its own `scheduler.*_enabled` flag
- Documented in `src/NoticesServiceProvider.php` (lines 244-382)

---

## Critical Patterns for Future Development

### Pattern 1: Always Use Smart Accessors

**✅ CORRECT:**
```php
// In views/controllers
$notification->patron_email
$notification->patron_phone
$notification->patron_name
```

**❌ INCORRECT:**
```php
// Don't query Polaris directly
$notification->patron->EmailAddress

// Don't access raw NotificationLog fields
$notification->notificationLog->patron_name
```

**Why:** Smart accessors handle precedence logic and provide enriched data automatically.

### Pattern 2: Field Precedence

When multiple sources provide the same field:

1. **PolarisPhoneNotice** (point-in-time snapshot, enriched)
2. **Polaris database** (real-time, authoritative)
3. **NotificationLog** (archived, may be incomplete)

### Pattern 3: Inference Over Storage

**Prefer:** Calculate delivery_option_id from patron list membership
**Over:** Storing delivery_option_id in every table

**Prefer:** Infer notification_type from filename pattern
**Over:** Trusting incomplete source data

**Why:** Source data is often incomplete. Inference rules are documented, testable, and maintainable.

### Pattern 4: Projection, Not Duplication

**The Problem:** Raw sources (NotificationLog, PhoneNotices, Shoutbomb exports) have different schemas and levels of completeness.

**The Solution:** Project all sources into a master `notifications` table with:
- Standardized field names
- Filled gaps from multiple sources
- Lifecycle events in `notification_events`

**Implementation:** `NotificationProjectionService::syncFromLog()`

### Pattern 5: Channel-Aware Status

**✅ GOOD:**
```php
"SMS Delivered"
"Voice Failed – Invalid Phone"
"Email Failed – Invalid Address"
```

**❌ BAD:**
```php
"Delivered"  // Which channel?
"Failed"     // Why? What went wrong?
```

**Why:** Status context matters for troubleshooting and reporting.

---

## View Layer Changes

### Notification Detail View Deduplication

**File:** `resources/views/dashboard/notification-detail.blade.php`

**Change:** Removed duplicate "Delivery To" field

**Before:**
- "Delivery To" shown in both Patron Information AND Notification Details sections
- Confusing duplication of `delivery_string`

**After:**
- **Patron Information section**: Shows registered contact info (`patron_email`, `patron_phone`)
- **Notification Details section**: Shows actual delivery target (`delivery_string`) used for THIS notification

**Why This Makes Sense:**
- Patron section = WHO the patron is (identity, registered contacts)
- Notification section = WHAT happened with this specific notification (actual delivery address, which may include carrier info like `5551234567@vtext.com`)
- Aligns with data model where `patron_email`/`patron_phone` are point-in-time snapshots, `delivery_string` is the actual delivery address used

**Commit:** `refactor: remove duplicate delivery information from notification detail view` (16d10af)

---

## Data Quality Improvements

### Before This Update

**Problems:**
- Patron names from NotificationLog often null/incomplete
- No easy way to know which notifications were actually delivered
- Manual cross-referencing between PhoneNotices and Shoutbomb exports
- Delivery method had to be guessed from context
- No single source of truth for "what happened with this notification?"

### After This Update

**Solutions:**
- Smart accessors pull patron data from PhoneNotices first, always have names
- `notification_events` tracks complete lifecycle with timestamps
- Inference rules automatically determine notification_type and delivery_option
- Master `notifications` table provides single query point for all notification data
- Channel-aware status text eliminates ambiguity

---

## Testing & Validation Notes

### What to Verify After Updates

1. **Smart Accessor Data Quality**
   ```php
   // Check that accessors return enriched data
   $log = NotificationLog::with('polaris_phone_notices')->first();
   dd($log->patron_name);  // Should NOT be null if PhoneNotices exist
   ```

2. **Projection Completeness**
   ```bash
   # Sync and verify
   php artisan notices:sync-from-logs --days=1
   # Check that notifications table has records
   # Check that each notification has events
   ```

3. **Field Mapping**
   ```sql
   -- Verify phone standardization
   SELECT DISTINCT patron_phone FROM polaris_phone_notices;
   -- Should be digits only, no formatting

   -- Verify notification type inference
   SELECT DISTINCT notification_type_id, source_file
   FROM polaris_phone_notices;
   -- holds*.txt should have type_id = 2
   ```

4. **Import Schedule**
   ```bash
   php artisan schedule:list
   # Verify all tasks are registered
   # Verify times match IMPORT_SCHEDULE.md
   ```

---

## Common Gotchas

### Gotcha 1: Import Order Matters

**Problem:** Importing notification exports before patron lists results in missing delivery_option_id.

**Solution:** Always import patron lists FIRST (5:30 AM is scheduled before 8:30 AM).

### Gotcha 2: Projection Timing

**Problem:** Querying `notifications` table before nightly projection (9:45 PM) shows stale data.

**Solution:** Either query `notification_logs` directly during the day, or manually run `notices:sync-from-logs`.

### Gotcha 3: PhoneNotices May Have Duplicates

**Problem:** PhoneNotices.csv can contain multiple rows for same notification if file is re-exported.

**Solution:** Code deduplicates by `(patron_barcode, item_barcode, notice_date, delivery_type)` composite key.

### Gotcha 4: NotificationLog NotificationStatusID is Not Reliable

**Problem:** Polaris NotificationStatusID may not reflect actual delivery for Voice/SMS (handled by Shoutbomb).

**Solution:** Use `notification_events` table which combines NotificationLog status with Shoutbomb delivery reports.

---

## Performance Considerations

### Indexed Fields

**Critical indexes:**
- `polaris_phone_notices`: `(patron_barcode, notice_date)`, `(phone_number)`, `(item_barcode)`
- `notification_logs`: `(patron_barcode, notification_date)`, `(delivery_string)`
- `notifications`: `(patron_barcode, notice_date)`, `(notification_log_id)`
- `notification_events`: `(notification_id, event_at)`

### Query Patterns

**✅ Efficient:**
```php
// Use master table with relationships
Notification::with(['events', 'notificationLog'])
    ->where('patron_barcode', $barcode)
    ->whereBetween('notice_date', [$start, $end])
    ->get();
```

**❌ Inefficient:**
```php
// Don't query Polaris directly in loops
foreach ($notifications as $notification) {
    $patron = $notification->patron;  // N+1 Polaris queries
}
```

---

## Future Enhancements

### Short Term (Next Sprint)

1. **Wire Enrichment Events**
   - Add `NotificationEvent` rows for `exported`, `submitted`, `phonenotices_recorded` events
   - Currently only projection from NotificationLog creates events
   - Should create events during import of each source file

2. **Failure Report Enrichment**
   - Link `notice_failure_reports` to `notifications` via phone/patron matching
   - Add failure events to `notification_events` timeline

3. **Hold Request Linking**
   - Use `sys_hold_request_id` to link to Polaris hold records
   - Show hold pickup location and expiration in notification details

### Medium Term

1. **Real-Time Projection**
   - Consider projecting NotificationLog → notifications immediately on import
   - Instead of nightly batch at 9:45 PM
   - Trade-off: More real-time data vs. more DB writes

2. **Delivery Verification**
   - Compare NotificationLog status with Shoutbomb delivery reports
   - Flag mismatches (NotificationLog says delivered, Shoutbomb says failed)

3. **Patron Communication Preferences**
   - Track patron's preferred communication channel
   - Alert if notification sent via non-preferred channel

---

## Related Documentation

**Architecture & Design:**
- `docs/ARCHITECTURE.md` - System architecture overview
- `docs/MASTER_NOTIFICATIONS.md` - Master data model specification
- `docs/DATA_SOURCE_FIELD_MAPPING_MATRIX.md` - Complete field mapping

**Imports & Scheduling:**
- `docs/IMPORT_SCHEDULE.md` - Daily import schedule and rationale
- `docs/shoutbomb/POLARIS_PHONE_NOTICES.md` - PhoneNotices.csv specification
- `docs/shoutbomb/SHOUTBOMB_REPORTS_INCOMING.md` - Shoutbomb report formats

**Package Reference:**
- `docs/notification_verification_package/new_documentation/README_MASTER.md` - Package overview
- `docs/notification_verification_package/new_documentation/QUICK_REFERENCE_GUIDE.md` - Quick reference

---

## Summary for AI Assistants

**Key Takeaway:** This system now uses a **projection pattern** where disparate sources are unified into a master `notifications` table. The `NotificationLog` model has **smart accessors** that pull from enriched `PolarisPhoneNotice` data first, then fall back to Polaris queries.

**When working with this codebase:**

1. **Always read the docs** (`MASTER_NOTIFICATIONS.md`, `DATA_SOURCE_FIELD_MAPPING_MATRIX.md`) before suggesting changes
2. **Use smart accessors** (`$notification->patron_email`) instead of direct database queries
3. **Respect field precedence**: PolarisPhoneNotice → Polaris → NotificationLog
4. **Honor import order**: Patron lists → PhoneNotices → Exports → Failures → Enrichment → Projection
5. **Channel-aware everything**: Status text, event types, and UI labels should specify channel context
6. **Don't duplicate data**: Use projection and inference patterns instead of storing redundant fields

**Most Important:** The user (Brian) has worked hard to make data "useful and meaningful" by building smart accessors and inference rules. Don't suggest changes that bypass this architecture or revert to direct Polaris queries. The enrichment layer exists for a reason.

---

**Document Version:** 1.0
**Last Updated:** November 26, 2025 (evening)
**Next Review:** After next major architectural change
