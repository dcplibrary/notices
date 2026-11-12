# Concepts

How a notification moves through the system, the four delivery options, and the issue types staff see. The rules that decide success and failure are in [AGENTS.md](../AGENTS.md).

## Notification Lifecycle

This document describes the end-to-end lifecycle of a library notification as modeled by the `dcplibrary/notices` package.

### Overview

The goal is to monitor, log, and validate notifications for all delivery channels:

- Mail (1)
- Email (2)
- Voice (3)
- SMS (8)

from the moment they are generated in Polaris through export, third-party delivery (Shoutbomb), and final success or failure.

### Generation in Polaris

1. Polaris generates notifications (holds, almost overdue/renewal, overdue, card expiration, etc.).
2. Notifications are queued internally and reflected in tables such as `NotificationLog` and `NotificationHistory`.
3. For phone-related notifications, Polaris also creates daily `PhoneNotices*.csv` exports.

### Export and delivery (Voice & SMS)

1. Voice and SMS notifications are extracted daily and exported for Shoutbomb, using the SQL examples described in `docs/NOTIFICATIONS README.md`.
2. Exports are uploaded to Shoutbomb's FTP server on a schedule (e.g., ~8:05 am for initial batch, multiple times per day for holds).
3. Shoutbomb deduplicates notifications and delivers them to patrons according to its internal schedule.
4. Shoutbomb does not update Polaris with final delivery status.

### Export and delivery (Mail & Email)

1. Mail and Email notifications are primarily tracked via the Polaris `NotificationLog` export.
2. The log is exported daily (around 5 am) and is the source of truth for mail and email delivery status.
3. `NotificationHistory` exports provide additional context.

### Failure reporting

Shoutbomb sends failure-related reports via email, which can be ingested via Microsoft Graph:

- Voice notices that were not delivered.
- Invalid patron phone numbers (patrons will not receive future notices until corrected or delivery option is updated).
- Calls that generated errors.
- Monthly and optional weekly summary reports with statistics and partially redacted invalid numbers.

### Deduplication and official notification timestamp

The same notification can appear in multiple exported files because Shoutbomb needs all pending notifications and Polaris continues to generate them until external events stop the queue.

The package will:

- Deduplicate notifications so that:
  - Same patron
  - Same time window
  - Same delivery option
  - => count as a single notification.
- Use the first time a notification appears in an export as the official notification timestamp.

### Unified notification record

All imported data (Shoutbomb exports, Polaris exports, and email reports) are consolidated into a single `notifications` record with fields for:

- Patron and organization identifiers.
- Delivery channel and type.
- Generation, export, expected delivery, delivered, and failed timestamps.
- Status and failure reason (if any).
- Links back to import batches for traceability.

### Failure detection

Failure detection combines:

- Missing entries in success paths (e.g., a notification never leaves the queue or never appears in exports).
- Explicit failures reported via Shoutbomb emails (undelivered calls, invalid phone numbers, error calls).
- Knowledge that invalid phone numbers block future notices until fixed or delivery option is changed.

The package surfaces per-patron timelines to answer: **"Why did this patron not get a notification?"**

---

## Delivery Options

This document describes the different delivery methods (DeliveryOptionID) used by Polaris ILS for patron notifications.

### Delivery Option IDs

The `delivery_option_id` field in notifications corresponds to Polaris's DeliveryOptionID values. All 8 Polaris delivery options are available in the system:

| ID | Polaris Name | Default Label | Configurable |
|----|--------------|---------------|--------------|
| 1  | Mailing Address | Mail | Yes |
| 2  | Email Address | Email | Yes |
| 3  | Phone 1 | Voice | Yes |
| 4  | Phone 2 | Phone 2 | Yes |
| 5  | Phone 3 | Phone 3 | Yes |
| 6  | FAX | FAX | Yes |
| 7  | EDI | EDI | Yes |
| 8  | TXT Messaging | SMS | Yes |

**Note**: You can customize labels and enable/disable delivery options in the UI using the `lookup_table_overrides` system.

### Configuring Delivery Options

#### Custom Labels

You can customize the display label for any delivery option using the `lookup_table_overrides` table:

```sql
INSERT INTO lookup_table_overrides (table_name, record_id, custom_label)
VALUES ('delivery_options', 3, 'Phone Call');
```

#### Enable/Disable Options

Control which delivery options appear in selection menus:

```sql
-- Disable Phone 2
INSERT INTO lookup_table_overrides (table_name, record_id, is_enabled)
VALUES ('delivery_options', 4, 0);

-- Re-enable it
UPDATE lookup_table_overrides
SET is_enabled = 1
WHERE table_name = 'delivery_options' AND record_id = 4;
```

#### Electronic vs Manual Delivery

Configure whether a delivery option uses LX Starter (electronic) or manual processing using the `notes` field with JSON configuration:

```sql
-- Configure Mail as manual delivery (doesn't use LX Starter)
INSERT INTO lookup_table_overrides (table_name, record_id, notes)
VALUES ('delivery_options', 1, '{"uses_lx_starter": false}');

-- Configure Email to use LX Starter (default behavior)
INSERT INTO lookup_table_overrides (table_name, record_id, notes)
VALUES ('delivery_options', 2, '{"uses_lx_starter": true}');
```

**Default Behavior**: If no configuration exists, delivery options are assumed to use LX Starter (`uses_lx_starter: true`).

**Impact**: This configuration affects:
- Whether `lx_starter_failure` issues are flagged for pending notifications
- Delivery confirmation reconciliation logic

### Status Tracking

#### Electronic Delivery Status
Electronic notifications progress through these statuses:
- `pending`: Exported but not yet delivered
- `delivered`: Successfully sent (confirmed by LX Starter or NotificationLog)
- `failed`: Delivery attempt failed (invalid email, phone number, etc.)

#### Mail Delivery Status
Mail notifications typically remain in `pending` status:
- `pending`: Normal state - waiting for staff to print and mail
- `delivered`: Staff has processed and mailed (requires NotificationLog entry)
- `failed`: Cannot be delivered (invalid address, etc.)

### Issue Types and Delivery Methods

#### lx_starter_failure

This issue type indicates the notification was exported by Polaris but never reached LX Starter for processing. This is a system integration failure, not a delivery failure.

**Only applies to delivery options configured with `uses_lx_starter: true`** (default for all options except those explicitly configured otherwise).

Delivery options configured with `uses_lx_starter: false` will NOT be flagged with this issue type because:
- They don't go through LX Starter
- Being `pending` is normal - they're waiting for manual processing
- Flagging them as "LX Starter failure" would be misleading

#### failed_delivery_string
**Can apply to any delivery method**

Indicates the patron's contact information (email, phone, address) has been identified as invalid or unreachable through automated delivery failures.

#### null_preference
**Applies to all delivery methods**

The patron has no delivery preference configured in Polaris, so the notification cannot be sent.

### Code References

See `src/Models/DeliveryOption.php` for:
- `displayLabel()` accessor - Returns custom label or default Polaris label
- `usesLxStarter()` method - Checks if delivery option uses LX Starter based on configuration

The system reads all delivery option configuration from the database - there are no hardcoded delivery option IDs or labels in the codebase.

---

## Delivery Issue Types

This document describes the different types of delivery issues tracked in the `patron_delivery_issues` table.

### Issue Type Overview

| Issue Type | User-Friendly Name | Description | Auto-Resolvable | Typical Resolution |
|------------|-------------------|-------------|-----------------|-------------------|
| `failed_delivery_string` | Failed contact | Patron's contact info is invalid/unreachable | Yes | Update patron record or resolve manually |
| `lx_starter_failure` | Not delivered to patron | Notification generated but never uploaded to LX Starter | Yes | Usually auto-resolves when delivery completes |
| `polaris_export_failure` | Polaris → LX Starter failure | Notification exported from Polaris but never sent by LX Starter | No | Requires investigation of communication breakdown |
| `null_preference` | No delivery preference set | Patron has no delivery method configured | No | Staff must configure patron's delivery preference |
| `manual_review` | Requires manual review | Staff intervention needed | No | Staff reviews and resolves with notes |
| `system_error` | System processing error | Technical/processing failure | No | IT/staff investigates and resolves |

### Detailed Descriptions

#### failed_delivery_string

**User Display:** "Failed contact: [email/phone/address]"

**Cause:** The patron's delivery contact information has been identified as problematic:
- Invalid email address
- Disconnected phone number
- Undeliverable mailing address
- Shoutbomb bounce-back reports

**Detection:**
- Imported from Shoutbomb failure emails
- Tracked in `failed_delivery_strings` table
- Any notification using this contact method gets flagged

**Resolution:**
- Auto-resolves if patron updates their contact information
- Can be manually resolved by staff if patron confirms contact is correct
- Links to specific failed delivery string record for investigation

**Applies to:** All delivery methods (mail, email, voice, SMS)

---

#### lx_starter_failure

**User Display:** "Not delivered to patron"

**Cause:** Notification was generated in Polaris but **never uploaded/exported** to LX Starter SaaS. This indicates the notification never left Polaris, typically due to:
- Export process failure
- File generation issues
- Database/system errors during export

**Detection:**
- Notification has `first_exported_at = NULL` (never exported)
- Remains in `pending` status after reconciliation
- Has a valid electronic delivery method (email/SMS/voice)
- Generated more than grace period ago

**Resolution:**
- Usually auto-resolves when notification eventually gets delivered or fails
- Can be manually resolved if staff confirms delivery through other means

**Important:** Only applies to electronic delivery (email, voice, SMS)
- **NEVER** flagged for mail (delivery_option_id = 1)
- Mail is printed manually by staff and stays `pending` until processed

**Applies to:** Electronic delivery methods only (email, voice, SMS)

---

#### polaris_export_failure

**User Display:** "Notification exported from Polaris but never sent by LX Starter"

**Cause:** Notification was successfully exported from Polaris (`first_exported_at` is set) but never successfully sent by the LX Starter service. This indicates a Polaris → LX Starter communication breakdown, typically:
- LX Starter polling/ingestion failures
- File lock or timing conflicts
- Service interruptions during peak export times
- Network connectivity issues between systems

**Detection:**
- Notification has `first_exported_at` set (successfully exported)
- Remains in `pending` status for >24 hours
- No delivery confirmations received
- Email delivery only (delivery_option_id = 2)

**Pattern Analysis:**
- Often clustered around specific times (e.g., 2:00-2:05 PM)
- May be partial failures (some emails succeed) or complete failures (all fail)
- Can indicate timing conflicts with Polaris export schedule

**Processing:**
Automatically processed daily via `notices:process-orphaned` command:
- Status changed from 'pending' to 'failed'
- `failed_at` timestamp set
- Descriptive `failure_reason` added
- Issue created with detailed cluster context

**Issue Details Include:**
- Cluster type (partial vs complete failure)
- Date and time of failure (12-hour format)
- Number of affected notifications
- Success count (if partial failure)

**Example:**
```
Partial failure cluster on Dec 30, 2025 at 2:00-2:59 PM.
12 notification(s) affected. 5 email(s) sent successfully at the same time.
```

**Viewing:**
- Dashboard: `/notices/issues/orphaned`
- Shows failure clusters with pattern analysis
- Click clusters to view affected notifications
- Active issues displayed prominently in notification details

**Resolution:**
- Requires investigation of Polaris → LX Starter communication
- Check LX Starter logs around failure time
- Verify export timing vs polling schedule
- Review file lock/timing conflicts
- Not auto-resolvable - needs manual investigation

**Important Distinction:**
- `lx_starter_failure`: Never exported (`first_exported_at = NULL`)
- `polaris_export_failure`: Exported but not sent (`first_exported_at` set, pending >24h)

**Applies to:** Email delivery only (delivery_option_id = 2)


---

#### null_preference

**User Display:** "No delivery preference set"

**Cause:** Patron has no delivery method configured in Polaris, so the notification cannot be sent.

**Detection:**
- Notification record has `delivery_option_id = NULL`
- Automatically flagged during reconciliation

**Resolution:**
- Staff must configure patron's preferred delivery method in Polaris
- Cannot auto-resolve - requires staff intervention

**Applies to:** All notification types

---

#### manual_review

**User Display:** "Requires manual review"

**Cause:** Notification or patron situation requires staff attention for reasons that don't fit other categories.

**Detection:**
- Manually flagged by staff or automated rules
- Complex delivery situations
- Special handling requirements

**Resolution:**
- Staff reviews the situation
- Staff adds resolution notes explaining action taken
- Manually marked as resolved

**Applies to:** Any delivery method

---

#### system_error

**User Display:** "System processing error"

**Cause:** Technical failure in the notification processing system:
- Data format/encoding issues
- Database constraint violations
- Unexpected system states

**Detection:**
- Exceptions during import/reconciliation
- Data validation failures
- System processing anomalies

**Resolution:**
- IT/development team investigates
- Fix underlying system issue
- Manually resolve with notes explaining fix

**Applies to:** Any delivery method

---

#### mismatched_preference

**User Display:** "Mismatched patron preference" (customizable via lookup table override)

**Cause:** Multiple barcodes share the same phone number but have conflicting delivery preferences. Shoutbomb cannot deliver notifications when one barcode is set to Voice and another to Text for the same phone number.

**Detection:**
- Imported from Shoutbomb "Mismatched patron preference" emails
- Detected during reconciliation when `shoutbomb_mismatched_preference` delivery confirmations exist
- One issue created per OLD barcode (the barcode that needs updating)

**Issue Details:**
- Contains description: "Barcode X is set to Y, but newer barcode(s) Z on phone P are set to W. Update this barcode to match the newer preference."
- Meta JSON includes:
  - `phone`: The shared phone number
  - `old_barcode`: The barcode that needs updating
  - `old_delivery_option_id`: Current preference (to be changed)
  - `new_barcodes`: Array of newer barcodes with the preference to match
  - `new_delivery_option_id`: The preference to match (Voice or Text)

**Resolution:**
- Staff updates the OLD barcode's delivery preference in Polaris to match the NEW barcode's preference
- Can be manually resolved with resolution notes
- Auto-resolves if the mismatch no longer exists (preferences aligned)

**Applies to:** Voice (3) and SMS (8) only

**Example:**
- Phone: 5555666612
- OLD barcode: 20000973630843 (set to Text)
- NEW barcode: 20000935838013 (set to Voice)
- Action: Update barcode 20000973630843 to Voice to match 20000935838013

---

### Issue Lifecycle

#### Status Flow
1. **Detected** → Issue created with `detected_at` timestamp
2. **Active** → `resolved_at` is NULL
3. **Resolved** → `resolved_at` set with `resolution_notes`

#### Auto-Resolution
Some issues auto-resolve when conditions change:
- `lx_starter_failure`: Resolves when notification status changes from `pending`
- `failed_delivery_string`: Resolves when patron updates contact info

#### Manual Resolution
Other issues require staff action:
- `null_preference`: Staff must configure delivery preference
- `manual_review`: Staff must investigate and document resolution
- `system_error`: IT/staff must fix underlying problem

### Code References

See:
- `src/Support/IssueDisplayHelper.php` - Display logic for all issue types
- `src/Services/NotificationReconciler.php` - Detection logic for lx_starter_failure, failed_delivery_string, null_preference
- `src/Services/NotificationService.php` - Detection and processing for polaris_export_failure
- `src/Console/ProcessOrphanedNotificationsCommand.php` - Command for processing orphaned notifications
- `src/Database/Migrations/2025_12_23_145531_add_lx_starter_failure_issue_type.php` - Initial issue type definitions
- `src/Database/Migrations/2025_12_31_000001_add_polaris_export_failure_issue_type.php` - Added polaris_export_failure type
