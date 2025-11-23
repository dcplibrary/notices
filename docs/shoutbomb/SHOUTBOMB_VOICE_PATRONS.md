# DATA SOURCE: SHOUTBOMB VOICE PATRONS LIST

---

## TABLE OF CONTENTS

- [Source Metadata](#source-metadata)
- [Field Definitions](#field-definitions)
- [Sample Data](#sample-data)
- [Cross-Reference Keys](#cross-reference-keys)
- [Known Quirks & Issues](#known-quirks--issues)
- [Conflict Resolution System](#conflict-resolution-system)
- [SQL Query](#sql-query)
- [Validation Rules](#validation-rules)
- [Processing Notes](#processing-notes)
- [Change Log](#change-log)
- [Contact / Support](#contact--support)
- [AI/LLM Instructions](#aillm-instructions)
- [Related Documentation](#related-documentation)

---

## SOURCE METADATA

**Source Name:** Shoutbomb Voice Notification Patrons Mapping

**Source Type:** SQL Query Export (automated via batch script)

**Frequency:** Once daily at 4:00am via Windows Task Scheduler

**File Format:** Pipe-delimited text file

**File Naming Pattern:** 
- Active file: `voice_patrons.txt` (overwrites each run)
- Archive file: `voice_patrons_submitted_YYYY-MM-DD_HH-MM-SS.txt`

**Location:** 
- Export path: `C:\shoutbomb\ftp\voice_patrons\voice_patrons.txt`
- FTP upload: Shoutbomb FTP server at `/voice_patrons/voice_patrons.txt`
- Archive path: `C:\shoutbomb\logs\voice_patrons_submitted_{timestamp}.txt`
- Additional backup: Local FTP server (${LOCAL_FTP_HOST}) - See [Archive and Backup System](SHOUTBOMB_DOCUMENTATION_INDEX.md#archive-and-backup-system) for details

**Purpose:** Provides Shoutbomb with a daily updated mapping of phone numbers to patron barcodes for patrons who receive voice (Phone1) notifications. Enables Shoutbomb's automated phone service to identify patron accounts when they call in. Updated daily to ensure account changes (switching delivery methods) don't create gaps in service.

**Batch Script:** `C:\shoutbomb\shoutbomb.bat voice_patrons`

**SQL Script:** `C:\shoutbomb\sql\voice_patrons.sql`

**Critical Dependencies:** 
- **MUST run AFTER conflict resolution scripts** (1:30am and 1:45am)
- See "Conflict Resolution System" section below

---

## FIELD DEFINITIONS

> Fields appear in EXACT order shown below. No header row.

| Field # | Field Name | Data Type | Nullable? | Format/Constraints | Description |
|---------|------------|-----------|-----------|-------------------|-------------|
| 1 | PhoneVoice1 | VARCHAR(10) | NO | Numeric only, 10 digits, no dashes | Patron's primary phone number (dashes removed) |
| 2 | Barcode | VARCHAR(20) | NO | Varies - see Known Quirks | Library patron barcode |

**Total Field Count:** 2

**Header Row:** NO

**Delimiter:** Pipe (`|`)

**Text Qualifier:** None

**Encoding:** Default SQL Server encoding (Windows-1252/UTF-8)

---

## SAMPLE DATA

```
# NO HEADER ROW

# FIELD STRUCTURE:
[PhoneVoice1]|[Barcode]

# SAMPLE ROWS (Voice notification patrons):
5551001001|23307000001001
5551001003|23307000001003
5551001005|23307000001005
5551001007|23307000001007
5551001009|23307000001009
```

**Sample Patron Details:**
- **5551001001** - Alice Johnson (Has hold ready)
- **5551001003** - Carol Chen (Has renewal reminder)
- **5551001005** - Emma Williams (Has 2nd overdue)
- **5551001007** - Grace Lee (Has hold ready)
- **5551001009** - Isabel Garcia (Has hold AND renewal)

**Cross-Reference Note:** These same patron barcodes appear in holds.txt, overdue.txt, renew.txt, and PhoneNotices.csv. See ANONYMIZED_SAMPLE_DATA_REFERENCE.md for complete cross-reference mapping.

---

## CROSS-REFERENCE KEYS

**This data source links to other sources via:**

| Field in THIS Source | Links to Source | Field in OTHER Source | Relationship |
|---------------------|-----------------|----------------------|--------------|
| Barcode | Polaris Patron Master | Barcode | 1:1 - Identifies patron |
| PhoneVoice1 | Shoutbomb Notification Exports | Phone lookup | Many:1 - Multiple patrons can share phone |
| Barcode | Shoutbomb Notification Exports | PatronBarcode | 1:1 - Links notifications to patron |
| PhoneVoice1 | Text Patrons List | PhoneVoice1 | **MUST NOT OVERLAP** - Critical validation |

---

## KNOWN QUIRKS & ISSUES

**Field Order Variations:**
- [x] Fields are ALWAYS in the same order
- [ ] Field order SOMETIMES varies
- [ ] Field order is UNPREDICTABLE

**Phone Number Format (Field 1 - PhoneVoice1):**
- **Format:** 10-digit numeric, no dashes, no spaces, no extensions
- **Example:** `5556832395` (not `555-683-2395`)
- **Standardization:** SQL removes dashes with `REPLACE(pr.PhoneVoice1,'-','')`
- **Length validation:** SQL filters `LEN(pr.PhoneVoice1) > 9` to ensure 10+ digits
- **US phone numbers only:** No international formats or extensions supported
- **NULL handling:** Patrons with NULL phone numbers excluded from export

**Barcode Format Variations (Field 2 - Barcode):**
- **EXTREME VARIATIONS:** Unlike notification exports, barcodes in patron lists show much wider variety
- **Single digits:** `110008`, `1090015` (very short barcodes)
- **Date formats:** `11-03-2014` (may be registration dates used as barcodes)
- **Long numeric strings:** `152626263426262` (15+ digits)
- **Standard format:** `23307000022883` (14-digit starting with 233070)
- **Online registrations:** `PACREG146601`, `pacreg88814413` (mixed case)
- **Coded barcodes:** `L95006107`, `T636622499093`, `W98200031`, `SMC2028`, `SRQLS`
- **Mixed case:** `s09946638` (lowercase), `S16792264` (uppercase)
- **Max length:** 20 characters (nvarchar(20) in database)
- **IMPORTANT:** ALL barcode formats are valid and should NOT be filtered
- **Shoutbomb agnostic:** Shoutbomb accepts any barcode format "within reason"

**Duplicate Phone Numbers (CRITICAL PATTERN):**
- **Same phone can appear MULTIPLE times on this list** with different barcodes
- **Example from sample:** `5553130649` appears with barcodes `10100008` and `M22522862`
- **Reason:** Family members sharing phone number (parent + child accounts)
- **Shoutbomb behavior:** Maps one phone â†’ many barcodes (one-to-many relationship)
- **Phone number is the key:** Shoutbomb uses phone to identify caller, then presents all linked accounts

**Phone Number Overlap with Text List (CRITICAL CONSTRAINT):**
- **A phone number can appear multiple times on voice list** âœ“
- **A phone number can appear multiple times on text list** âœ“
- **A phone number CANNOT appear on BOTH lists** âœ— (Shoutbomb requirement)
- **Reason:** Shoutbomb needs phone â†’ delivery method mapping to be unambiguous
- **Conflict resolution:** Automated scripts run at 1:30am and 1:45am to prevent overlap
- **Validation:** Monitor should check for phone overlap between voice and text exports

**Grace Period for Expired Accounts:**
- **Filter:** `pr.Expirationdate > DATEADD(MONTH,-3,GETDATE())`
- **Meaning:** Includes patrons expired within last 3 months (grace period)
- **Reason:** Allows recently expired patrons to continue receiving notifications
- **Active patrons:** Expiration date in future (always included)
- **Recently expired:** Expired 0-90 days ago (included in grace period)
- **Long expired:** Expired 90+ days ago (excluded)

**DeliveryOptionID Exclusivity:**
- **Each patron has exactly ONE DeliveryOptionID** (cannot be 3 AND 8 simultaneously)
- **This export:** DeliveryOptionID = 3 (Phone1 / voice notifications)
- **Text export:** DeliveryOptionID = 8 (TXT Messaging)
- **Result:** A patron barcode appears on exactly ONE list (voice OR text), never both
- **BUT:** A phone number can appear on one list multiple times (family accounts)

**TxtPhoneNumber Field (Internal Polaris Flag):**
- **Not exported but affects query logic**
- **Legacy from Polaris SMS:** Boolean flag indicating which phone field (PhoneVoice1, 2, or 3) is text-enabled
- **DCPL standard:** Always uses PhoneVoice1 for both voice and text
- **Text patrons must have:** TxtPhoneNumber = 1 (PhoneVoice1 is the text number)
- **Voice patrons:** No TxtPhoneNumber requirement (voice works on any phone)
- **Still relevant:** Polaris uses internally even though Shoutbomb handles all SMS

**Phone1CarrierID (Legacy Field):**
- **Not exported but referenced in conflict resolution scripts**
- **Legacy from Polaris SMS:** When Polaris sent texts via carrier email (5555555555@sms.att.net)
- **Still collected:** DCPL still asks patrons for carrier during registration
- **No longer critical:** Shoutbomb handles SMS directly, doesn't need carrier info
- **Backward compatibility:** Kept for Polaris internal processes

---

## CONFLICT RESOLUTION SYSTEM

**Critical Dependency: This export MUST run AFTER conflict resolution scripts**

### **The Problem: Shared Phone Numbers**

**Scenario:**
1. Mother registers with phone `555-123-4567`, selects voice notifications (DeliveryOptionID = 3)
2. Mother appears on voice_patrons list
3. One year later, son registers with same phone `555-123-4567`
4. Staff asks: "Voice, text, or email?"
5. Mother says "text" (unaware she's changing notification method)
6. Staff updates son's account to text (DeliveryOptionID = 8) without checking mother's account
7. **Result:** Same phone on BOTH voice and text lists â†’ **Breaks Shoutbomb**

**Shoutbomb Requirement:**
- Phone number must map to exactly ONE delivery method
- Phone â†’ voice OR phone â†’ text (never both)
- Multiple barcodes can share phone on same list (family accounts)

### **The Solution: Automated Conflict Resolution**

**Schedule:**
1. **1:30am** - `resolve_text.sql` runs (sync voice â†’ text for conflicting phones)
2. **1:45am** - `resolve_voice.sql` runs (sync text â†’ voice for conflicting phones)
3. **4:00am** - `voice_patrons.sql` exports (after conflicts resolved)
4. **5:00am** - `text_patrons.sql` exports (after conflicts resolved)

**Process:**
1. Find accounts updated in last 24 hours with specific DeliveryOptionID
2. Find OTHER accounts sharing same phone number with conflicting DeliveryOptionID
3. Update conflicting accounts to match most recently updated account's DeliveryOptionID
4. Log change in PatronCustomDataStrings (field 16) with note: "SB to [voice|text] [date]"
5. Log conflicts to file: `voice_conflicts_{timestamp}.txt` or `text_conflicts_{timestamp}.txt`

**Example Resolution:**
- Mother account: Phone `555-123-4567`, DeliveryOptionID = 3 (voice)
- Son account: Phone `555-123-4567`, DeliveryOptionID = 8 (text), updated yesterday
- **resolve_voice.sql finds:** Mother's account conflicts with son's recent update
- **Action:** Update mother's DeliveryOptionID from 3 â†’ 8 (both now text)
- **Result:** Both mother and son appear on text list only, not voice list
- **Note added:** "SB to text 11-13-2025" in mother's custom data field

**Conflict Resolution Scripts:**
- **Logging:** `C:\shoutbomb\sql\conflicts\log_voice_conflicts.sql`
- **Logging:** `C:\shoutbomb\sql\conflicts\log_text_conflicts.sql`
- **Resolution:** `C:\shoutbomb\sql\conflicts\resolve_voice.sql`
- **Resolution:** `C:\shoutbomb\sql\conflicts\resolve_text.sql`
- **Batch file:** `C:\shoutbomb\shoutbomb_conflicts.bat`

**Critical Notes:**
- **Most recent wins:** Account updated most recently determines delivery method for entire phone number
- **No manual intervention:** System handles automatically every night
- **Family accounts sync:** All family members with shared phone get same delivery method
- **Staff notification:** Custom data field notes help staff understand automatic changes
- **Log files:** Track which accounts were updated and when

---

## SQL QUERY

> **File:** `C:\shoutbomb\sql\voice_patrons.sql`  
> **Run Time:** Daily at 4:00am via `shoutbomb.bat voice_patrons`  
> **Database:** DCPLPRO server, Polaris database

```sql
SET NOCOUNT ON
SELECT REPLACE(pr.Phonevoice1,'-',''), p.Barcode
	FROM polaris.Polaris.PatronRegistration pr
		inner join Polaris.polaris.patrons p
	ON pr.PatronID = p.PatronID
	WHERE pr.DeliveryOptionID = 3
	AND pr.PhoneVoice1 IS NOT NULL
	AND p.Barcode IS NOT NULL
	AND pr.Expirationdate > DATEADD(MONTH,-3,GETDATE())
	AND LEN(pr.PhoneVoice1) > 9
	ORDER BY p.Barcode
```

**Query Dependencies:**
- **Tables:**
  - `Polaris.Polaris.PatronRegistration` - Patron preferences and phone numbers
  - `Polaris.Polaris.Patrons` - Patron master records
- **Views:** None
- **Functions:** 
  - `GETDATE()` - current server date/time
  - `DATEADD()` - date arithmetic for grace period
  - `REPLACE()` - remove dashes from phone numbers
  - `LEN()` - validate phone number length
- **Permissions Required:** SELECT on Polaris.Polaris schemas

**Query Logic:**
- Selects patrons with DeliveryOptionID = 3 (Phone1/voice)
- Removes dashes from phone numbers for standardization
- Excludes patrons with NULL phone or NULL barcode
- Includes active patrons + 3-month grace period for expired accounts
- Validates phone has 10+ digits (after dash removal)
- Orders by barcode for consistent output

**DeliveryOptionID Reference:**
- 3 = Phone1 (voice notifications) â† **THIS EXPORT**
- 8 = TXT Messaging (text notifications)

**Phone Number Standardization:**
- Database stores: `555-683-2395` or `5556832395`
- Export outputs: `5556832395` (dashes removed)
- Length check: `LEN(pr.PhoneVoice1) > 9` ensures 10-digit format

**sqlcmd Execution:**
```batch
sqlcmd -S DCPLPRO -d Polaris -i C:\shoutbomb\sql\voice_patrons.sql -o C:\shoutbomb\ftp\voice_patrons\voice_patrons.txt -h-1 -W -s "|"
```
- `-h-1` - No column headers
- `-W` - Remove trailing spaces
- `-s "|"` - Pipe delimiter

---

## VALIDATION RULES

**Record Count Expectations:**
- Typical daily volume: 500-2000 voice patrons
- Varies based on patron registration and preference patterns
- Alert if: Drastic change from previous day (possible query/export failure)
- Zero rows: Investigate immediately (all voice patrons can't disappear)

**Data Integrity Checks:**
- [ ] All Barcode values must exist in Polaris.Polaris.Patrons table
- [ ] All PhoneVoice1 values must be exactly 10 digits
- [ ] PhoneVoice1 must be numeric only (no letters, dashes, spaces)
- [ ] Barcode must be 20 characters or less
- [ ] Field count must always be exactly 2
- [ ] No NULL values in either field

**Business Logic Validation:**
- [ ] **CRITICAL:** No phone number from this list appears on text_patrons list (zero overlap)
- [ ] Same phone can appear multiple times with different barcodes (family accounts)
- [ ] Same barcode should NOT appear multiple times (patron has one barcode)
- [ ] Cross-reference barcodes against notification exports to ensure consistency
- [ ] Verify conflict resolution scripts ran successfully (check timestamps)

**Conflict Detection:**
- Compare PhoneVoice1 values against text_patrons export
- If ANY phone appears on both lists â†’ **CRITICAL ERROR**
- Alert indicates conflict resolution scripts failed
- Manual intervention required to sync delivery methods

---

## PROCESSING NOTES

**Import Strategy:**
- New file uploaded daily at 4:00am, overwrites previous
- Archive files are incremental (never deleted automatically)
- Recommended: Import as daily snapshot with timestamp
- Track changes: Compare today's list to yesterday's to detect adds/removes/changes

**Transformations Needed:**
- **Phone already standardized:** 10 digits, no dashes (done in SQL)
- **Normalize barcodes:** Uppercase all barcodes for consistent matching (optional)
- **Deduplicate:** Verify same barcode doesn't appear twice (shouldn't happen)

**Dependencies:**
- **MUST process AFTER conflict resolution** (1:30am, 1:45am scripts)
- Process before notification exports (holds, overdue, renew) for reference
- Compare with text_patrons export to validate no phone overlap

**FTP Upload Process:**
1. SQL export writes to: `C:\shoutbomb\ftp\voice_patrons\voice_patrons.txt`
2. WinSCP uploads to: Shoutbomb FTP `/voice_patrons/voice_patrons.txt`
3. On success: File moved to `C:\shoutbomb\logs\voice_patrons_submitted_{timestamp}.txt`
4. Separate task: Copy archived file to local FTP server
5. WinSCP activity logged to: `C:\shoutbomb\logs\voice_patrons.log`

**Change Tracking:**
- Compare daily: Today's export vs yesterday's export
- **Additions:** Barcodes in today but not yesterday (new voice patrons or switched from text)
- **Removals:** Barcodes in yesterday but not today (switched to text/email/mail or expired)
- **Phone changes:** Same barcode, different phone (patron updated contact info)
- **Family accounts:** Multiple barcodes sharing phone (normal pattern)

**Shoutbomb Usage:**
- Shoutbomb stores phone â†’ barcode mappings
- When patron calls automated phone service, Shoutbomb matches phone to account(s)
- If multiple barcodes for one phone, Shoutbomb presents all accounts
- Patron can interact with any linked account during call

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2020-08-22 | Created conflict resolution system | Automated sync of shared phone numbers |
| (Unknown) | Standardized on PhoneVoice1 | All voice and text use same phone field |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Vendor Contact:** Shoutbomb support (contact info TBD)

**Polaris ILS:** Innovative Interfaces

**Documentation:** 
- Batch script: `C:\shoutbomb\shoutbomb.bat`
- SQL script: `C:\shoutbomb\sql\voice_patrons.sql`
- Conflict resolution: `C:\shoutbomb\shoutbomb_conflicts.bat`
- Windows Task Scheduler: Multiple scheduled tasks (1:30am conflicts, 4:00am export)

**Last Reviewed:** 2025-11-13

---

## AI/LLM INSTRUCTIONS

> When working with this data source, AI tools MUST:

1. **ALWAYS reference this document** before generating code or making assumptions
2. **Check field count** - exactly 2 fields, pipe-delimited, NO header row
3. **Validate phone format** - exactly 10 digits, numeric only, no dashes
4. **Handle barcode variations** - EXTREME variety including single digits, dates, codes, mixed case
5. **Expect duplicate phones** - Same phone can appear multiple times with different barcodes
6. **Validate no overlap** - CRITICAL: No phone from voice list can appear on text list
7. **Understand conflict resolution** - Must run BEFORE this export (1:30am, 1:45am)
8. **Grace period logic** - Includes patrons expired within last 90 days
9. **Family accounts** - One phone â†’ many barcodes is normal and expected
10. **If structure doesn't match** - STOP and alert human

**Critical Reminders:**
- This is the DEFINITIVE source of truth for voice patrons export structure
- **MOST CRITICAL:** Phone number overlap between voice and text lists = FAILURE
- Same phone appearing multiple times on THIS list = EXPECTED (family accounts)
- Barcode format is EXTREMELY variable - do NOT assume any pattern
- Do NOT filter barcodes by format - ALL formats are valid
- Conflict resolution MUST complete before this export runs
- Phone number is Shoutbomb's key field (more important than barcode for phone service)

**Common AI Mistakes to Avoid:**
1. Treating duplicate phone numbers as errors (they're family accounts)
2. Filtering out "unusual" barcode formats (all formats are legitimate)
3. Assuming all barcodes follow 233070 pattern (extreme variation exists)
4. Not validating phone overlap with text_patrons list (CRITICAL check)
5. Adding a header row during import
6. Using comma delimiter instead of pipe
7. Assuming phone has dashes (they're removed in SQL)
8. Not accounting for conflict resolution dependency
9. Filtering out recently expired patrons (90-day grace period)
10. Treating this like notification exports (completely different structure/purpose)

**Validation Example:**
```
voice_patrons has:
5556832395|23307000022883
5556832395|23307000068779  â† Same phone, different barcode = VALID (family)

text_patrons has:
5556832395|23307012345678  â† CRITICAL ERROR: Phone appears on both lists!
```

**Purpose Reminder:**
This is NOT a notification list. This is a phone â†’ barcode mapping for Shoutbomb's automated phone service. When a patron calls, Shoutbomb uses their phone number to look up their library account(s).

---

## RELATED DOCUMENTATION

### Master Documentation
- **[SHOUTBOMB_DOCUMENTATION_INDEX.md](SHOUTBOMB_DOCUMENTATION_INDEX.md)** - Central index and system architecture

### Related Patron List
- **[SHOUTBOMB_TEXT_PATRONS.md](SHOUTBOMB_TEXT_PATRONS.md)** - Text notification patron list (critical: phone numbers CANNOT overlap)

### Notification Exports
- **[SHOUTBOMB_HOLDS_EXPORT.md](SHOUTBOMB_HOLDS_EXPORT.md)** - Hold ready notifications
- **[SHOUTBOMB_OVERDUE_EXPORT.md](SHOUTBOMB_OVERDUE_EXPORT.md)** - Overdue notifications
- **[SHOUTBOMB_RENEW_EXPORT.md](SHOUTBOMB_RENEW_EXPORT.md)** - Renewal reminders

### Reports & Validation
- **[SHOUTBOMB_REPORTS_INCOMING.md](SHOUTBOMB_REPORTS_INCOMING.md)** - Failure reports from ShoutBomb
- **[POLARIS_PHONE_NOTICES.md](POLARIS_PHONE_NOTICES.md)** - Native Polaris phone notices export

### API Integration
- **[Polaris_Notification_Guide_PAPIClient.md](Polaris_Notification_Guide_PAPIClient.md)** - Laravel PAPIClient package guide
- **[Polaris-API-swagger.json](Polaris-API-swagger.json)** - Complete Polaris API specification

---

**Last Updated:** November 14, 2025  
**Document Version:** 2.0  
**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library
