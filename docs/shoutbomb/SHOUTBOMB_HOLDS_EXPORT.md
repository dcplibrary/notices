# DATA SOURCE: SHOUTBOMB HOLDS EXPORT

---

## TABLE OF CONTENTS

- [Source Metadata](#source-metadata)
- [Field Definitions](#field-definitions)
- [Sample Data](#sample-data)
- [Cross-Reference Keys](#cross-reference-keys)
- [Known Quirks & Issues](#known-quirks--issues)
- [SQL Query](#sql-query)
- [Validation Rules](#validation-rules)
- [Processing Notes](#processing-notes)
- [Change Log](#change-log)
- [Contact / Support](#contact--support)
- [AI/LLM Instructions](#aillm-instructions)
- [Related Documentation](#related-documentation)

---

## SOURCE METADATA

**Source Name:** Shoutbomb Holds Notification Export

**Source Type:** SQL Query Export (automated via batch script)

**Frequency:** 4 times daily (8am, 9am, 1pm, 5pm) via Windows Task Scheduler

**File Format:** Pipe-delimited text file

**File Naming Pattern:** 
- Active file: `holds.txt` (overwrites each run)
- Archive file: `holds_submitted_YYYY-MM-DD_HH-MM-SS_.txt` (note trailing underscore)

**Location:** 
- Export path: `C:\shoutbomb\ftp\holds\holds.txt`
- FTP upload: Shoutbomb FTP server at `/holds/holds.txt`
- Archive path: `C:\shoutbomb\logs\holds_submitted_{timestamp}.txt`
- Additional backup: Local FTP server (${LOCAL_FTP_HOST}) - See [Archive and Backup System](SHOUTBOMB_DOCUMENTATION_INDEX.md#archive-and-backup-system) for details

**Purpose:** Submits Phone1 (voice) and TXT Messaging (text) hold notifications to Shoutbomb for patron delivery. Includes patrons with ready holds waiting for pickup.

**Batch Script:** `C:\shoutbomb\shoutbomb.bat holds`

**SQL Script:** `C:\shoutbomb\sql\holds.sql`

---

## FIELD DEFINITIONS

> Fields appear in EXACT order shown below. No header row.

| Field # | Field Name | Data Type | Nullable? | Format/Constraints | Description |
|---------|------------|-----------|-----------|-------------------|-------------|
| 1 | BTitle | VARCHAR(255) | NO | Free text, may contain special chars | Browse title of held item (display only) |
| 2 | CreationDate | VARCHAR(10) | NO | YYYY-MM-DD | Date the hold request was created |
| 3 | SysHoldRequestID | INT | NO | Numeric only | Unique hold request identifier (primary key) |
| 4 | PatronID | INT | NO | Numeric only | Unique patron identifier (internal Polaris ID) |
| 5 | PickupOrganizationID | INT | NO | Numeric only | Branch ID (always 3 for DCPL) |
| 6 | HoldTillDate | VARCHAR(10) | NO | YYYY-MM-DD | Expiration date - patron must pick up by this date |
| 7 | PBarcode | VARCHAR(20) | NO | Varies - see Known Quirks | Library patron barcode |

**Total Field Count:** 7

**Header Row:** NO - Shoutbomb expects no header

**Delimiter:** Pipe (`|`)

**Text Qualifier:** None

**Encoding:** Default SQL Server encoding (Windows-1252/UTF-8)

---

## SAMPLE DATA

```
# NO HEADER ROW

# FIELD STRUCTURE:
[BTitle]|[CreationDate]|[SysHoldRequestID]|[PatronID]|[PickupOrganizationID]|[HoldTillDate]|[PBarcode]

# SAMPLE ROWS:
The Silent Patient|2025-11-10|900001|100001|3|2025-11-17|23307000001001
Becoming|2025-11-11|900004|100004|3|2025-11-18|23307000001004
The Four Winds|2025-11-12|900007|100007|3|2025-11-19|23307000001007
The Thursday Murder Club|2025-11-13|900009|100009|3|2025-11-20|23307000001009
```

**Sample Hold Details:**
- **Alice Johnson** (23307000001001) - Voice - "The Silent Patient" ready
- **David Thompson** (23307000001004) - Text - "Becoming" ready
- **Grace Lee** (23307000001007) - Voice - "The Four Winds" ready
- **Isabel Garcia** (23307000001009) - Voice - "The Thursday Murder Club" ready

**Cross-Reference Note:** 
- These same patron barcodes appear in voice_patrons.txt (Alice, Grace, Isabel) or text_patrons.txt (David)
- These same patron IDs and hold IDs appear in PhoneNotices.csv with notification_type_id = 2
- See ANONYMIZED_SAMPLE_DATA_REFERENCE.md for complete cross-reference mapping

---

## CROSS-REFERENCE KEYS

**This data source links to other sources via:**

| Field in THIS Source | Links to Source | Field in OTHER Source | Relationship |
|---------------------|-----------------|----------------------|--------------|
| PBarcode | Polaris Patron Master | Barcode | 1:1 - Identifies patron |
| PatronID | Polaris Patron Master | PatronID | 1:1 - Internal patron ID |
| SysHoldRequestID | Polaris Hold Requests | SysHoldRequestID | 1:1 - Specific hold request |
| CreationDate + PBarcode | Shoutbomb Failure Reports | Date + Barcode | Many:1 - Match failed notifications |
| SysHoldRequestID | Polaris Daily Hold Verification CSV | HoldRequestID | 1:1 - Verify export completeness |

---

## KNOWN QUIRKS & ISSUES

**Field Order Variations:**
- [x] Fields are ALWAYS in the same order
- [ ] Field order SOMETIMES varies
- [ ] Field order is UNPREDICTABLE

**Patron Barcode Format Variations (Field 7 - PBarcode):**
- **Standard format:** 14-digit numeric starting with `233070` (e.g., `2330701272245`)
  - This is the VAST MAJORITY of patron barcodes
- **Online registrations:** `pacreg` (lowercase or uppercase) + numeric digits of varying length (e.g., `pacreg12345`, `PACREG9876`)
- **Homebound patrons:** `HB` + SPACE + phrase (e.g., `HB Smith Residence`)
- **Other variations:** May contain spaces and words - format is unpredictable
- **Max length:** 20 characters (nvarchar(20) in database)
- **IMPORTANT:** ALL barcode formats are sent to Shoutbomb - none excluded

**Date Format:**
- CreationDate and HoldTillDate use `convert(varchar(10), date, 120)` which produces YYYY-MM-DD
- No time component included (dates only)
- HoldTillDate is always in the future (filtered by `hn.HoldTillDate>GETDATE()` in SQL)

**Duplicate Records:**
- Same patron can appear multiple times in one export (multiple ready holds)
- Same patron+hold can appear across multiple exports:
  - Hold notices are triggered when item is trapped/held
  - Exported at next scheduled time (8am, 9am, 1pm, 5pm)
  - If hold placed after 5pm, notification sent next morning at 8am
  - Second/final hold notice sent 3 days later
- **Shoutbomb deduplicates** - will not send duplicate notifications for same hold on same day
- **Shoutbomb batches** - multiple holds for one patron combined into single notification

**PickupOrganizationID (Field 5):**
- Always `3` for Daviess County Public Library (single-branch system)
- Field is theoretically variable but practically constant
- Shoutbomb requires this field for API calls despite single-branch limitation

**File Availability:**
- Files uploaded at: 8:05am, 9:05am, 1:05pm, 5:05pm (approximately 5 min after scheduled time)
- Archive copies timestamped with script execution time
- Zero-byte files or missing exports indicate query returned no results (no ready holds)

**Special Cases:**
- No test patron exclusions - all patrons with DeliveryOptionID 3 or 8 are included
- No staff exclusions
- DeliveryOptionID filtering:
  - 3 = Phone1 (Voice notifications)
  - 8 = TXT Messaging (SMS notifications)

---

## SQL QUERY

> **File:** `C:\shoutbomb\sql\holds.sql`  
> **Run Time:** Daily at 8am, 9am, 1pm, 5pm via `shoutbomb.bat holds`  
> **Database:** DCPLPRO server, Polaris database

```sql
SET NOCOUNT ON
Select convert(varchar(255), REPLACE(hn.BrowseTitle, '|', '-')) as BTitle
    , convert(varchar(10), q.CreationDate, 120) as CreationDate
    , hr.SysHoldRequestID
    , q.PatronID
    , hn.PickupOrganizationID
    , convert(varchar(10), hn.HoldTillDate, 120) as HoldTillDate
    , convert(varchar(20), p.Barcode) as PBarcode
From
    Results.polaris.NotificationQueue q (nolock)
    join Results.polaris.HoldNotices hn (nolock) on q.ItemRecordID=hn.ItemRecordID and q.PatronID=hn.PatronID and q.NotificationTypeID=hn.NotificationTypeID
    join Polaris.polaris.Patrons p (nolock) on q.PatronID=p.PatronID
    join Polaris.polaris.SysHoldRequests hr on q.PatronID=hr.PatronID and q.ItemRecordID=hr.TrappingItemRecordID
Where
    (q.DeliveryOptionID=3 OR q.DeliveryOptionID=8)
    and hn.HoldTillDate>GETDATE()
Order By 
    p.Barcode
```

**Query Dependencies:**
- **Tables:**
  - `Results.polaris.NotificationQueue` - Pending notifications
  - `Results.polaris.HoldNotices` - Hold notice details
  - `Polaris.polaris.Patrons` - Patron master records
  - `Polaris.polaris.SysHoldRequests` - Hold request master
- **Views:** None
- **Functions:** `GETDATE()` - current server date/time
- **Permissions Required:** SELECT on Results.polaris and Polaris.polaris schemas

**Query Logic:**
- Selects holds from NotificationQueue where patron has opted for voice (3) or text (8) delivery
- Excludes holds that have expired (HoldTillDate must be in future)
- Orders by patron barcode for consistent output

**sqlcmd Execution:**
```batch
sqlcmd -S DCPLPRO -d Polaris -i C:\shoutbomb\sql\holds.sql -o C:\shoutbomb\ftp\holds\holds.txt -h-1 -W -s "|"
```
- `-h-1` - No column headers
- `-W` - Remove trailing spaces
- `-s "|"` - Pipe delimiter

---

## VALIDATION RULES

**Record Count Expectations:**
- Typical daily volume: 50-300 holds across all 4 exports
- Per-export volume: 10-100 rows
- Alert if: More than 500 rows in single export (possible system issue)
- Zero rows is valid: No ready holds for voice/text patrons

**Data Integrity Checks:**
- [ ] All PBarcode values must exist in Polaris.polaris.Patrons table
- [ ] All PatronID values must exist in Polaris.polaris.Patrons table
- [ ] All SysHoldRequestID values must exist in Polaris.polaris.SysHoldRequests table
- [ ] SysHoldRequestID must never be NULL
- [ ] HoldTillDate must be greater than export date (future dates only)
- [ ] CreationDate must be less than or equal to export date (past/present only)
- [ ] PickupOrganizationID should always be 3 for DCPL
- [ ] PBarcode must be 20 characters or less
- [ ] BTitle should not contain pipe characters (handled by REPLACE in SQL)

**Business Logic Validation:**
- Each SysHoldRequestID should correspond to a ready-for-pickup hold in Polaris
- If patron appears multiple times, they have multiple holds ready
- Cross-reference against Polaris Daily Hold Verification CSV to ensure no holds missing
- If same hold appears across multiple exports, verify it hasn't been picked up

---

## PROCESSING NOTES

**Import Strategy:**
- New file uploaded 4x daily, overwrites previous
- Archive files are incremental (never deleted automatically)
- Recommended: Import archives as separate records with timestamp for audit trail

**Transformations Needed:**
- **Normalize PBarcode:** Standardize format for matching (uppercase, trim spaces)
- **Parse BTitle:** Remove/escape pipe characters if present
- **Date handling:** CreationDate and HoldTillDate are already ISO format (YYYY-MM-DD)
- **Deduplication:** Track by SysHoldRequestID + export timestamp to identify same hold across exports

**Dependencies:**
- Must process Polaris Patron Master export BEFORE importing this file (for PBarcode validation)
- Must process Polaris Hold Requests export for hold details
- Should process Shoutbomb failure reports AFTER this to match failed deliveries

**FTP Upload Process:**
1. SQL export writes to: `C:\shoutbomb\ftp\holds\holds.txt`
2. WinSCP uploads to: Shoutbomb FTP `/holds/holds.txt`
3. On success: File moved to `C:\shoutbomb\logs\holds_submitted_{timestamp}.txt`
4. Separate task: Copy archived file to local FTP server
5. WinSCP activity logged to: `C:\shoutbomb\logs\holds.log`

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2025-11-13 | Added REPLACE(hn.BrowseTitle, '\|', '-') to SQL | Prevents parsing errors from pipe characters in titles |
| 2025-11-13 | Changed LEFT JOIN to INNER JOIN for SysHoldRequests | Clarifies intent - SysHoldRequestID never NULL |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Vendor Contact:** Shoutbomb support (contact info TBD)

**Polaris ILS:** Innovative Interfaces

**Documentation:** 
- Batch script: `C:\shoutbomb\shoutbomb.bat`
- SQL script: `C:\shoutbomb\sql\holds.sql`
- Windows Task Scheduler: 4 scheduled tasks (8am, 9am, 1pm, 5pm)

**Last Reviewed:** 2025-11-13

---

## AI/LLM INSTRUCTIONS

> When working with this data source, AI tools MUST:

1. **ALWAYS reference this document** before generating code or making assumptions about field structure
2. **Check field count** - exactly 7 fields, pipe-delimited, NO header row
3. **Validate field order** - fields NEVER change order; if parser finds 8 fields, there's a pipe in BTitle
4. **Handle barcode variations** - PBarcode is NOT always numeric; can contain letters, spaces, phrases
5. **Expect duplicates** - same patron+hold can appear in multiple exports; use SysHoldRequestID for uniqueness
6. **Date format** - always YYYY-MM-DD (ISO format), no time component
7. **PickupOrganizationID** - always 3 for DCPL; don't filter on this value
8. **Archived filenames** - note trailing underscore in timestamp format: `holds_submitted_YYYY-MM-DD_HH-MM-SS_.txt`
9. **If structure doesn't match** - STOP and alert human; do not guess or assume data format

**Critical Reminders:**
- This is the DEFINITIVE source of truth for holds export structure
- Observed patterns in sample data may be incomplete due to barcode variations
- Do NOT assume all barcodes are numeric or follow 233070 pattern
- Do NOT assume one patron = one hold; patrons can have multiple ready holds
- Do NOT filter or exclude any barcode formats; ALL formats are valid

**Common AI Mistakes to Avoid:**
1. Assuming PBarcode is always 14-digit numeric
2. Adding a header row during import
3. Treating duplicate SysHoldRequestID as errors (they're expected across time)
4. Filtering out non-standard barcodes (pacreg, HB, etc.)
5. Using comma delimiter instead of pipe
6. Assuming PickupOrganizationID varies (it doesn't for DCPL)

---

## RELATED DOCUMENTATION

### Master Documentation
- **[SHOUTBOMB_DOCUMENTATION_INDEX.md](SHOUTBOMB_DOCUMENTATION_INDEX.md)** - Central index and system architecture

### Patron Lists
- **[SHOUTBOMB_VOICE_PATRONS.md](SHOUTBOMB_VOICE_PATRONS.md)** - Voice notification patrons
- **[SHOUTBOMB_TEXT_PATRONS.md](SHOUTBOMB_TEXT_PATRONS.md)** - Text notification patrons

### Other Notification Exports
- **[SHOUTBOMB_OVERDUE_EXPORT.md](SHOUTBOMB_OVERDUE_EXPORT.md)** - Overdue notifications
- **[SHOUTBOMB_RENEW_EXPORT.md](SHOUTBOMB_RENEW_EXPORT.md)** - Renewal reminders

### Reports & Validation
- **[SHOUTBOMB_REPORTS_INCOMING.md](SHOUTBOMB_REPORTS_INCOMING.md)** - Failure reports from ShoutBomb
- **[POLARIS_PHONE_NOTICES.md](POLARIS_PHONE_NOTICES.md)** - Native Polaris phone notices export

### API Integration
- **[Polaris_Notification_Guide_PAPIClient.md](Polaris_Notification_Guide_PAPIClient.md)** - Laravel PAPIClient package guide

---

**Last Updated:** November 14, 2025  
**Document Version:** 2.0  
**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library
