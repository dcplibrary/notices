# DATA SOURCE: SHOUTBOMB OVERDUE NOTIFICATIONS EXPORT

---

## TABLE OF CONTENTS

- [Source Metadata](#source-metadata)
- [Field Definitions](#field-definitions)
- [Sample Data](#sample-data)
- [Cross-Reference Keys](#cross-reference-keys)
- [Known Quirks & Issues](#known-quirks--issues)
- [Known Business Process Issue](#known-business-process-issue)
- [SQL Query](#sql-query)
- [Validation Rules](#validation-rules)
- [Processing Notes](#processing-notes)
- [Change Log](#change-log)
- [Contact / Support](#contact--support)
- [AI/LLM Instructions](#aillm-instructions)
- [Related Documentation](#related-documentation)

---

## SOURCE METADATA

**Source Name:** Shoutbomb Overdue/Fine/Bill Notification Export

**Source Type:** SQL Query Export (automated via batch script)

**Frequency:** Once daily at approximately 8:04am via Windows Task Scheduler

**File Format:** Pipe-delimited text file

**File Naming Pattern:** 
- Active file: `overdue.txt` (overwrites each run)
- Archive file: `overdue_submitted_YYYY-MM-DD_HH-MM-SS.txt`

**Location:** 
- Export path: `C:\shoutbomb\ftp\overdue\overdue.txt`
- FTP upload: Shoutbomb FTP server at `/overdue/overdue.txt`
- Archive path: `C:\shoutbomb\logs\overdue_submitted_{timestamp}.txt`
- Additional backup: Local FTP server (${LOCAL_FTP_HOST}) - See [Archive and Backup System](SHOUTBOMB_DOCUMENTATION_INDEX.md#archive-and-backup-system) for details

**Purpose:** Submits Phone1 (voice) and TXT Messaging (text) notifications for overdue items, almost-overdue reminders, fines, and bills to Shoutbomb for patron delivery. Includes items at various stages of the overdue process plus pre-due-date courtesy reminders.

**Batch Script:** `C:\shoutbomb\shoutbomb.bat overdue`

**SQL Script:** `C:\shoutbomb\sql\overdue.sql`

---

## FIELD DEFINITIONS

> Fields appear in EXACT order shown below. No header row.

| Field # | Field Name | Data Type | Nullable? | Format/Constraints | Description |
|---------|------------|-----------|-----------|-------------------|-------------|
| 1 | PatronID | INT | NO | Numeric only | Unique patron identifier (internal Polaris ID) |
| 2 | ItemBarcode | VARCHAR(20) | NO | Numeric (typically 14 digits starting with 33307) | Physical barcode on item (staff/patron-visible identifier) |
| 3 | Title | VARCHAR(255) | NO | Free text (pipes replaced with dashes) | Browse title of overdue item (display only) |
| 4 | DueDate | VARCHAR(10) | NO | YYYY-MM-DD | Original due date of the item |
| 5 | ItemRecordID | INT | NO | Numeric only | Internal Polaris item identifier (database key) |
| 6 | Dummy1 | VARCHAR | YES | Always empty | Placeholder field (Shoutbomb requirement) |
| 7 | Dummy2 | VARCHAR | YES | Always empty | Placeholder field (Shoutbomb requirement) |
| 8 | Dummy3 | VARCHAR | YES | Always empty | Placeholder field (Shoutbomb requirement) |
| 9 | Dummy4 | VARCHAR | YES | Always empty | Placeholder field (Shoutbomb requirement) |
| 10 | Renewals | INT | NO | Numeric (0 or higher) | Number of times item has been renewed |
| 11 | BibliographicRecordID | INT | NO | Numeric only | Catalog record ID (one bib can have multiple item copies) |
| 12 | RenewalLimit | INT | NO | Numeric (typically 0-2) | Maximum renewals allowed for this item type |
| 13 | PatronBarcode | VARCHAR(20) | NO | Varies - see Known Quirks | Library patron barcode |

**Total Field Count:** 13

**Header Row:** NO - Shoutbomb expects no header

**Delimiter:** Pipe (`|`)

**Text Qualifier:** None

**Encoding:** Default SQL Server encoding (Windows-1252/UTF-8)

---

## SAMPLE DATA

```
# NO HEADER ROW

# FIELD STRUCTURE:
[PatronID]|[ItemBarcode]|[Title]|[DueDate]|[ItemRecordID]|[Dummy1]|[Dummy2]|[Dummy3]|[Dummy4]|[Renewals]|[BibliographicRecordID]|[RenewalLimit]|[PatronBarcode]

# SAMPLE ROWS:
100002|33307000002002|Where the Crawdads Sing|2025-10-25|200002|||||0|300002|2|23307000001002
100004|33307000002005|The Midnight Library|2025-10-20|200005|||||1|300005|2|23307000001004
100005|33307000002006|Project Hail Mary|2025-10-10|200006|||||2|300006|2|23307000001005
100006|33307000002007|Atomic Habits|2025-09-30|200007|||||2|300007|2|23307000001006
100010|33307000002010|Lessons in Chemistry|2025-10-28|200010|||||0|300010|2|23307000001010
```

**Sample Overdue Details:**
- **Bob Martinez** (100002) - Text - "Where the Crawdads Sing" - 1st overdue (20 days)
- **David Thompson** (100004) - Text - "The Midnight Library" - 1st overdue (25 days) 
- **Emma Williams** (100005) - Voice - "Project Hail Mary" - 2nd overdue (35 days)
- **Frank Rodriguez** (100006) - Text - "Atomic Habits" - 3rd overdue (45 days)
- **James Taylor** (100010) - Text - "Lessons in Chemistry" - 1st overdue (17 days)

**Cross-Reference Note:**
- These patron barcodes appear in voice_patrons.txt (Emma) or text_patrons.txt (Bob, David, Frank, James)
- These same records appear in PhoneNotices.csv with notification_type_id = 1, 12, or 13 (overdue levels)
- David Thompson (100004) also has a hold (appears in holds.txt) - demonstrates multiple notification types
- See ANONYMIZED_SAMPLE_DATA_REFERENCE.md for complete cross-reference mapping

---

## CROSS-REFERENCE KEYS

**This data source links to other sources via:**

| Field in THIS Source | Links to Source | Field in OTHER Source | Relationship |
|---------------------|-----------------|----------------------|--------------|
| PatronBarcode | Polaris Patron Master | Barcode | 1:1 - Identifies patron |
| PatronID | Polaris Patron Master | PatronID | 1:1 - Internal patron ID |
| ItemBarcode | Polaris Item Records | Barcode | 1:1 - Identifies physical item |
| ItemRecordID | Polaris Item Checkouts | ItemRecordID | 1:1 - Internal item ID |
| BibliographicRecordID | Polaris Bibliographic Records | BibliographicRecordID | Many:1 - Catalog record (multiple copies) |
| DueDate + PatronBarcode | Shoutbomb Failure Reports | Date + Barcode | Many:1 - Match failed notifications |
| ItemRecordID + PatronID | Polaris Verification CSV | Item + Patron | 1:1 - Verify export completeness |

---

## KNOWN QUIRKS & ISSUES

**Field Order Variations:**
- [x] Fields are ALWAYS in the same order
- [ ] Field order SOMETIMES varies
- [ ] Field order is UNPREDICTABLE

**Patron Barcode Format Variations (Field 13 - PatronBarcode):**
- **Standard format:** 14-digit numeric starting with `233070` (e.g., `23307018880550`)
  - This is the VAST MAJORITY of patron barcodes
- **Online registrations:** `pacreg` (lowercase or uppercase) + numeric digits of varying length (e.g., `pacreg12345`, `PACREG9876`)
- **Homebound patrons:** `HB` + SPACE + phrase (e.g., `HB Smith Residence`)
- **Other variations:** May contain spaces and words - format is unpredictable
- **Max length:** 20 characters (nvarchar(20) in database)
- **IMPORTANT:** ALL barcode formats are sent to Shoutbomb - none excluded

**Item Barcode Format (Field 2 - ItemBarcode):**
- **Standard format:** 14-digit numeric starting with `33307` (e.g., `33307007887953`)
- Different prefix from patron barcodes (items = 33307, patrons = 233070)
- Max length: 20 characters

**Dummy Fields (Fields 6-9):**
- Always empty strings in output (rendered as `||||` in pipe-delimited file)
- Required by Shoutbomb for integration with other ILS systems
- **IMPORTANT:** Do NOT remove these fields - Shoutbomb expects 13 fields
- Can be ignored during parsing/import for DCPL purposes
- Do NOT attempt to populate with data

**Notification Type Coverage:**
This export includes multiple notification types, not just overdues:
- **NotificationTypeID 1:** 1st Overdue (Day 1 after due date)
- **NotificationTypeID 7:** Almost overdue/Auto-renew reminder (3 days and 1 day before due)
- **NotificationTypeID 8:** Fine notice
- **NotificationTypeID 11:** Bill notice
- **NotificationTypeID 12:** 2nd Overdue (Day 7 after due date)
- **NotificationTypeID 13:** 3rd Overdue (Day 14 after due date)

**Overdue Progression Timeline:**
- **DueDate - 3 days:** Almost Overdue notice sent (Type 7)
- **DueDate - 1 day:** Almost Overdue notice sent (Type 7)
- **DueDate + 1 day:** 1st Overdue notice sent (Type 1)
- **DueDate + 7 days:** 2nd Overdue notice sent (Type 12)
- **DueDate + 14 days:** 3rd Overdue notice sent (Type 13)
- **DueDate + 21 days:** Item declared LOST and patron billed automatically (for email/mail) or manually (for text/voice)

**24-Hour CreationDate Filter:**
- SQL filters for `nq.CreationDate>GETDATE()-1` (last 24 hours)
- This maintains active notification records in the queue for currently overdue items
- Export contains ALL currently overdue items (daily snapshot), not just newly overdue items
- Same overdue item appears in daily exports repeatedly until resolved
- Example: Item overdue for 5 days will appear in all 5 daily exports
- When item is returned/resolved, it disappears from export and Shoutbomb stops sending notices
- Shoutbomb manages the notification schedule (sends on Day 1, Day 7, Day 14) based on receiving daily updates

**Duplicate Records:**
- Same patron can appear multiple times in one export (multiple overdue items)
- Same item should NOT appear in same export with different notification types
- Same overdue item appears in EVERY daily export until resolved (returned, paid, billed, etc.)
- Items progress through notification stages based on days overdue, but Shoutbomb manages the actual send schedule
- **Shoutbomb batches** - multiple overdues for one patron combined into single notification

**Renewal Fields (Fields 10 & 12):**
- **Renewals:** Count of how many times item has already been renewed (0 = never renewed)
- **RenewalLimit:** Maximum renewals allowed for this item (typically 0-2)
- If Renewals = RenewalLimit, item cannot be renewed again

**Date Format:**
- DueDate uses `convert(varchar(10), ic.DueDate, 120)` which produces YYYY-MM-DD
- No time component included (dates only)
- DueDate can be past, present, or future depending on notification type:
  - Type 7 (Almost overdue): DueDate is in future (1-3 days ahead)
  - Types 1, 12, 13 (Overdue): DueDate is in past (1-14+ days ago)

**Bibliographic vs Item Records:**
- **BibliographicRecordID:** Catalog record (title/author info) - one bib can have many physical copies
- **ItemRecordID:** Specific physical item - one item belongs to one bib
- Example: Library owns 3 copies of "The Great Gatsby" â†’ 1 BibliographicRecord, 3 ItemRecords

---

## KNOWN BUSINESS PROCESS ISSUE

**CRITICAL - 3rd Overdue Confirmation Gap:**

**Problem:**
- Polaris requires confirmation that 3rd Overdue notice was delivered before declaring item LOST
- For email/mail notifications: Polaris handles delivery and tracks confirmation automatically
- For text/voice notifications: Shoutbomb handles delivery but does NOT send confirmation back to Polaris
- Result: Text/voice overdues must be manually billed instead of automatic lost item billing

**Impact:**
- Staff must manually review 3rd overdue text/voice notifications
- Staff must manually bill patrons for lost items (text/voice recipients only)
- Email/mail recipients are billed automatically (7 days after 3rd notice)
- Inconsistent patron experience based on notification preference

**Timeline:**
- Day 14: 3rd Overdue notice sent via Shoutbomb
- Day 21: SHOULD trigger lost status + automatic billing
- Reality: Only email/mail trigger automatically; text/voice require manual intervention

**Future Enhancement:**
- Need automated process to confirm 3rd overdue delivery back to Polaris
- Possible solutions:
  - SQL update to Polaris database after Shoutbomb export
  - API call to Polaris to mark notification as sent
  - Shoutbomb integration to report delivery status back to Polaris
- This is a planned improvement but NOT currently implemented

---

## SQL QUERY

> **File:** `C:\shoutbomb\sql\overdue.sql`  
> **Run Time:** Daily at approximately 8:04am via `shoutbomb.bat overdue`  
> **Database:** DCPLPRO server, Polaris database

```sql
SET NOCOUNT ON
Select nq.PatronID
    , convert(varchar(20), cir.Barcode) as ItemBarcode
    , convert(varchar(255), REPLACE(br.BrowseTitle, '|', '-')) as Title
    , convert(varchar(10), ic.DueDate, 120) as DueDate
    , cir.ItemRecordID
    , '' as Dummy1
    , '' as Dummy2
    , '' as Dummy3
    , '' as Dummy4
    , ic.Renewals
    , br.BibliographicRecordID
    , cir.RenewalLimit
    , convert(varchar(20), p.Barcode) as PatronBarcode
From
    Results.Polaris.NotificationQueue nq (nolock)
    join Polaris.Polaris.Patrons p (nolock) on nq.PatronID=p.PatronID
    join Polaris.Polaris.ItemCheckouts ic (nolock) on nq.PatronId=ic.PatronID and nq.ItemRecordId=ic.ItemRecordID
    join Polaris.Polaris.CircItemRecords cir (nolock) on ic.ItemRecordID=cir.ItemRecordID
    join Polaris.Polaris.BibliographicRecords br (nolock) on cir.AssociatedBibRecordID=br.BibliographicRecordID
Where  
    (nq.DeliveryOptionId=3 OR nq.DeliveryOptionId=8)
    and nq.NotificationTypeId in (1,7,8,11,12,13)
    and nq.CreationDate>GETDATE()-1
Order By
    nq.PatronID
```

**Query Dependencies:**
- **Tables:**
  - `Results.Polaris.NotificationQueue` - Pending notifications
  - `Polaris.Polaris.Patrons` - Patron master records
  - `Polaris.Polaris.ItemCheckouts` - Current checkouts
  - `Polaris.Polaris.CircItemRecords` - Item master records
  - `Polaris.Polaris.BibliographicRecords` - Catalog records
- **Views:** None
- **Functions:** `GETDATE()` - current server date/time
- **Permissions Required:** SELECT on Results.Polaris and Polaris.Polaris schemas

**Query Logic:**
- Selects notifications from NotificationQueue where patron has opted for voice (3) or text (8) delivery
- Filters for overdue-related notification types (1, 7, 8, 11, 12, 13)
- Only includes notifications created in last 24 hours (new entries in queue)
- Joins to get patron barcode, item details, and catalog information
- Orders by PatronID for consistent output

**NotificationTypeID Reference:**
- 1 = 1st Overdue
- 7 = Almost overdue/Auto-renew reminder
- 8 = Fine
- 11 = Bill
- 12 = 2nd Overdue
- 13 = 3rd Overdue

**DeliveryOptionID Reference:**
- 3 = Phone1 (voice notifications)
- 8 = TXT Messaging (text notifications)

**sqlcmd Execution:**
```batch
sqlcmd -S DCPLPRO -d Polaris -i C:\shoutbomb\sql\overdue.sql -o C:\shoutbomb\ftp\overdue\overdue.txt -h-1 -W -s "|"
```
- `-h-1` - No column headers
- `-W` - Remove trailing spaces
- `-s "|"` - Pipe delimiter

---

## VALIDATION RULES

**Record Count Expectations:**
- Typical daily volume: 20-100 notifications
- Alert if: More than 300 rows (possible system issue)
- Zero rows is valid: No new overdue/fine/bill notifications for voice/text patrons in last 24 hours

**Data Integrity Checks:**
- [ ] All PatronBarcode values must exist in Polaris.Polaris.Patrons table
- [ ] All PatronID values must exist in Polaris.Polaris.Patrons table
- [ ] All ItemBarcode values must exist in Polaris.Polaris.CircItemRecords table
- [ ] All ItemRecordID values must exist in Polaris.Polaris.CircItemRecords table
- [ ] All BibliographicRecordID values must exist in Polaris.Polaris.BibliographicRecords table
- [ ] Renewals must be less than or equal to RenewalLimit
- [ ] Renewals and RenewalLimit must be non-negative integers
- [ ] Dummy fields (6-9) should always be empty
- [ ] Field count must always be exactly 13
- [ ] PatronBarcode must be 20 characters or less
- [ ] ItemBarcode must be 20 characters or less
- [ ] Title should not contain pipe characters (handled by REPLACE in SQL)

**Business Logic Validation:**
- Each ItemRecordID should correspond to a checked-out item in Polaris
- If same patron appears multiple times, they have multiple overdue/fine notifications
- DueDate should be in past for Types 1, 12, 13 (overdue notices)
- DueDate should be in near future for Type 7 (almost overdue)
- Cross-reference against Polaris notification records to ensure no notifications missing

---

## PROCESSING NOTES

**Import Strategy:**
- New file uploaded daily at ~8:04am, overwrites previous
- Archive files are incremental (never deleted automatically)
- Recommended: Import archives as separate records with timestamp for audit trail
- Track by PatronID + ItemRecordID + export timestamp to identify notification lifecycle

**Transformations Needed:**
- **Normalize PatronBarcode:** Standardize format for matching (uppercase, trim spaces)
- **Date handling:** DueDate is already ISO format (YYYY-MM-DD)
- **Empty fields:** Dummy fields will appear as four consecutive pipes `||||` in file
- **Title cleanup:** Pipe characters already replaced with dashes in SQL

**Dependencies:**
- Must process Polaris Patron Master export BEFORE importing this file (for PatronBarcode validation)
- Must process Polaris Item Master export for item details
- Must process Polaris Checkouts for circulation context
- Should process Shoutbomb failure reports AFTER this to match failed deliveries

**FTP Upload Process:**
1. SQL export writes to: `C:\shoutbomb\ftp\overdue\overdue.txt`
2. WinSCP uploads to: Shoutbomb FTP `/overdue/overdue.txt`
3. On success: File moved to `C:\shoutbomb\logs\overdue_submitted_{timestamp}.txt`
4. Separate task: Copy archived file to local FTP server
5. WinSCP activity logged to: `C:\shoutbomb\logs\overdue.log`

**Tracking Overdue Progression:**
- To track items through overdue lifecycle, monitor ItemRecordID across daily exports
- Same item appears in EVERY daily export from when it becomes overdue until it's resolved
- Notification type changes as days overdue increases (Type 1 â†’ Type 12 â†’ Type 13)
- Shoutbomb manages actual notification sends based on schedule (Day 1, Day 7, Day 14)
- If item disappears from exports, patron likely returned it, paid fine, or item was manually resolved

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2025-11-13 | Added REPLACE(br.BrowseTitle, '\|', '-') to SQL | Prevents parsing errors from pipe characters in titles |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Vendor Contact:** Shoutbomb support (contact info TBD)

**Polaris ILS:** Innovative Interfaces

**Documentation:** 
- Batch script: `C:\shoutbomb\shoutbomb.bat`
- SQL script: `C:\shoutbomb\sql\overdue.sql`
- Windows Task Scheduler: 1 scheduled task (approximately 8:04am)

**Last Reviewed:** 2025-11-13

---

## AI/LLM INSTRUCTIONS

> When working with this data source, AI tools MUST:

1. **ALWAYS reference this document** before generating code or making assumptions about field structure
2. **Check field count** - exactly 13 fields, pipe-delimited, NO header row
3. **Validate field order** - fields NEVER change order
4. **Handle empty fields** - Fields 6-9 are ALWAYS empty; do NOT skip or remove them during parsing
5. **Handle barcode variations** - PatronBarcode is NOT always numeric; can contain letters, spaces, phrases
6. **Expect duplicates** - same patron can appear multiple times (multiple overdue items)
7. **Date format** - always YYYY-MM-DD (ISO format), no time component
8. **Multiple notification types** - export contains Types 1, 7, 8, 11, 12, 13 (not just overdues)
9. **24-hour window** - only NEW notifications, not cumulative overdues
10. **Two barcode fields** - ItemBarcode (field 2) and PatronBarcode (field 13) are DIFFERENT
11. **Renewals logic** - Renewals (count) â‰¤ RenewalLimit (max allowed)
12. **If structure doesn't match** - STOP and alert human; do not guess or assume data format

**Critical Reminders:**
- This is the DEFINITIVE source of truth for overdue export structure
- Observed patterns in sample data may be incomplete due to barcode variations and notification type diversity
- Do NOT assume all patron barcodes are numeric or follow 233070 pattern
- Do NOT assume all item barcodes start with 33307 (though this is typical)
- Do NOT remove or skip empty Dummy fields - they are required by Shoutbomb
- Do NOT filter by notification type - all types (1, 7, 8, 11, 12, 13) are intentionally included
- Do NOT treat same patron appearing multiple times as duplicates (multiple items overdue)

**Common AI Mistakes to Avoid:**
1. Assuming PatronBarcode is always 14-digit numeric
2. Removing or skipping the 4 empty Dummy fields
3. Confusing ItemBarcode with PatronBarcode
4. Adding a header row during import
5. Filtering out specific notification types
6. Treating all records as overdue (Type 7 is pre-overdue reminder)
7. Misunderstanding the daily export (it IS a daily snapshot of all currently overdue items, continuously sent until resolved)
8. Using comma delimiter instead of pipe
9. Misunderstanding Renewals vs RenewalLimit
10. Expecting one patron = one notification (patrons can have multiple overdues)

---

## RELATED DOCUMENTATION

### Master Documentation
- **[SHOUTBOMB_DOCUMENTATION_INDEX.md](SHOUTBOMB_DOCUMENTATION_INDEX.md)** - Central index and system architecture

### Patron Lists
- **[SHOUTBOMB_VOICE_PATRONS.md](SHOUTBOMB_VOICE_PATRONS.md)** - Voice notification patrons
- **[SHOUTBOMB_TEXT_PATRONS.md](SHOUTBOMB_TEXT_PATRONS.md)** - Text notification patrons

### Other Notification Exports
- **[SHOUTBOMB_HOLDS_EXPORT.md](SHOUTBOMB_HOLDS_EXPORT.md)** - Hold ready notifications
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
