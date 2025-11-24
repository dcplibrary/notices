# DATA SOURCE: SHOUTBOMB RENEWAL REMINDER EXPORT

---

## TABLE OF CONTENTS

- [Source Metadata](#source-metadata)
- [Field Definitions](#field-definitions)
- [Sample Data](#sample-data)
- [Cross-Reference Keys](#cross-reference-keys)
- [Known Quirks & Issues](#known-quirks--issues)
- [SQL Queries](#sql-queries)
  - [Monday-Wednesday, Friday-Sunday Script](#monday-wednesday-friday-sunday-script)
  - [Thursday Script](#thursday-script)
- [Validation Rules](#validation-rules)
- [Processing Notes](#processing-notes)
- [Change Log](#change-log)
- [Contact / Support](#contact--support)
- [AI/LLM Instructions](#aillm-instructions)
- [Related Documentation](#related-documentation)

---

## SOURCE METADATA

**Source Name:** Shoutbomb Renewal Reminder/Pre-Due Notification Export

**Source Type:** SQL Query Export (automated via batch script)

**Frequency:** Once daily at approximately 8:03am via Windows Task Scheduler

**File Format:** Pipe-delimited text file

**File Naming Pattern:** 
- Active file: `renew.txt` (overwrites each run)
- Archive file: `renew_submitted_YYYY-MM-DD_HH-MM-SS.txt`

**Location:** 
- Export path: `C:\shoutbomb\ftp\renew\renew.txt`
- FTP upload: Shoutbomb FTP server at `/renew/renew.txt`
- Archive path: `C:\shoutbomb\logs\renew_submitted_{timestamp}.txt`
- Additional backup: Local FTP server (${LOCAL_FTP_HOST}) - See [Archive and Backup System](SHOUTBOMB_DOCUMENTATION_INDEX.md#archive-and-backup-system) for details

**Purpose:** Submits Phone1 (voice) and TXT Messaging (text) renewal reminder notifications to Shoutbomb for patron delivery. Notifies patrons 3 days before items are due (or 4 days on Thursdays to account for Sunday not being a valid due date). Single notification per item.

**Batch Scripts:** 
- Monday-Wednesday, Friday-Sunday: `C:\shoutbomb\shoutbomb.bat renew`
- Thursday only: `C:\shoutbomb\shoutbomb_renew_thursday.bat`

**SQL Scripts:** 
- Monday-Wednesday, Friday-Sunday: `C:\shoutbomb\sql\renew.sql`
- Thursday only: `C:\shoutbomb\sql\renew_thursday.sql`

---

## FIELD DEFINITIONS

> Fields appear in EXACT order shown below. No header row.

| Field # | Field Name | Data Type | Nullable? | Format/Constraints | Description |
|---------|------------|-----------|-----------|-------------------|-------------|
| 1 | PatronID | INT | NO | Numeric only | Unique patron identifier (internal Polaris ID) |
| 2 | ItemBarcode | VARCHAR(20) | NO | Numeric (typically 14 digits starting with 33307) | Physical barcode on item (staff/patron-visible identifier) |
| 3 | Title | VARCHAR(255) | NO | Free text (pipes replaced with dashes) | Browse title of item due soon (display only) |
| 4 | DueDate | VARCHAR(10) | NO | YYYY-MM-DD | Due date of the item (3 or 4 days in future) |
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

**Tab-Delimited CSVs:** Any CSV reference tables exported from Polaris are tab-delimited, not comma-delimited

---

## SAMPLE DATA

```
# NO HEADER ROW

# FIELD STRUCTURE:
[PatronID]|[ItemBarcode]|[Title]|[DueDate]|[ItemRecordID]|[Dummy1]|[Dummy2]|[Dummy3]|[Dummy4]|[Renewals]|[BibliographicRecordID]|[RenewalLimit]|[PatronBarcode]

# SAMPLE ROWS:
8796|33307008102600|Don't trust fish|2025-11-13|899430|||||0|907877|2|23307055559034
13093|33307007096027|Borderlands3|2025-11-13|762378|||||2|816681|2|23307000004852
```

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

---

## KNOWN QUIRKS & ISSUES

**Field Order Variations:**
- [x] Fields are ALWAYS in the same order
- [ ] Field order SOMETIMES varies
- [ ] Field order is UNPREDICTABLE

**Patron Barcode Format Variations (Field 13 - PatronBarcode):**
- **Standard format:** 14-digit numeric starting with `233070` (e.g., `23307055559034`)
  - This is the VAST MAJORITY of patron barcodes
- **Online registrations:** `pacreg` (lowercase or uppercase) + numeric digits of varying length (e.g., `pacreg12345`, `PACREG9876`)
- **Homebound patrons:** `HB` + SPACE + phrase (e.g., `HB Smith Residence`)
- **Other variations:** May contain spaces and words - format is unpredictable
- **Max length:** 20 characters (nvarchar(20) in database)
- **IMPORTANT:** ALL barcode formats are sent to Shoutbomb - none excluded

**Item Barcode Format (Field 2 - ItemBarcode):**
- **Standard format:** 14-digit numeric starting with `33307` (e.g., `33307008102600`)
- Different prefix from patron barcodes (items = 33307, patrons = 233070)
- Max length: 20 characters

**Dummy Fields (Fields 6-9):**
- Always empty strings in output (rendered as `||||` in pipe-delimited file)
- Required by Shoutbomb for integration with other ILS systems
- **IMPORTANT:** Do NOT remove these fields - Shoutbomb expects 13 fields
- Can be ignored during parsing/import for DCPL purposes
- Do NOT attempt to populate with data

**Thursday Special Case - Sunday Due Date Handling:**
- **Library Policy:** Items are NEVER due on Sunday
- Valid due dates: Monday, Tuesday, Wednesday, Thursday, Friday, Saturday only
- **Problem:** If using standard 3-day reminder, Thursday would catch "Sunday due" items
- **Solution:** Thursday uses separate SQL script with 4-day lookahead (getdate()+4)
- **Result:** Thursday catches Monday-due items (skipping Sunday)

**Days Ahead by Export Day:**

| Export Day | Script | Days Ahead | Catches Items Due |
|------------|--------|------------|-------------------|
| Monday | renew.sql | +3 | Thursday |
| Tuesday | renew.sql | +3 | Friday |
| Wednesday | renew.sql | +3 | Saturday |
| Thursday | renew_thursday.sql | +4 | Monday (skips Sunday) |
| Friday | renew.sql | +3 | Monday |
| Saturday | renew.sql | +3 | Tuesday |
| Sunday | renew.sql | +3 | Wednesday |

**Important Notes:**
- Items due Monday receive reminder on EITHER Thursday (+4) OR Friday (+3), not both
- Each item appears in exactly ONE export
- Sunday exports run normally (reminders sent on Sunday even though items can't be due Sunday)

**One-Time Export Logic (CRITICAL DIFFERENCE FROM OVERDUE):**
- Filter: `DueDate = getdate()+3` (or +4 on Thursday) - EXACT MATCH ONLY
- Each item appears in EXACTLY ONE export (the day it hits the 3 or 4 day threshold)
- **Example:**
  - Item due Wednesday Nov 20
  - Sunday Nov 17: Item exported (DueDate = getdate()+3 = Nov 20) âœ“
  - Monday Nov 18: Item NOT exported (DueDate Nov 20 â‰  getdate()+3 = Nov 21)
  - Tuesday Nov 19: Item NOT exported (DueDate Nov 20 â‰  getdate()+3 = Nov 22)
- **This is different from overdue exports** which send the same items daily until resolved
- Shoutbomb receives each renewal reminder once and sends the notification

**Material Type Exclusion:**
- SQL filters out `MaterialTypeID!=12` (Juvenile Audio)
- **MaterialTypeID 12 = Juvenile Audio** - not eligible for renewal reminders
- All other material types are included
- Reason for exclusion: Unknown (possibly non-renewable or different circulation rules)

**PatronRegistration Table Usage:**
- Renewal reminders query `PatronRegistration` table for patron preferences
- This is different from overdue/holds which use `NotificationQueue`
- Both tables contain DeliveryOptionID for determining voice/text preference
- Result is the same: filters for DeliveryOptionID 3 (Phone1) or 8 (TXT Messaging)

**Renewal Fields (Fields 10 & 12):**
- **Renewals:** Count of how many times item has already been renewed (0 = never renewed)
- **RenewalLimit:** Maximum renewals allowed for this item (typically 0-2)
- If Renewals = RenewalLimit, item cannot be renewed again
- These fields help patrons know if they can renew online or must return

**Date Format:**
- DueDate uses `convert(varchar(10), ic.DueDate, 120)` which produces YYYY-MM-DD
- No time component included (dates only)
- DueDate is always in the FUTURE (3 or 4 days ahead of export date)

**Bibliographic vs Item Records:**
- **BibliographicRecordID:** Catalog record (title/author info) - one bib can have many physical copies
- **ItemRecordID:** Specific physical item - one item belongs to one bib
- Example: Library owns 3 copies of "The Great Gatsby" â†’ 1 BibliographicRecord, 3 ItemRecords

**Record Count Patterns:**
- Same patron can appear multiple times (multiple items due on same date)
- Same item will NOT appear multiple times (one-time export)
- Daily volume varies based on circulation patterns and due date distribution

---

## SQL QUERIES

### **Monday-Wednesday, Friday-Sunday Script**

> **File:** `C:\shoutbomb\sql\renew.sql`  
> **Run Time:** Daily at approximately 8:03am (except Thursday)  
> **Database:** DCPLPRO server, Polaris database

```sql
SET NOCOUNT ON
Select pr.PatronID
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
    Polaris.ItemCheckouts ic (nolock)
    join Polaris.Polaris.PatronRegistration pr (nolock) on ic.PatronID=pr.PatronID
    join Polaris.Polaris.Patrons p (nolock) on pr.PatronID=p.PatronID
    join Polaris.Polaris.CircItemRecords cir (nolock) on ic.ItemRecordID=cir.ItemRecordID
    join Polaris.Polaris.BibliographicRecords br (nolock) on cir.AssociatedBibRecordID=br.BibliographicRecordID
Where
    (pr.DeliveryOptionID=3 or pr.DeliveryOptionID=8) and
    convert(varchar (11),ic.DueDate, 101)=convert(varchar (11), getdate()+3, 101) and
    cir.MaterialTypeID!=12
Order By 
    ic.PatronID
```

**Key Filter:** `DueDate = getdate()+3` (exact match for items due in exactly 3 days)

---

### **Thursday Script**

> **File:** `C:\shoutbomb\sql\renew_thursday.sql`  
> **Run Time:** Thursdays only at approximately 8:03am  
> **Database:** DCPLPRO server, Polaris database

```sql
SET NOCOUNT ON
Select pr.PatronID
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
    Polaris.ItemCheckouts ic (nolock)
    join Polaris.Polaris.PatronRegistration pr (nolock) on ic.PatronID=pr.PatronID
    join Polaris.Polaris.Patrons p (nolock) on pr.PatronID=p.PatronID
    join Polaris.Polaris.CircItemRecords cir (nolock) on ic.ItemRecordID=cir.ItemRecordID
    join Polaris.Polaris.BibliographicRecords br (nolock) on cir.AssociatedBibRecordID=br.BibliographicRecordID
Where
    (pr.DeliveryOptionID=3 or pr.DeliveryOptionID=8) and
    convert(varchar (11),ic.DueDate, 101)=convert(varchar (11), getdate()+4, 101) and
    cir.MaterialTypeID!=12
Order By 
    ic.PatronID
```

**Key Filter:** `DueDate = getdate()+4` (exact match for items due in exactly 4 days, skipping Sunday)

**Only Difference:** `getdate()+3` vs `getdate()+4`

---

**Query Dependencies:**
- **Tables:**
  - `Polaris.ItemCheckouts` - Current checkouts
  - `Polaris.Polaris.PatronRegistration` - Patron preferences
  - `Polaris.Polaris.Patrons` - Patron master records
  - `Polaris.Polaris.CircItemRecords` - Item master records
  - `Polaris.Polaris.BibliographicRecords` - Catalog records
- **Views:** None
- **Functions:** `getdate()` - current server date/time
- **Permissions Required:** SELECT on Polaris.Polaris schemas

**Query Logic:**
- Selects items from ItemCheckouts (currently checked out)
- Joins to PatronRegistration for patron delivery preferences
- Filters for voice (3) or text (8) delivery preference
- Filters for items due in exactly 3 days (or 4 on Thursday)
- Excludes MaterialTypeID 12 (Juvenile Audio)
- Orders by PatronID for consistent output

**Date Comparison Logic:**
- `convert(varchar(11), ic.DueDate, 101)` = MM/DD/YYYY format (11 chars)
- `convert(varchar(11), getdate()+3, 101)` = today + 3 days in MM/DD/YYYY
- Comparison is EXACT MATCH (equals, not greater than)
- This ensures each item appears in exactly one export

**DeliveryOptionID Reference:**
- 3 = Phone1 (voice notifications)
- 8 = TXT Messaging (text notifications)

**MaterialTypeID 12:**
- 12 = Juvenile Audio (excluded from renewal reminders)

**sqlcmd Execution:**
```batch
sqlcmd -S DCPLPRO -d Polaris -i C:\shoutbomb\sql\renew.sql -o C:\shoutbomb\ftp\renew\renew.txt -h-1 -W -s "|"
```
- `-h-1` - No column headers
- `-W` - Remove trailing spaces
- `-s "|"` - Pipe delimiter

---

## VALIDATION RULES

**Record Count Expectations:**
- Typical daily volume: 10-50 renewal reminders
- Varies based on circulation patterns and due date distribution
- Alert if: More than 200 rows (possible system issue or unusual circulation spike)
- Zero rows is valid: No items due in exactly 3/4 days for voice/text patrons

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
- [ ] DueDate must be exactly 3 days in future (or 4 on Thursday)
- [ ] DueDate must NOT be a Sunday

**Business Logic Validation:**
- Each ItemRecordID should correspond to a checked-out item in Polaris
- If same patron appears multiple times, they have multiple items due on same date
- DueDate should never fall on Sunday
- Cross-reference DueDate against day of week - Thursday exports should have Monday due dates
- No MaterialTypeID 12 (Juvenile Audio) items should appear
- Same ItemRecordID should NOT appear in consecutive daily exports (one-time only)

**Thursday-Specific Validation:**
- On Thursday exports, all DueDate values should be Monday (4 days ahead)
- On non-Thursday exports, DueDate should be 3 days ahead

---

## PROCESSING NOTES

**Import Strategy:**
- New file uploaded daily at ~8:03am, overwrites previous
- Archive files are incremental (never deleted automatically)
- Recommended: Import archives as separate records with timestamp for audit trail
- Track by ItemRecordID + export date to ensure no duplicates across days

**Transformations Needed:**
- **Normalize PatronBarcode:** Standardize format for matching (uppercase, trim spaces)
- **Date handling:** DueDate is already ISO format (YYYY-MM-DD)
- **Empty fields:** Dummy fields will appear as four consecutive pipes `||||` in file
- **Title cleanup:** Pipe characters already replaced with dashes in SQL
- **Day of week validation:** Verify DueDate is not Sunday

**Dependencies:**
- Must process Polaris Patron Master export BEFORE importing this file (for PatronBarcode validation)
- Must process Polaris Item Master export for item details
- Must process Polaris Checkouts for circulation context
- Should process Shoutbomb failure reports AFTER this to match failed deliveries

**FTP Upload Process:**
1. SQL export writes to: `C:\shoutbomb\ftp\renew\renew.txt`
2. WinSCP uploads to: Shoutbomb FTP `/renew/renew.txt`
3. On success: File moved to `C:\shoutbomb\logs\renew_submitted_{timestamp}.txt`
4. Separate task: Copy archived file to local FTP server
5. WinSCP activity logged to: `C:\shoutbomb\logs\renew.log`

**Deduplication Strategy:**
- Within same export: Same patron can appear multiple times (multiple items due same date)
- Across daily exports: Same item should NEVER appear twice (verify ItemRecordID uniqueness across days)
- If ItemRecordID appears in multiple exports, investigate SQL logic or due date changes

**Tracking Renewal Reminders:**
- Each item gets exactly one reminder per due date
- If patron renews item, new DueDate is set and item will appear again when new due date hits 3-day threshold
- If item becomes overdue without renewal, it disappears from renewal exports and appears in overdue exports

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2025-11-13 | Added REPLACE(br.BrowseTitle, '\|', '-') to SQL | Prevents parsing errors from pipe characters in titles |
| (Unknown) | Created separate Thursday script with getdate()+4 | Handles Sunday due date exclusion policy |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Vendor Contact:** Shoutbomb support (contact info TBD)

**Polaris ILS:** Innovative Interfaces

**Documentation:** 
- Batch scripts: `C:\shoutbomb\shoutbomb.bat`, `C:\shoutbomb\shoutbomb_renew_thursday.bat`
- SQL scripts: `C:\shoutbomb\sql\renew.sql`, `C:\shoutbomb\sql\renew_thursday.sql`
- Windows Task Scheduler: 2 scheduled tasks (daily at ~8:03am, Thursday uses separate batch file)

**Last Reviewed:** 2025-11-13

---

## AI/LLM INSTRUCTIONS

> When working with this data source, AI tools MUST:

1. **ALWAYS reference this document** before generating code or making assumptions about field structure
2. **Check field count** - exactly 13 fields, pipe-delimited, NO header row
3. **Validate field order** - fields NEVER change order
4. **Handle empty fields** - Fields 6-9 are ALWAYS empty; do NOT skip or remove them during parsing
5. **Handle barcode variations** - PatronBarcode is NOT always numeric; can contain letters, spaces, phrases
6. **Understand ONE-TIME export logic** - Each item appears in exactly ONE export, not daily like overdue
7. **Date format** - always YYYY-MM-DD (ISO format), no time component
8. **Thursday special case** - Uses different SQL (+4 instead of +3) to skip Sunday due dates
9. **Two barcode fields** - ItemBarcode (field 2) and PatronBarcode (field 13) are DIFFERENT
10. **Renewals logic** - Renewals (count) â‰¤ RenewalLimit (max allowed)
11. **MaterialType exclusion** - MaterialTypeID 12 (Juvenile Audio) never appears
12. **If structure doesn't match** - STOP and alert human; do not guess or assume data format

**Critical Reminders:**
- This is the DEFINITIVE source of truth for renewal export structure
- **CRITICAL DIFFERENCE FROM OVERDUE:** Items appear ONCE (when DueDate = getdate()+3), not daily
- Do NOT treat this like overdue export (continuous daily snapshot)
- Do NOT expect same ItemRecordID to appear across multiple exports
- Do NOT assume all patron barcodes are numeric or follow 233070 pattern
- Do NOT remove or skip empty Dummy fields - they are required by Shoutbomb
- Do NOT include MaterialTypeID 12 (Juvenile Audio) in any analysis
- Confirm Thursday exports use getdate()+4 logic, not getdate()+3

**Common AI Mistakes to Avoid:**
1. Treating renewal export like overdue export (continuous vs one-time)
2. Expecting same item to appear in multiple consecutive exports
3. Assuming PatronBarcode is always 14-digit numeric
4. Removing or skipping the 4 empty Dummy fields
5. Confusing ItemBarcode with PatronBarcode
6. Adding a header row during import
7. Using comma delimiter instead of pipe
8. Forgetting Thursday uses different SQL (+4 not +3)
9. Allowing Sunday as a valid DueDate
10. Including MaterialTypeID 12 (Juvenile Audio) in results
11. Misunderstanding Renewals vs RenewalLimit
12. Not validating ItemRecordID uniqueness across daily exports

**Simulation Example for Understanding:**
```
Database has items:
- Item A due Nov 20
- Item B due Nov 21

Sunday Nov 17 export (getdate()+3 = Nov 20):
  â†’ Item A exported âœ“
  â†’ Item B not exported

Monday Nov 18 export (getdate()+3 = Nov 21):
  â†’ Item A NOT exported (already sent Sunday)
  â†’ Item B exported âœ“

Each item appears ONCE when its due date matches the filter.
```

---

## RELATED DOCUMENTATION

### Master Documentation
- **[SHOUTBOMB_DOCUMENTATION_INDEX.md](SHOUTBOMB_DOCUMENTATION_INDEX.md)** - Central index and system architecture

### Patron Lists
- **[SHOUTBOMB_VOICE_PATRONS.md](SHOUTBOMB_VOICE_PATRONS.md)** - Voice notification patrons
- **[SHOUTBOMB_TEXT_PATRONS.md](SHOUTBOMB_TEXT_PATRONS.md)** - Text notification patrons

### Other Notification Exports
- **[SHOUTBOMB_HOLDS_EXPORT.md](SHOUTBOMB_HOLDS_EXPORT.md)** - Hold ready notifications
- **[SHOUTBOMB_OVERDUE_EXPORT.md](SHOUTBOMB_OVERDUE_EXPORT.md)** - Overdue notifications

### Reports & Validation
- **[SHOUTBOMB_REPORTS_INCOMING.md](SHOUTBOMB_REPORTS_INCOMING.md)** - Failure reports from ShoutBomb
- **[POLARIS_PHONE_NOTICES.md](POLARIS_PHONE_NOTICES.md)** - Native Polaris phone notices export

### API Integration
- **[Polaris_Notification_Guide_PAPIClient.md](Polaris_Notification_Guide_PAPIClient.md)** - Laravel PAPIClient package guide

---

**Last Updated:** November 14, 2025  
**Document Version:** 2.0  
**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library
