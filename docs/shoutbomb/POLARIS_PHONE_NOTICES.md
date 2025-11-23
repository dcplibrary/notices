# DATA SOURCE: POLARIS PHONE NOTICES EXPORT

---

## TABLE OF CONTENTS

- [Source Metadata](#source-metadata)
- [Field Definitions](#field-definitions)
- [Sample Data](#sample-data)
- [Lookup Tables](#lookup-tables)
  - [NoticeType (Field 3)](#noticetype-field-3)
  - [NotificationLevel (Field 4)](#notificationlevel-field-4)
  - [LanguageID (Field 17)](#languageid-field-17)
  - [NotificationTypeID (Field 18)](#notificationtypeid-field-18)
  - [DeliveryOptionID (Field 19)](#deliveryoptionid-field-19)
- [Reference Lookup Tables](#reference-lookup-tables)
  - [Polaris.Polaris.Languages](#polarispolarislanguages-complete-table)
  - [Polaris.Polaris.NotificationTypes](#polarispolarisnotificationtypes-complete-table)
  - [Polaris.Polaris.NotificationStatuses](#polarispolarisnotificationstatuses-for-api-confirmations)
- [Database Schema](#database-schema)
- [Known Quirks](#known-quirks)
- [Validation Rules](#validation-rules)
- [Processing Notes](#processing-notes)
- [Validation Queries](#validation-queries)
- [Future Enhancement: Polaris API Integration](#future-enhancement-polaris-api-integration)
- [Summary](#summary)
- [Related Documentation](#related-documentation)

---

## SOURCE METADATA

**Source Name:** Polaris Phone Notices Export (Native Polaris Export)

**Source Type:** Polaris Internal Export Process (automated)

**Frequency:** Daily (exact timing controlled by Polaris internal scheduler)

**File Format:** Comma-delimited CSV file with quoted text fields

**File Naming Pattern:** `PhoneNotices.csv` (consistent name)

**Location:** 
- **Export destination:** Local FTP server `${LOCAL_FTP_HOST}` (configured via environment variable)
- **Upload location:** Root directory `/PhoneNotices.csv`
- **Export timing:** Daily at approximately 8:04-8:05 AM (4-5 minutes after 8:00 AM)
- **Export method:** Automated by Polaris internal export process
- **NOT uploaded to ShoutBomb FTP** - This is for validation and supplemental data only
- **FTP Configuration:** See [Configuration Requirements](SHOUTBOMB_DOCUMENTATION_INDEX.md#configuration-requirements) for setup details
- **System Architecture:** See [Archive and Backup System](SHOUTBOMB_DOCUMENTATION_INDEX.md#archive-and-backup-system) for FTP server details

**Purpose:** Polaris' native export format for phone/text notifications. **NOT sent to ShoutBomb.** Instead, this file is available on the local FTP server for a future notification monitoring app to:

1. **Validate ShoutBomb Submissions:**
   - Verify correct notifications were sent
   - Verify correct delivery methods (voice vs. text)
   - Verify data accuracy (barcodes, dates, patron info)
   - Flag missing or mismatched notifications

2. **Enrich Notification Data:**
   - Add patron names (NameFirst, NameLast)
   - Add email addresses
   - Add site/branch information
   - Add language preferences
   - Add notification levels (1st/2nd/3rd overdue)
   - Add pickup area descriptions (holds)
   - Add account balances (fines/bills)
   - Create fuller picture of each notification

3. **Provide Audit Trail:**
   - Complete notification lifecycle tracking
   - Historical record of what Polaris queued
   - Cross-reference with ShoutBomb delivery reports

**Integration Point:** Available at `ftp://${LOCAL_FTP_HOST}/PhoneNotices.csv` (updated daily ~8:04 AM)

**Usage Context:** DCPL's custom SQL extracts (holds.txt, overdue.txt, renew.txt) are what actually get sent to ShoutBomb for delivery. This PhoneNotices.csv file is the validation and enrichment source for monitoring.

**Related Custom Exports:** See `SHOUTBOMB_HOLDS_EXPORT.md`, `SHOUTBOMB_OVERDUE_EXPORT.md`, `SHOUTBOMB_RENEW_EXPORT.md`

---

## FIELD DEFINITIONS

> Fields appear in EXACT order shown below. **HAS HEADER ROW** (unlike ShoutBomb custom exports).

| Field # | Field Name | Data Type | Nullable? | Format/Constraints | Description |
|---------|------------|-----------|-----------|-------------------|-------------|
| 1 | DeliveryMethod | CHAR(1) | NO | 'V' or 'T' | V = Voice notification, T = Text notification |
| 2 | Language | VARCHAR(3) | NO | ISO 639-2/T code | Language code (typically 'eng' for English) |
| 3 | NoticeType | INT | NO | 1-4 | Type of notice for i-tiva: 1=All overdues, 2=Holds, 3=Hold Cancel, 4=Fines/Bills |
| 4 | NotificationLevel | INT | NO | 1-3 | Overdue notification status: 1=Default, 2=Second overdue, 3=Third overdue |
| 5 | PatronBarcode | VARCHAR(20) | NO | Varies | Library patron barcode (primary patron identifier) |
| 6 | PatronTitle | VARCHAR(50) | YES | Often blank | Patron's title (Mr., Mrs., Dr., etc.) |
| 7 | NameFirst | VARCHAR(50) | NO | Case-insensitive | Patron first name (case varies by staff data entry) |
| 8 | NameLast | VARCHAR(50) | NO | Case-insensitive | Patron last name (case varies by staff data entry) |
| 9 | PhoneNumber | VARCHAR(15) | YES | May contain formatting | Patron phone number (may have dashes/spaces/parens/periods - standardize to 10 digits for ShoutBomb comparison) |
| 10 | EmailAddress | VARCHAR(255) | YES | Email format or blank | Patron email address (may be empty) |
| 11 | SiteCode | VARCHAR(10) | NO | Typically 'DCPL' | Abbreviation of the reporting (sending) branch (from Organizations.Abbreviation) |
| 12 | SiteName | VARCHAR(255) | NO | Full name | Name of the reporting (sending) branch (e.g., "Daviess County Public Library") |
| 13 | ItemBarcode | VARCHAR(20) | NO | Varies | Barcode of item related to notification |
| 14 | DueDate | VARCHAR(10) | NO | MM/DD/YYYY | Due date for overdues/renewals, or held date for holds (convert to YYYY-MM-DD for ShoutBomb comparison) |
| 15 | BrowseTitle | VARCHAR(255) | NO | Free text | Bibliographic title of item (browse/display title) |
| 16 | ReportingOrgID | INT | NO | Typically 3 | Database identifier for the reporting (sending) branch (matches BranchID) |
| 17 | LanguageID | INT | NO | Numeric | Polaris language ID (1033 = English, see lookup table below) |
| 18 | NotificationTypeID | INT | NO | Numeric | Type of notification (1=1st Overdue, 2=Hold, 8=Fine, 11=Bill, 12=2nd Overdue, 13=3rd Overdue, etc. - see lookup table below) |
| 19 | DeliveryOptionID | INT | NO | 1-8 | Notification delivery method (3=Phone1, 8=TXT Messaging - see lookup table below) |
| 20 | PatronID | INT | NO | Numeric | Polaris internal patron identifier |
| 21 | ItemRecordID | INT | YES | Numeric | Polaris internal item record identifier (appears on holds, overdues, bills) |
| 22 | SysHoldRequestID | INT | YES | Numeric | Polaris hold request identifier (positive for holds/cancels, negative for ILL, 0 for non-hold notifications) |
| 23 | PickupAreaDescription | VARCHAR(255) | YES | Text or blank | Description of pickup area in library (conditional: appears on hold pickup notices when configured) |
| 24 | TxnID | INT | YES | Numeric | Patron account transaction identifier (conditional: appears on manual bill notices only) |
| 25 | AccountBalance | DECIMAL | YES | Currency format | Patron's balance owed (conditional: appears on fines, bills, and manual bills) |

**Total Field Count:** 25

**Header Row:** YES - First row contains field names (unlike ShoutBomb custom exports)

**Conditional Fields:** Fields 23-25 only appear under certain conditions:
- **PickupAreaDescription (Field 23):** Only appears on first and second hold pickup notices when pickup area is configured
- **TxnID (Field 24):** Only appears on manual bill notices
- **AccountBalance (Field 25):** Only appears on fines, bills, and manual bills

When these fields don't apply to a notification type, they may be absent or contain blank/null values.

**Delimiter:** Comma (`,`)

**Text Qualifier:** Double quotes (`"`)

**Encoding:** UTF-8 with BOM (﻿ at start of file)

**Line Endings:** Windows (CRLF - `\r\n`)

---

## SAMPLE DATA

```csv
delivery_method,language,notice_type,notification_level,patron_barcode,patron_title,name_first,name_last,phone_number,email_address,site_code,site_name,item_barcode,due_date,browse_title,reporting_org_id,language_id,notification_type_id,delivery_option_id,patron_id,item_record_id,sys_hold_request_id,pickup_area_description,txn_id,account_balance
"V","eng","2","1","23307000001234"," ","JANE","SMITH","5551234567","jsmith@example.com","DCPL","Daviess County Public Library","33307000012345","11/10/2025","Example Book Title","3","1033","2","3","100001","800001","880001"," "," "," "
"T","eng","2","1","23307000005678"," ","JOHN","DOE","5559876543"," ","DCPL","Daviess County Public Library","33307000067890","11/11/2025","Another Book Title","3","1033","2","8","100002","800002","880002"," "," "," "
```

**Note:** 
- Field 3 (NoticeType): "2" = Holds
- Field 4 (NotificationLevel): "1" = Default value
- Field 16 (ReportingOrgID): "3" = DCPL main branch
- Field 18 (NotificationTypeID): "2" = Hold notification
- Field 19 (DeliveryOptionID): "3" for Voice (row 1), "8" for Text (row 2)

---

## LOOKUP TABLES

### NoticeType (Field 3)

| Value | Notice Type | Description |
|-------|-------------|-------------|
| 1 | All overdues | All overdue-related notices |
| 2 | Holds | Hold ready for pickup |
| 3 | Hold Cancel | Hold cancellation notice |
| 4 | Fines/Bills | Fine or bill notice |

**Note:** This field is for i-tiva compatibility and may differ from NotificationTypeID (Field 18).

### NotificationLevel (Field 4)

| Value | Level | Description |
|-------|-------|-------------|
| 1 | Default | First/default notification level |
| 2 | Second overdue | Second overdue notice |
| 3 | Third overdue | Third overdue notice |

**Correlation with NotificationTypeID:**
- When NotificationTypeID = 12 (2nd Overdue) → NotificationLevel = 2
- When NotificationTypeID = 13 (3rd Overdue) → NotificationLevel = 3
- For other notification types → NotificationLevel = 1

### LanguageID (Field 17)

| ID | Language | Notes |
|----|----------|-------|
| 1033 | English | Most common |
| 3082 | Spanish | |
| 2052 | Chinese | |
| 1042 | Korean | |
| 1045 | Polish | |
| 1049 | Russian | |
| 1065 | Farsi - Persian | |
| 1066 | Vietnamese | |
| 1081 | Hindi | |
| 1107 | Khmer | |
| 1141 | Hawaiian | |
| 12289 | Arabic | |
| 15372 | Haitian Creole | |

**Note:** These are Windows LCID (Locale ID) values. For complete language mappings including 44 languages, see the **Polaris.Polaris.Languages** table in the Reference Lookup Tables section below.

### NotificationTypeID (Field 18)

| ID | Type | Description | Exportable? |
|----|------|-------------|-------------|
| 0 | Combined | Combined notification types | No |
| 1 | 1st Overdue | First overdue notice | Yes |
| 2 | Hold | Hold ready for pickup | Yes |
| 3 | Cancel | Cancellation notice | Yes |
| 4 | Recall | Item recall notice | No |
| 5 | All | All notification types | No |
| 6 | Route | Routing notice | No |
| 7 | Almost overdue/Auto-renew reminder | Courtesy/pre-overdue notice | No |
| 8 | Fine | Fine/fee notice | Yes |
| 9 | Inactive Reminder | Inactive account reminder | No |
| 10 | Expiration Reminder | Card expiration reminder | No |
| 11 | Bill | Billing notice | Yes |
| 12 | 2nd Overdue | Second overdue notice | Yes |
| 13 | 3rd Overdue | Third overdue notice | Yes |
| 14 | Serial Claim | Serial publication claim | No |
| 15 | Polaris Fusion Access Request | Fusion access request response | No |
| 16 | Course Reserves | Course reserves notice | No |
| 17 | Borrow-By-Mail Failure | Borrow-by-mail failure notice | No |
| 18 | 2nd Hold | Second hold notice | Yes |
| 19 | Missing Part | Missing part notice | No |
| 20 | Manual Bill | Manual billing notice | Yes |
| 21 | 2nd Fine Notice | Second fine notice | Yes |

**Most common for ShoutBomb exports:**
- Type 2 (Hold) - Item ready for pickup
- Types 1, 12, 13 (Overdue progression)
- Type 7 (Renewal reminder) - Note: Not exportable in standard Polaris export
- Type 8, 11 (Fines/Bills)

### DeliveryOptionID (Field 19)

| ID | Delivery Option | Description | Exportable? |
|----|-----------------|-------------|-------------|
| 1 | Mailing Address | Physical mail notification | Yes |
| 2 | Email Address | Email notification | Yes |
| 3 | Phone 1 | Primary phone number (voice) | Yes |
| 4 | Phone 2 | Secondary phone number (voice) | Yes |
| 5 | Phone 3 | Tertiary phone number (voice) | Yes |
| 6 | FAX | Fax notification | No |
| 7 | EDI | Electronic Data Interchange | No |
| 8 | TXT Messaging | SMS/Text message notification | Yes |

**For ShoutBomb:** Only DeliveryOptionID 3 (Phone 1) and 8 (TXT Messaging) are actively used.

**Critical Correlation:** Field 1 (DeliveryMethod) and Field 19 (DeliveryOptionID) must match:
- 'V' (Voice) → DeliveryOptionID = 3 (or 4 or 5)
- 'T' (Text) → DeliveryOptionID = 8

---

## REFERENCE LOOKUP TABLES

### Polaris.Polaris.Languages (Complete Table)

**Source:** Polaris.Polaris.Languages table  
**Purpose:** Maps LanguageID (Field 17) to language descriptions and admin language IDs

| LanguageID | LanguageDesc | NcipValue | AdminLanguageID | Notes |
|------------|--------------|-----------|-----------------|-------|
| 1 | English | eng | 1033 | Default |
| 2 | Spanish | spa | 3082 | |
| 3 | German | ger | 1031 | |
| 4 | French | fre | 3084 | |
| 5 | Italian | ita | | |
| 6 | Hebrew | heb | | |
| 7 | Hungarian | hun | | |
| 8 | Chinese | chi | 2052 | |
| 9 | Polish | pol | 1045 | |
| 10 | Korean | kor | 1042 | |
| 11 | Japanese | jpn | | |
| 12 | Arabic | ara | 12289 | |
| 13 | Greek | gre | | |
| 14 | Romanian | rum | | |
| 15 | Portuguese | por | | |
| 16 | Vietnamese | vie | 1066 | |
| 17 | Russian | rus | 1049 | |
| 18 | Albanian | alb | | |
| 19 | Armenian | arm | | |
| 20 | Bengali | ben | | |
| 21 | Bosnian | bos | | |
| 22 | Bulgarian | bul | | |
| 23 | Czech | cze | | |
| 24 | Dutch | dut | 1043 | |
| 25 | Gujarati | guj | | |
| 26 | Hindi | hin | 1081 | |
| 27 | Kannada | kan | | |
| 28 | Marathi | mar | | |
| 29 | Persian | per | 1065 | |
| 30 | Panjabi | pan | | |
| 31 | Serbian | srp | | |
| 32 | Tamil | tam | | |
| 33 | Telugu | tel | | |
| 34 | Thai | tha | | |
| 35 | Urdu | urd | | |
| 36 | Hawaiian | haw | 1141 | |
| 37 | Haitian Creole | hat | 15372 | |
| 38 | Malay | may | | |
| 39 | Somali | som | | |
| 40 | Tagalog | tag | | |
| 41 | Filipino | fil | | |
| 42 | Khmer | khm | 1107 | |
| 43 | Other | qqq | | |
| 44 | Catalan | cat | 1027 | |

**Note:** AdminLanguageID maps to Windows LCID values used in the PhoneNotices export (Field 17).

### Polaris.Polaris.NotificationTypes (Complete Table)

**Source:** Polaris.Polaris.NotificationTypes table  
**Purpose:** Maps NotificationTypeID (Field 18) to notification type descriptions

| NotificationTypeID | Description | Exportable in PhoneNotices? | Used by ShoutBomb? |
|--------------------|-------------|---------------------------|-------------------|
| 0 | Combined | No | No |
| 1 | 1st Overdue | Yes | Yes |
| 2 | Hold | Yes | Yes |
| 3 | Cancel | Yes | No |
| 4 | Recall | No | No |
| 5 | All | No | No |
| 6 | Route | No | No |
| 7 | Almost overdue/Auto-renew reminder | No | Yes (custom export) |
| 8 | Fine | Yes | Yes |
| 9 | Inactive Reminder | No | No |
| 10 | Expiration Reminder | No | No |
| 11 | Bill | Yes | Yes |
| 12 | 2nd Overdue | Yes | Yes |
| 13 | 3rd Overdue | Yes | Yes |
| 14 | Serial Claim | No | No |
| 15 | Polaris Fusion Access Request Responses | No | No |
| 16 | Course Reserves | No | No |
| 17 | Borrow-By-Mail Failure Notice | No | No |
| 18 | 2nd Hold | Yes | No |
| 19 | Missing Part | No | No |
| 20 | Manual Bill | Yes | No |
| 21 | 2nd Fine Notice | Yes | No |

**Notes:**
- "Exportable in PhoneNotices?" indicates whether Polaris can export this type in the native phone notices export
- "Used by ShoutBomb?" indicates whether DCPL's custom SQL exports include this type
- Type 7 is not exportable via standard Polaris export but is handled by custom SQL for renewal reminders

### Polaris.Polaris.NotificationStatuses (For API Confirmations)

**Source:** Polaris API documentation  
**Purpose:** Status codes for use with NotificationUpdatePut API endpoint when confirming deliveries

| NotificationStatusID | Status | Description | Use Case |
|---------------------|--------|-------------|----------|
| 1 | Call completed - Voice | Successfully spoke with person | Voice delivery to answering person |
| 2 | Call completed - Answering machine | Left message on voicemail | Voice delivery to voicemail |
| 3 | Call not completed - Hang up | Call was hung up | Voice failure |
| 4 | Call not completed - Busy | Line was busy | Voice failure |
| 5 | Call not completed - No answer | No one answered the call | Voice failure |
| 6 | Call not completed - No ring | Phone did not ring | Voice failure |
| 7 | Call failed - No dial tone | No dial tone detected | Voice failure |
| 8 | Call failed - Intercept tones heard | Intercept message detected | Voice failure |
| 9 | Call failed - Probable bad phone number | Invalid phone number | Voice failure |
| 10 | Call failed - Maximum retries exceeded | Too many retry attempts | Voice failure |
| 11 | Call failed - Undetermined error | Unknown error occurred | Voice failure |
| 12 | Email Completed | Email successfully sent | Email delivery success |
| 13 | Email Failed - Invalid address | Email address is invalid | Email failure |
| 14 | Email Failed | Email failed to send | Email failure |
| 15 | Mail Printed | Physical mail notice printed | Print delivery |
| 16 | Sent | Generic sent status | Default success status |

**For ShoutBomb Integration:**
- Use Status 16 (Sent) as default for successful text message deliveries
- Use Status 1 or 2 for voice calls (if ShoutBomb provides delivery detail)
- Use Status 12 for email notifications (if applicable)

**Critical for 3rd Overdue Confirmation Gap:**
This table is essential for the future enhancement to confirm 3rd overdue deliveries back to Polaris via the NotificationUpdatePut API endpoint.

**Note:** Position 16 is PickupBranchID, Position 19 is DeliveryOptionID showing:
- "3" for Voice notifications (DeliveryMethod = "V")
- "8" for Text notifications (DeliveryMethod = "T")

---

## CROSS-REFERENCE KEYS

**This data source links to other sources via:**

| Field in THIS Source | Links to Source | Field in OTHER Source | Relationship |
|---------------------|-----------------|----------------------|--------------|
| PatronBarcode | Polaris Patron Master | Barcode | 1:1 - Identifies patron |
| PatronID | All Polaris tables | PatronID | 1:1 - Internal patron key |
| ItemRecordID | Polaris Item Master | ItemRecordID | 1:1 - Identifies specific item |
| ItemBarcode | Polaris Circulation | Barcode | 1:1 - Item barcode |
| SysHoldRequestID | Polaris Hold Requests | SysHoldRequestID | 1:1 - Identifies hold (0 if not hold) |
| PhoneNumber | ShoutBomb Patron Lists | PhoneVoice1 | Many:1 - Phone lookup |
| NotificationTypeID | NotificationTypes table | NotificationTypeID | Many:1 - Type lookup |
| DeliveryOptionID | DeliveryOptions table | DeliveryOptionID | Many:1 - Method lookup |

**Key Cross-Validation Opportunities:**

1. **Compare with ShoutBomb Holds Export:**
   - Match by PatronID + ItemRecordID + SysHoldRequestID
   - Verify all holds in custom export appear here
   - Check for holds in PhoneNotices NOT in custom export (missed notifications)

2. **Compare with ShoutBomb Overdue Export:**
   - Match by PatronID + ItemRecordID
   - Filter PhoneNotices where NotificationTypeID IN (1,7,8,11,12,13)
   - Verify all overdues captured

3. **Compare with ShoutBomb Renewal Export:**
   - Match by PatronID + ItemRecordID
   - Filter PhoneNotices where NotificationTypeID = 7 (pre-due)
   - Verify all renewal reminders captured

4. **Compare Patron Lists:**
   - Match PhoneNumber + PatronBarcode combinations
   - Verify DeliveryOptionID matches delivery method (3=voice, 8=text)
   - Detect mismatches in patron delivery preferences

---

## DATA INSIGHTS & PATTERNS

### **Delivery Method Correlation**

**Critical Pattern:**
- Field 1 (DeliveryMethod) and Field 19 (DeliveryOptionID) must correlate:
  - 'V' (Voice) → DeliveryOptionID = 3, 4, or 5 (Phone 1/2/3)
  - 'T' (Text) → DeliveryOptionID = 8 (TXT Messaging)
- If mismatch detected → **DATA INTEGRITY ERROR**
- For DCPL: Only DeliveryOptionID 3 (Phone 1) and 8 (TXT Messaging) are used

### **Notification Type Patterns**

**Field 3 (NoticeType)** - i-tiva compatibility field:
- **Type 1:** All overdues
- **Type 2:** Holds (ready for pickup)
- **Type 3:** Hold Cancel
- **Type 4:** Fines/Bills

**Field 18 (NotificationTypeID)** - Full Polaris notification type:
- **Type 2:** Hold notifications (ready for pickup)
- **Type 7:** Pre-due / Almost overdue / Renewal reminders (not exportable in standard format)
- **Type 1:** 1st Overdue notice
- **Type 12:** 2nd Overdue notice
- **Type 13:** 3rd Overdue notice
- **Type 8:** Fine notices
- **Type 11:** Bill notices
- **Type 18:** 2nd Hold reminder
- **Type 20:** Manual Bill

**Field 4 (NotificationLevel)** - Overdue progression:
- **Level 1:** Default/First notification
- **Level 2:** Second overdue (correlates with NotificationTypeID 12)
- **Level 3:** Third overdue (correlates with NotificationTypeID 13)

### **NotificationLevel and NotificationTypeID Correlation**

The relationship between Field 4 (NotificationLevel) and Field 18 (NotificationTypeID):
- NotificationTypeID = 1 (1st Overdue) → NotificationLevel = 1
- NotificationTypeID = 12 (2nd Overdue) → NotificationLevel = 2
- NotificationTypeID = 13 (3rd Overdue) → NotificationLevel = 3
- All other notification types → NotificationLevel = 1

### **SysHoldRequestID Usage (Field 22)**

- **Positive integers:** First and second hold pickup notices, and hold cancel notices
- **Negative integers:** ILL (Interlibrary Loan) request notices  
- **Value = 0 or NULL:** Notification is overdue, renewal reminder, fine, bill, or other non-hold type

### **ItemRecordID Usage (Field 21)**

- **Present (non-null):** Appears on first and second hold notices, overdues, and bills
- **Null/Absent:** May be null on certain notification types that don't relate to a specific item

### **Conditional Fields (23-25)**

**PickupAreaDescription (Field 23):**
- Only populated on first and second hold pickup notices
- Only when library has configured pickup areas
- Otherwise blank/null

**TxnID (Field 24):**
- Only populated on manual bill notices (NotificationTypeID = 20)
- Database identifier for the patron account transaction
- Otherwise blank/null

**AccountBalance (Field 25):**
- Only populated on fines (Type 8), bills (Type 11), and manual bills (Type 20)
- Decimal value showing patron's current balance owed
- Otherwise blank/null

### **Date Format Difference**

**IMPORTANT:** DueDate (Field 14) uses MM/DD/YYYY format (American format)
- Example: "11/12/2025"
- **Different from ShoutBomb custom exports** which use YYYY-MM-DD (ISO format)
- **Transformation required** when importing to match other date fields
- **Strip time component** from ShoutBomb datetime fields when comparing (HH:MM:SS or HH:MM:SS.mmm milliseconds)

**Field Purpose by Notification Type:**
- **Overdues/Renewals:** DueDate = actual item due date
- **Holds:** DueDate = date held/available (when item became ready for pickup)
- When importing, map this field according to notification purpose

### **Name Field Characteristics**

- **Field 6 (PatronTitle):** Often blank - contains titles like Mr., Mrs., Dr. when populated
- **Fields 7-8 (NameFirst, NameLast):** Case-insensitive
  - May appear in UPPERCASE, Title Case, or lowercase depending on staff data entry
  - Do NOT assume uppercase - normalize as needed for display or matching
- Some names may have unusual formatting (truncated, special characters, etc.)

### **Phone Number Format Variability**

**CRITICAL:** Field 9 (PhoneNumber) may contain various formats:
- **May include:** Dashes, spaces, parentheses, periods, other formatting characters
- **May be:** NULL/blank (not all patrons have phone numbers)
- **Examples of possible formats:**
  - `5551234567` (standard 10 digits)
  - `555-123-4567` (dashed)
  - `(555) 123-4567` (formatted)
  - `555.123.4567` (dotted)
  
**Standardization Required:**
- When comparing to ShoutBomb patron lists (voice_patrons.txt, text_patrons.txt), **must standardize to 10 digits**
- Strip all non-numeric characters before comparison
- Validate 10-digit length after standardization

### **Duplicate Patron Notifications**

- Same patron (PatronBarcode) can appear multiple times in one export
- Reason: Multiple items triggering notifications (multiple holds, multiple overdues)
- Each row = one notification for one item
- Shoutbomb batches these into single notification per patron

---

## VALIDATION RULES

### **Record Count Expectations**

**Daily Volume Estimates:**
- Holds: 50-300 notifications daily (distributed across 4 Shoutbomb exports)
- Overdues: Variable based on circulation (100-500 daily)
- Renewals: 50-200 daily

**Alerts:**
- Zero rows when ShoutBomb exports show notifications → **EXPORT FAILURE**
- Significantly more rows than ShoutBomb exports → **MISSED NOTIFICATIONS**
- Significantly fewer rows than ShoutBomb exports → **FALSE POSITIVES**

### **Data Integrity Checks**

**Required Field Validation:**
- [ ] All PatronBarcode values must exist in Polaris.Polaris.Patrons table
- [ ] All ItemBarcode values must exist in Polaris circulation (when present)
- [ ] All PatronID values must exist in Polaris.Polaris.Patrons table
- [ ] All ItemRecordID values must exist in Polaris.Polaris.CircItemRecords table (when present - nullable)
- [ ] PhoneNumber must be valid format OR null/blank (various formats allowed - standardize for comparison)
- [ ] EmailAddress must be valid email format OR blank
- [ ] DueDate must be valid date in MM/DD/YYYY format
- [ ] DeliveryMethod must be 'V' or 'T' only
- [ ] NoticeType must be 1, 2, 3, or 4
- [ ] NotificationLevel must be 1, 2, or 3
- [ ] NotificationTypeID must be valid Polaris notification type (see lookup table)
- [ ] DeliveryOptionID must be 1-8 (see lookup table)
- [ ] ReportingOrgID must be valid branch (typically 3 for DCPL)
- [ ] LanguageID must be valid Polaris language ID (see lookup table)

**Business Logic Validation:**
- [ ] DeliveryMethod 'V' must have DeliveryOptionID = 3, 4, or 5 (Phone 1/2/3)
- [ ] DeliveryMethod 'T' must have DeliveryOptionID = 8 (TXT Messaging)
- [ ] SysHoldRequestID > 0 implies NotificationTypeID = 2, 3, or 18 (hold-related types)
- [ ] If NotificationTypeID = 2 or 18 (holds), then SysHoldRequestID should be > 0
- [ ] PhoneNumber must match patron record for given PatronID (when present)
- [ ] PatronBarcode must match patron record for given PatronID
- [ ] NotificationLevel should correlate with NotificationTypeID:
  - NotificationTypeID 1 (1st Overdue) → NotificationLevel 1
  - NotificationTypeID 12 (2nd Overdue) → NotificationLevel 2
  - NotificationTypeID 13 (3rd Overdue) → NotificationLevel 3
  - All other types → NotificationLevel 1
- [ ] Conditional fields populated appropriately:
  - PickupAreaDescription only on hold notices (when configured)
  - TxnID only on manual bill notices (Type 20)
  - AccountBalance only on fines/bills (Types 8, 11, 20)

**Cross-System Validation:**
- [ ] Every notification in PhoneNotices should appear in corresponding ShoutBomb export
- [ ] Every notification in ShoutBomb export should appear in PhoneNotices (within date range)
- [ ] DeliveryOptionID in PhoneNotices matches patron's preference in patron lists
- [ ] Phone numbers match between PhoneNotices and voice_patrons or text_patrons lists

---

## PROCESSING NOTES

### **Import Strategy**

**Recommended Approach:**
1. Download file from local FTP server (ftp://${LOCAL_FTP_HOST}/PhoneNotices.csv) - uploaded daily ~8:04-8:05 AM
2. Import daily file with timestamp
3. Parse DueDate field and convert to ISO format (YYYY-MM-DD) for consistency
4. Cross-reference with ShoutBomb exports from same day
5. Flag discrepancies for review
6. Store as supplemental data in notification tracking table

**DO NOT use this file to trigger actual notifications** - ShoutBomb custom exports serve that purpose.

### **Transformations Needed**

1. **Date Conversion:**
   - Field: DueDate (Field 14)
   - Input: "11/12/2025" (MM/DD/YYYY)
   - Output: "2025-11-12" (YYYY-MM-DD)
   - Use for consistency with ShoutBomb date fields

2. **Phone Number Standardization:**
   - Field: PhoneNumber (Field 9)
   - Input: May contain dashes, spaces, parentheses, periods
   - Examples: "555-123-4567", "(555) 123-4567", "555.123.4567"
   - Output: "5551234567" (10 digits only)
   - **CRITICAL:** Strip all non-numeric characters before comparison with ShoutBomb patron lists
   - Validate 10-digit length after standardization

3. **Name Case Normalization (optional):**
   - Fields: NameFirst (Field 7), NameLast (Field 8)
   - Input: May be UPPERCASE, Title Case, or lowercase (depends on staff data entry)
   - Output: Normalize to Title Case for display consistency
   - Keep original for exact matching purposes if needed

4. **Email Cleanup:**
   - Field: EmailAddress (Field 10)
   - Trim whitespace
   - Validate email format
   - Flag invalid emails
   - Flag invalid emails

### **Dependencies**

**Process AFTER:**
- Polaris patron master updates
- Polaris item master updates
- Polaris circulation updates

**Process WITH:**
- ShoutBomb holds export (for cross-validation)
- ShoutBomb overdue export (for cross-validation)
- ShoutBomb renewal export (for cross-validation)
- Voice and text patron lists (for patron preference validation)

### **Key Use Cases**

1. **Validation Dashboard:**
   - Count notifications by type in PhoneNotices
   - Compare counts to ShoutBomb export counts
   - Alert on discrepancies > 5%

2. **Missing Notification Detection:**
   - Identify notifications in PhoneNotices NOT in ShoutBomb exports
   - Root cause: SQL query filters, timing issues, data corruption
   - Trigger investigation

3. **False Positive Detection:**
   - Identify notifications in ShoutBomb exports NOT in PhoneNotices
   - Root cause: Custom query logic errors
   - Trigger SQL query review

4. **Patron Name Enrichment:**
   - Use FirstName/LastName fields to populate patron display names
   - ShoutBomb exports only include patron barcodes
   - Improves notification logging readability

5. **Email Address Capture:**
   - Store email addresses for future multi-channel notification strategy
   - Currently not used for notifications but valuable for reporting

6. **Notification Lifecycle Tracking:**
   - Combine with ShoutBomb delivery reports
   - Track: Queued (PhoneNotices) → Exported (ShoutBomb) → Sent (ShoutBomb report) → Delivered/Failed (ShoutBomb report)

---

## KNOWN ISSUES & LIMITATIONS

### **Timing Discrepancies**

**Issue:** Polaris PhoneNotices export timing may not align perfectly with ShoutBomb custom export timing.

**Example:**
- PhoneNotices exports at 6:00am
- ShoutBomb holds export at 8:00am
- Holds that become ready between 6:00am-8:00am appear in ShoutBomb but NOT in PhoneNotices

**Mitigation:**
- Use DateSent field to filter appropriate date range
- Allow for +/- 1 day window when cross-validating
- Focus on pattern detection, not exact row-by-row matching

### **Unknown Fields**

**Field 4 (Unknown_Field):** Always value of '1'
- Purpose unknown
- May be notification queue priority
- May be notification attempt counter
- Requires Polaris documentation or support inquiry

**Field 18 (Unknown_AlwaysTwo):** Always value of '2'
- Purpose unknown
- May relate to notification status
- May be notification generation method indicator
- Requires Polaris documentation or support inquiry

### **Middle Name Field Unused**

- Field 6 (MiddleName) is almost always blank/space
- Polaris patron records may have middle names stored elsewhere
- This export field appears to be reserved but not populated
- Not a data issue, just export design choice

### **No Confirmation Status**

**Critical Limitation:** This file represents notifications **queued/generated** by Polaris, NOT notifications **successfully delivered**.

- Does not indicate if ShoutBomb received the notification
- Does not indicate if patron received voice call or text
- Does not indicate if notification failed
- Must combine with ShoutBomb delivery reports for complete picture

### **No Batch Information**

- File shows individual item notifications
- Does not indicate how ShoutBomb will batch multiple items for same patron
- Cannot determine final message patron will receive from this file alone

---

## ENRICHMENT OPPORTUNITIES

Importing PhoneNotices data into your notification tracking system enables:

### **1. Patron Name Display**
**Current State:** ShoutBomb exports only include PatronBarcode
**Enhancement:** Join with PhoneNotices to display "JOHN SMITH" instead of "23307001234567"
**Tables:** Notification logs, reports, dashboards

### **2. Full Notification Context**
**Current State:** ShoutBomb exports optimized for delivery (minimal fields)
**Enhancement:** Store complete context (patron name, email, dates, IDs)
**Value:** Better audit trail, troubleshooting, reporting

### **3. Email Address Capture**
**Current State:** Email addresses not tracked in ShoutBomb workflow
**Enhancement:** Store for future multi-channel notification strategy
**Value:** Enables future email notification fallback or supplemental notifications

### **4. Validation Metrics**
**Current State:** Unknown if custom SQL captures all notifications
**Enhancement:** Daily comparison reports showing coverage percentage
**Value:** Confidence in notification delivery, early error detection

### **5. Historical Trend Analysis**
**Current State:** Limited ability to analyze notification patterns over time
**Enhancement:** Rich dataset with dates, types, patrons, items for analytics
**Value:** Understand circulation patterns, patron behavior, notification effectiveness

### **6. Pickup Branch Insights**
**Current State:** ShoutBomb exports don't clearly indicate pickup branch
**Enhancement:** PickupBranchID field (position 16) provides branch-level analysis
**Value:** Branch-specific notification statistics and performance

---

## RECOMMENDED TABLE SCHEMA

**Suggested database table for importing PhoneNotices data:**

```sql
CREATE TABLE polaris_phone_notices (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- Import metadata
    import_date DATE NOT NULL,
    import_timestamp DATETIME NOT NULL,
    file_name VARCHAR(255),
    
    -- Polaris export data (corrected field names from Polaris documentation)
    delivery_method CHAR(1) NOT NULL,
    language VARCHAR(3) NOT NULL,
    notice_type INT NOT NULL,  -- Field 3: i-tiva compatibility (1-4)
    notification_level INT NOT NULL,  -- Field 4: Overdue status (1-3)
    patron_barcode VARCHAR(20) NOT NULL,
    patron_title VARCHAR(50),  -- Often blank
    name_first VARCHAR(50) NOT NULL,  -- Case-insensitive
    name_last VARCHAR(50) NOT NULL,  -- Case-insensitive
    phone_number VARCHAR(15),  -- May contain formatting, nullable
    email_address VARCHAR(255),
    site_code VARCHAR(10) NOT NULL,  -- Reporting branch abbreviation
    site_name VARCHAR(255) NOT NULL,  -- Reporting branch full name
    item_barcode VARCHAR(20) NOT NULL,
    due_date DATE NOT NULL,  -- Converted to DATE type
    due_date_original VARCHAR(10),  -- Store original MM/DD/YYYY format
    browse_title VARCHAR(255) NOT NULL,
    reporting_org_id INT NOT NULL,  -- Branch database ID
    language_id INT NOT NULL,  -- Polaris language identifier
    notification_type_id INT NOT NULL,  -- Field 18: Full notification type
    delivery_option_id INT NOT NULL,  -- Field 19: Delivery method ID
    patron_id INT NOT NULL,
    item_record_id INT,  -- Nullable - conditional
    sys_hold_request_id INT,  -- Nullable - conditional
    
    -- Conditional fields (may be null/absent)
    pickup_area_description VARCHAR(255),  -- Appears on hold notices when configured
    txn_id INT,  -- Appears on manual bill notices
    account_balance DECIMAL(10,2),  -- Appears on fines/bills
    
    -- Validation and tracking
    matched_to_shoutbomb BOOLEAN DEFAULT FALSE,
    shoutbomb_export_date DATE,
    validation_notes TEXT,
    
    -- Indexes for performance
    INDEX idx_due_date (due_date),
    INDEX idx_patron_id (patron_id),
    INDEX idx_patron_barcode (patron_barcode),
    INDEX idx_item_record_id (item_record_id),
    INDEX idx_notification_type_id (notification_type_id),
    INDEX idx_notice_type (notice_type),
    INDEX idx_delivery_method (delivery_method),
    INDEX idx_phone_number (phone_number),
    INDEX idx_sys_hold_request (sys_hold_request_id),
    INDEX idx_reporting_org (reporting_org_id),
    
    -- Composite indexes for common queries
    INDEX idx_patron_item (patron_id, item_record_id),
    INDEX idx_date_notification_type (due_date, notification_type_id),
    INDEX idx_validation (matched_to_shoutbomb, due_date)
);
```

---

## VALIDATION QUERIES

**Daily Validation Report - Compare PhoneNotices to ShoutBomb Exports:**

```sql
-- Holds validation: Are all PhoneNotices holds in ShoutBomb export?
SELECT 
    pn.due_date,
    pn.notification_type_id,
    COUNT(*) as polaris_count,
    SUM(CASE WHEN pn.matched_to_shoutbomb THEN 1 ELSE 0 END) as matched_count,
    COUNT(*) - SUM(CASE WHEN pn.matched_to_shoutbomb THEN 1 ELSE 0 END) as unmatched_count,
    ROUND((SUM(CASE WHEN pn.matched_to_shoutbomb THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as match_percentage
FROM polaris_phone_notices pn
WHERE pn.notification_type_id = 2  -- Holds
  AND pn.due_date = CURDATE()
GROUP BY pn.due_date, pn.notification_type_id;

-- Overdue validation: Are all PhoneNotices overdues in ShoutBomb export?
SELECT 
    pn.due_date,
    pn.notification_type_id,
    COUNT(*) as polaris_count,
    SUM(CASE WHEN pn.matched_to_shoutbomb THEN 1 ELSE 0 END) as matched_count
FROM polaris_phone_notices pn
WHERE pn.notification_type_id IN (1, 7, 8, 11, 12, 13)  -- Overdue types
  AND pn.due_date = CURDATE()
GROUP BY pn.due_date, pn.notification_type_id;

-- Delivery method breakdown
SELECT 
    pn.delivery_method,
    pn.delivery_option_id,
    COUNT(*) as notification_count,
    COUNT(DISTINCT pn.patron_id) as unique_patrons
FROM polaris_phone_notices pn
WHERE pn.due_date = CURDATE()
GROUP BY pn.delivery_method, pn.delivery_option_id;

-- Detect delivery method mismatches
SELECT 
    pn.patron_barcode,
    pn.name_first,
    pn.name_last,
    pn.phone_number,
    pn.delivery_method,
    pn.delivery_option_id,
    'Mismatch: Voice should be 3, 4, or 5' as error
FROM polaris_phone_notices pn
WHERE pn.delivery_method = 'V' 
  AND pn.delivery_option_id NOT IN (3, 4, 5)
UNION ALL
SELECT 
    pn.patron_barcode,
    pn.name_first,
    pn.name_last,
    pn.phone_number,
    pn.delivery_method,
    pn.delivery_option_id,
    'Mismatch: Text should be 8' as error
FROM polaris_phone_notices pn
WHERE pn.delivery_method = 'T' AND pn.delivery_option_id != 8;

-- NotificationLevel correlation validation
SELECT 
    pn.notification_type_id,
    pn.notification_level,
    COUNT(*) as count,
    'Unexpected correlation' as warning
FROM polaris_phone_notices pn
WHERE 
    (pn.notification_type_id = 12 AND pn.notification_level != 2)
    OR (pn.notification_type_id = 13 AND pn.notification_level != 3)
    OR (pn.notification_type_id = 1 AND pn.notification_level != 1)
GROUP BY pn.notification_type_id, pn.notification_level;
```

---

## FUTURE ENHANCEMENT: POLARIS API INTEGRATION

**Long-term Vision:** Replace manual PhoneNotices CSV import with Polaris API integration.

**Potential Benefits:**
- Real-time notification tracking (not just daily exports)
- Ability to mark notifications as confirmed in Polaris
- Solve the "3rd overdue confirmation gap" issue
- Two-way integration: Read notifications AND update status

**Related Project Knowledge:**
- See Polaris API documentation in project files
- PAPIClient Laravel package already created for Polaris API integration
- NotificationUpdatePut endpoint could enable confirmation feedback loop

**Implementation Priority:** Medium-term (after current ShoutBomb validation system stabilized)

---

## SUMMARY

The Polaris Phone Notices export is a **validation and enrichment data source for future notification monitoring applications**. It is NOT sent to ShoutBomb - instead, it's available on the local FTP server for app integration.

### Primary Value for Future App:

✅ **Validation** - Verify ShoutBomb received all correct notifications  
✅ **Delivery Method Verification** - Confirm voice vs. text preferences are correct  
✅ **Data Accuracy** - Cross-check barcodes, dates, and patron information  
✅ **Enrichment** - Add supplemental data (names, emails, balances, etc.) not in ShoutBomb exports  
✅ **Monitoring** - Track complete notification lifecycle from Polaris queue to delivery  
✅ **Exception Detection** - Flag missing notifications or data mismatches  

### App Integration:

**Download:** `ftp://${LOCAL_FTP_HOST}/PhoneNotices.csv` (updated daily ~8:04 AM)  
**Use:** Compare against ShoutBomb submission archives to validate and enrich notification data  
**Result:** Complete monitoring dashboard with validation results and enriched notification details  

For app integration details, see the [Future App Integration](SHOUTBOMB_DOCUMENTATION_INDEX.md#future-app-integration) section in the master documentation index.

---

## RELATED DOCUMENTATION

### App Integration
- **[SHOUTBOMB_DOCUMENTATION_INDEX.md#future-app-integration](SHOUTBOMB_DOCUMENTATION_INDEX.md#future-app-integration)** - Complete app integration guide

### ShoutBomb Export Documentation
- **[SHOUTBOMB_DOCUMENTATION_INDEX.md](SHOUTBOMB_DOCUMENTATION_INDEX.md)** - Master index of all ShoutBomb documentation
- **[SHOUTBOMB_VOICE_PATRONS.md](SHOUTBOMB_VOICE_PATRONS.md)** - Voice notification patron list
- **[SHOUTBOMB_TEXT_PATRONS.md](SHOUTBOMB_TEXT_PATRONS.md)** - Text notification patron list
- **[SHOUTBOMB_HOLDS_EXPORT.md](SHOUTBOMB_HOLDS_EXPORT.md)** - Hold ready notifications
- **[SHOUTBOMB_OVERDUE_EXPORT.md](SHOUTBOMB_OVERDUE_EXPORT.md)** - Overdue notifications
- **[SHOUTBOMB_RENEW_EXPORT.md](SHOUTBOMB_RENEW_EXPORT.md)** - Renewal reminders
- **[SHOUTBOMB_REPORTS_INCOMING.md](SHOUTBOMB_REPORTS_INCOMING.md)** - Failure reports from ShoutBomb

### API Integration Documentation
- **[Polaris_Notification_Guide_PAPIClient.md](Polaris_Notification_Guide_PAPIClient.md)** - Laravel PAPIClient package guide
- **[Polaris-API-swagger.json](Polaris-API-swagger.json)** - Complete Polaris API specification

### Templates
- **[DATA_SOURCE_TEMPLATE.md](DATA_SOURCE_TEMPLATE.md)** - Template for documenting new data sources

---

**Last Updated:** November 14, 2025  
**Document Version:** 3.0  
**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library
