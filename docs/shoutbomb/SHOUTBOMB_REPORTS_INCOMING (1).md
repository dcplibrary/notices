# DATA SOURCE: SHOUTBOMB REPORTS (INCOMING)

---

## TABLE OF CONTENTS

- [Source Metadata](#source-metadata)
- [Report Types Overview](#report-types-overview)
- [Report Type 1: Daily Invalid Phone Report](#report-type-1-daily-invalid-phone-report)
- [Report Type 2: Daily Voice Failure Report](#report-type-2-daily-voice-failure-report)
- [Report Type 3: Voice Call System Errors (Triggered)](#report-type-3-voice-call-system-errors-triggered)
- [Report Type 4: Monthly Statistics Report](#report-type-4-monthly-statistics-report)
- [Cross-Reference Usage](#cross-reference-usage)
- [Processing Strategies](#processing-strategies)
- [Validation Rules](#validation-rules)
- [Known Issues & Limitations](#known-issues--limitations)
- [Security & Privacy Considerations](#security--privacy-considerations)
- [Email Routing & Automation](#email-routing--automation)
- [Change Log](#change-log)
- [Contact / Support](#contact--support)
- [AI/LLM Instructions](#aillm-instructions)
- [Related Documentation](#related-documentation)

---

## SOURCE METADATA

**Source Name:** Shoutbomb Notification Reports (Multiple Types)

**Source Type:** Email reports (text body content, not structured files)

**Frequency:** Variable - Daily scheduled, monthly scheduled, and triggered alerts

**File Format:** Email body text (unstructured, must be parsed from email content)

**Delivery Method:** Email to configured recipients

**Purpose:** Provides failure reports, statistics, and system alerts from Shoutbomb notification service. These are incoming reports FROM Shoutbomb TO the library, unlike the exports which go FROM library TO Shoutbomb.

---

## REPORT TYPES OVERVIEW

Shoutbomb sends four distinct types of reports via email:

| Report Type | Frequency | Timing | Purpose |
|------------|-----------|--------|---------|
| Daily Invalid Phone Report | Daily | ~6:01am | Lists opt-outs and invalid phone numbers from previous day |
| Daily Voice Failure Report | Daily | ~4:10pm | Lists voice notifications that failed that morning |
| Voice Call System Errors | Triggered | Variable | Detailed technical failures after 3 retry attempts |
| Monthly Statistics Report | Monthly | ~1st of month, ~1:14pm | Comprehensive usage and failure statistics for previous month |

---

## REPORT TYPE 1: DAILY INVALID PHONE REPORT

### **Metadata**

**Subject Line:** `Invalid patron phone number [Day], [Month] [Date]th [Year]`

**Example:** `Invalid patron phone number Thu, November 13th 2025`

**From:** Shoutbomb

**To:** Library staff email addresses

**Sent:** Daily at approximately 6:01am

**Covers:** Previous day's failures (opt-outs and invalid numbers detected)

### **Content Structure**

Email contains two sections:

**Section 1: Opted-Out Phone Numbers**
```
Hello,

These patron phone numbers seem to have OPTED-OUT from SMS or MMS messages based on multiple
attempts to contact. Please verify and correct.

[Phone] :: [Barcode] :: [PatronID] :: [DeliveryOptionID] :: SMS
[Phone] :: [Barcode] :: [DeliveryOptionID]
[Phone] :: No associated barcode
```

**Section 2: Invalid Phone Numbers**
```
Hello,

These patron phone numbers seem to be invalid based on multiple attempts to contact. Please verify and
correct.

[Phone] :: [Barcode] :: [PatronID] :: [DeliveryOptionID] :: SMS
```

### **Field Formats**

**Standard Format (Patron Still Active):**
```
5551234567 :: 23307012345678 :: 98765 :: 3 :: SMS
```

| Position | Field | Format | Description |
|----------|-------|--------|-------------|
| 1 | Phone | 10 digits, no dashes | Patron phone number |
| 2 | Barcode | Varies | Patron barcode |
| 3 | PatronID | Numeric | Internal Polaris patron ID |
| 4 | DeliveryOptionID | 3 or 8 | 3=Phone1/voice, 8=TXT Messaging |
| 5 | SMS | Literal "SMS" | Indicates text message type |

**Deleted Patron Format (Branch ID Only):**
```
5559876543 :: 23307098765432 :: 3
```
When only `3` appears after barcode with nothing following, the `3` is the **Branch ID**, not PatronID. This typically indicates the patron has been deleted from Polaris but Shoutbomb still has their phone number.

**No Barcode Format:**
```
5552468135 :: No associated barcode
```
Phone number exists in Shoutbomb system but no barcode mapping found.

### **Sample Data (Fake)**

```
Subject: Invalid patron phone number Thu, November 13th 2025
Date: Thursday, November 13, 2025 at 6:01:00AM Central Standard Time
From: Shoutbomb
To: library@example.org

Hello,

These patron phone numbers seem to have OPTED-OUT from SMS or MMS messages based on multiple
attempts to contact. Please verify and correct.

5551112222 :: 23307011111111 :: 12345 :: 3 :: SMS
5553334444 :: 23307022222222 :: 23456 :: 3 :: SMS
5555556666 :: 23307033333333 :: 34567 :: 3 :: SMS
5557778888 :: 23307044444444 :: 45678 :: 3 :: SMS
5559990000 :: 23307055555555 :: 3
5551239999 :: No associated barcode

Hello,

These patron phone numbers seem to be invalid based on multiple attempts to contact. Please verify and
correct.

5552223333 :: 23307066666666 :: 56789 :: 3 :: SMS
5554445555 :: 23307077777777 :: 67890 :: 3 :: SMS
```

### **Known Quirks**

**Opt-Out vs Invalid:**
- **Opt-out:** Patron actively replied STOP or carrier marked as opted-out
- **Invalid:** Number disconnected, wrong number, or carrier rejected

**Field Variations:**
- Third field can be PatronID (5+ digits) OR Branch ID (just `3`)
- If third field is `3` with nothing after â†’ Patron deleted, only Branch ID remains
- "No associated barcode" means phone in system but mapping broken

**DeliveryOptionID Always 3:**
- Even though field appears, these are primarily text (SMS) failures
- Voice failures appear in different report (Daily Voice Failure Report)
- Value 3 (Phone1) is standard here

**Multiple Entries:**
- Same phone can appear in both opt-out and invalid sections (different failure types)
- Same patron can have multiple entries if multiple phones registered

---

## REPORT TYPE 2: DAILY VOICE FAILURE REPORT

### **Metadata**

**Subject Line:** `Voice notices that were not delivered on [Day], [Month] [Date]th [Year]`

**Example:** `Voice notices that were not delivered on Mon, November 10th 2025`

**From:** Shoutbomb

**To:** Library staff email addresses

**Sent:** Daily at approximately 4:10pm

**Covers:** Voice notifications that failed **that morning** (sent earlier same day)

### **Content Structure**

**Simple format - one line per failure:**
```
[Phone] | [Barcode]| [Library Name]| [Patron Name] | [Notification Type]
```

**Note:** Delimiter is pipe (`|`) with inconsistent spacing

### **Field Formats**

| Position | Field | Format | Description |
|----------|-------|--------|-------------|
| 1 | Phone | 10 digits, no dashes | Patron phone number |
| 2 | Barcode | Varies | Patron barcode |
| 3 | Library Name | Text | Library branch name (e.g., "Daviess County Public Library") |
| 4 | Patron Name | LASTNAME, FIRSTNAME | Patron's name (capitalized) |
| 5 | Notification Type | Text | Type of notification (e.g., "Overdue item message", "Hold ready message") |

### **Sample Data (Fake)**

```
Subject: Voice notices that were not delivered on Mon, November 10th 2025
Date: Monday, November 10, 2025 at 4:10:35PM Central Standard Time
From: Shoutbomb
To: library@example.org

5551234567 | 23307012345678| Daviess County Public Library| SMITH, JOHN | Overdue item message
5559876543 | 23307098765432| Daviess County Public Library| DOE, JANE | Hold ready message
5552468135 | 23307024681357| Daviess County Public Library| JOHNSON, ROBERT | Renewal reminder message
```

### **Known Quirks**

**Patron Names Present:**
- Unlike other reports, this includes actual patron names
- **Privacy concern:** Handle carefully, contains PII

**Inconsistent Spacing:**
- Pipes have inconsistent spacing around them
- Some have spaces, some don't
- Parse by splitting on pipe, then trimming whitespace

**Notification Type Values:**
- "Overdue item message"
- "Hold ready message"  
- "Renewal reminder message"
- Other variations possible

**Empty Reports:**
- If no voice failures occurred, email may be empty or not sent at all
- Check for existence before processing

**Timing:**
- Reports failures from morning voice calls (typically 8am-12pm)
- Sent same day at 4:10pm
- Does NOT include text (SMS) failures (those in invalid phone report)

---

## REPORT TYPE 3: VOICE CALL SYSTEM ERRORS (TRIGGERED)

### **Metadata**

**Subject Line:** `Calls that generated errors [Day], [Month] [Date]th [Year]`

**Example:** `Calls that generated errors Sun, November 2nd 2025`

**From:** Shoutbomb

**To:** Library staff email addresses

**Sent:** Variable timing - **triggered after 3 failed call attempts** to same number

**Covers:** Technical carrier-level failures with detailed error logs

### **Content Structure**

```
Hello,

Calls to these patron phone numbers were not processed due to errors or other service failures.
Please, validate that these patron numbers are working. If not, please update the patron record so that it
no longer is part of the voice notice list.

[Provider] :: [CallID] :: Patron number [Phone] :: Home Library [BranchID] :: error :: disconnect ::: [Error Message] ::: [TransactionID] :: Library number [LibraryPhone] :: [Date] :: [Time] ::
```

**Each failed attempt is logged as a separate line**

### **Field Formats**

**Delimiter:** Double colon (` :: `) separates major fields

| Position | Field | Format | Description |
|----------|-------|--------|-------------|
| 1 | Provider | Text | Carrier provider (e.g., "BANDWIDTH") |
| 2 | CallID | UUID | Unique call attempt identifier |
| 3 | Patron Phone | Text + 10 digits | Format: "Patron number 5551234567" |
| 4 | Branch ID | Text + number | Format: "Home Library 3" |
| 5 | Error Type | Text | Usually "error" |
| 6 | Disconnect | Text | Usually "disconnect" |
| 7 | Error Message | Text | Detailed error (e.g., "Service unavailable") |
| 8 | TransactionID | UUID | Provider transaction identifier |
| 9 | Library Phone | Text + 10 digits | Format: "Library number 5556849100" |
| 10 | Date | YYYY-MM-DD | Date of call attempt |
| 11 | Time | HH:MM:SS | Time of call attempt |

### **Sample Data (Fake)**

```
Subject: Calls that generated errors Sun, November 2nd 2025
Date: Monday, November 3, 2025 at 6:01:10AM Central Standard Time
From: Shoutbomb
To: library@example.org

Hello,

Calls to these patron phone numbers were not processed due to errors or other service failures.
Please, validate that these patron numbers are working. If not, please update the patron record so that it
no longer is part of the voice notice list.

BANDWIDTH :: c-64b3d243-2baaa6aa-9eb2-4ba4-a2cd-4cbca956e201 :: Patron number 5551234567 :: Home Library 3 :: error :: disconnect ::: Service unavailable ::: cdad99cb-9a8f-41e9-888a-e0ddbfc8ccf2 :: Library number 5556849100 :: 2025-11-02 :: 12:11:26 ::
BANDWIDTH :: c-2140ecd2-f8d3ead7-699b-4422-967b-4058bdbad18d :: Patron number 5551234567 :: Home Library 3 :: error :: disconnect ::: Service unavailable ::: 3203948f-1651-47ed-9e3f-b3cb3735ef8a :: Library number 5556849100 :: 2025-11-02 :: 13:27:31 ::
BANDWIDTH :: c-388b81b0-3887bc42-43d5-4db3-9131-ecfb33ff7791 :: Patron number 5551234567 :: Home Library 3 :: error :: disconnect ::: Service unavailable ::: 03df47ca-be9d-4dc1-aaf3-4174f5c1d0c5 :: Library number 5556849100 :: 2025-11-02 :: 14:17:22 ::
```

### **Known Quirks**

**3-Retry Pattern:**
- Email is triggered **only after 3 failed attempts** to same number
- All 3 attempts logged in same email
- Attempts spread over hours (not immediate retries)
- Same patron phone appears 3 times with different CallIDs and timestamps

**Library Number:**
- "Library number 5556849100" is **library's Shoutbomb caller ID**
- This is what shows on patron's phone when Shoutbomb calls
- Not the patron's number

**Error Messages:**
- "Service unavailable" - Carrier rejected call
- "Disconnect" - Number disconnected
- Other carrier-specific errors possible

**Provider Field:**
- Usually "BANDWIDTH" (Shoutbomb's carrier)
- Could be other providers in future

**Triple Colon Delimiters:**
- Error message wrapped in triple colons: `::: Service unavailable :::`
- Inconsistent with double colon used elsewhere
- Parse carefully

**UUID Format:**
- CallID and TransactionID are UUIDs
- Format: `c-xxxxxxxx-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` (CallID)
- Format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` (TransactionID)

**Timing:**
- Report sent **next morning** (~6:01am) after 3 failures complete
- Not sent in real-time
- Multiple patron failures may be in same email

---

## REPORT TYPE 4: MONTHLY STATISTICS REPORT

### **Metadata**

**Subject Line:** `Shoutbomb Rpt [Month] [Year]`

**Example:** `Shoutbomb Rpt October 2025`

**From:** DCPL Notifications (or similar configured sender)

**To:** Library staff email addresses

**Sent:** Monthly, approximately 1st or 2nd of month, around 1:14pm

**Covers:** Complete statistics for **previous month**

### **Content Structure**

Large email with multiple sections:

1. **Notification Statistics** - Counts by type (holds, overdues, renewals)
2. **Totals Section** - Summarized counts with percentages
3. **Registered Patrons by Branch** - Current patron counts
4. **Daily Call Volume** - Automated phone system usage by day
5. **Average Daily Call Volume** - Calculated average
6. **Opted-Out Phone Numbers** - List of patrons who opted out
7. **Invalid Phone Numbers** - List of invalid/disconnected numbers
8. **Keyword Usage Frequency** - Patron text message commands used
9. **Registration Totals** - Active patron counts
10. **Patron Signups** - New registrations for the month
11. **Patron Cancellations** - Service cancellations (usually empty)
12. **Invalid Barcodes Removed** - Barcodes removed from Shoutbomb system
13. **Expired Card Updates** - Patrons who renewed cards
14. **Expired Card Notifications** - Patrons notified about expiring cards

### **Section 1: Notification Statistics**

**Format: Free-form text with key-value pairs**

```
Branch:: [Library Name]
Hold text notices sent for the month = [Count]
Hold text reminders notices sent for the month = [Count]
Hold voice notices sent for the month = [Count]
Hold voice reminder notices sent for the month = [Count]

Overdue text notices sent for the month = [Count]
Overdue items eligible for renewal, text notices sent for the month = [Count]
Overdue items ineligible for renewal, text notices sent for the month = [Count]
Overdue (text) items renewed successfully by patrons for the month = [Count]
Overdue (text) items unsuccessfully renewed by patrons for the month = [Count]
Overdue voice notices sent for the month = [Count]
Overdue items eligible for renewal, voice notices sent for the month = [Count]
Overdue items ineligible for renewal, voice notices sent for the month = [Count]

Renewal text notices sent for the month = [Count]
Items eligible for renewal text notices sent for the month = [Count]
Items ineligible for renewal text notices sent for the month = [Count]
Items (text) unsuccessfully renewed by patrons for the month = [Count]
Renewal reminder text notices sent for the month = [Count]
Items eligible for renewal reminder text notices sent for the month = [Count]
Items ineligible for renewal reminder text notices sent for the month = [Count]

Renewal voice notices sent for the month = [Count]
Voice items eligible for renewal notices sent for the month = [Count]
Voice items ineligible for renewal notices sent for the month = [Count]
Renewal voice reminder notices sent for the month = [Count]
Voice items eligible for renewal reminder notices sent for the month = [Count]
Voice items ineligible for renewal reminder notices sent for the month = [Count]
```

### **Section 2: Totals with Percentages**

```
================================TOTALS================================
[Repeated statistics from Section 1]

 Percentage of overall overdue (text) items where renewal was attempted:: [Percent]%
 Percentage of overall overdue (text) items where renewal was successful:: [Percent]%
 Percentage of overall overdue (text) items where renewal failed:: [Percent]%
 Percentage of overall (text) items where renewal was attempted:: [Percent]%
 Percentage of overall (text) items where renewal failed:: [Percent]%
```

### **Section 3: Registered Patrons by Branch**

```
============================TOTALS OF REGISTERED PATRON BY BRANCH================================
[Library Name] has [Count] registered patrons for text notices
[Library Name] has [Count] registered patrons for voice notices
```

**Note:** May show duplicate or conflicting counts (appears to be a Shoutbomb bug in the monthly report)

### **Section 4: Daily Call Volume**

```
============================TOTAL CALLS FOR EACH DAY================================
On [YYYY-MM-DD] there were [Count] calls
On [YYYY-MM-DD] there were [Count] calls
[... one line per day of month ...]

Average daily call volume is:: [Count]
```

### **Section 5: Opted-Out Phone Numbers**

**Format: Same as Daily Invalid Phone Report**

```
These patron phone numbers seem to have OPTED-OUT from SMS or MMS messages based on multiple
attempts to contact. Please verify and correct.

[Phone] :: [Barcode] :: [PatronID] :: [DeliveryOptionID] :: SMS
[Phone] :: [Barcode] :: [DeliveryOptionID]
[Phone] :: No associated barcode
```

**Cumulative for the entire month**

### **Section 6: Invalid Phone Numbers**

**Format: Same as Daily Invalid Phone Report**

```
These patron phone numbers seem to be invalid based on multiple attempts to contact. Please verify and
correct.

[Phone] :: [Barcode] :: [PatronID] :: [DeliveryOptionID] :: SMS
```

**Cumulative for the entire month**

### **Section 7: Keyword Usage Frequency**

```
...........................................FREQUENCY OF KEYWORD USAGE BY PATRONS (for the
month)...........................................

HELP was used [Count] times.
HL was used [Count] times.
MYBOOK was used [Count] times.
OA was used [Count] times.
OI was used [Count] times.
OK was used [Count] times.
OL was used [Count] times.
ON was used [Count] times.
RA was used [Count] times.
RHL was used [Count] times.
RI was used [Count] times.
STOP was used [Count] times.
THANK was used [Count] times.
THANKS was used [Count] times.
```

**Keywords Explanation:**
- **HELP** - Request help/instructions
- **HL** - Hold list
- **MYBOOK** - My books / checked out items
- **OA** - Overdue items, all
- **OI** - Overdue items
- **OK** - OK / confirmation
- **OL** - Overdue list
- **ON** - Overdues with renewal info
- **RA** - Renewal, all
- **RHL** - Renew hold
- **RI** - Renewal info
- **STOP** - Opt out of notifications
- **THANK/THANKS** - Thank you (no action)

### **Section 8: Registration Totals**

```
Total registered users = [Count]
Total registered barcodes = [Count]
Total registered users for text notices is [Count]
Total registered users for voice notices is [Count]

There was on average [Count] daily voice notices calls.
Registered users the last month = [Count]

[Library Name] signed up [Count] patron(s) for voice notices the last month
[Library Name] signed up [Count] patron(s) for text notices the last month
```

### **Section 9: Patron Cancellations**

```
..................................................*****...........................................
                     No Patron Initiated Cancellation of Notice Service
..................................................*****...........................................
```

**Usually empty.** If patrons cancelled, would list barcodes here.

### **Section 10: Invalid Barcodes Removed**

```
...........................................*****...........................................
The following are patron barcodes found to no longer be valid and have been removed from our system
automatically

XXXXXXXXXX1234
XXXXXXXXXX5678
XXXXXXXXXX9012
[... list continues ...]
```

**Format:** Barcodes with most digits redacted (`XXXXXXXXXX` + last 4 digits)

**These barcodes:**
- No longer exist in Polaris
- Removed from Shoutbomb's database automatically
- Can be hundreds of entries per month

### **Section 11: Expired Card Updates**

```
...........................................*****.........................................................
The following are patrons have updated their expired or soon to expire library cards.

XXXXXXXXXX1111 XXXXXXXXXX2222 XXXXXXXXXX3333 XXXXXXXXXX4444
XXXXXXXXXX5555
...........................................*****.........................................................
```

**Format:** Redacted barcodes, space-separated (not one per line)

### **Section 12: Expired Card Notifications**

```
...........................................*****.........................................................
The following are patrons that have been notified of their expired or soon to expire library cards.

XXXXXXXXXX6666
...........................................*****.........................................................
```

**Format:** Redacted barcodes, one per line

### **Sample Data (Fake Monthly Report - Abbreviated)**

```
Subject: Shoutbomb Rpt October 2025
Date: Saturday, November 1, 2025 at 1:14:37PM Central Daylight Time
From: DCPL Notifications
To: library@example.org

Branch:: Daviess County Public Library
Hold text notices sent for the month = 1728
Hold text reminders notices sent for the month = 672
Hold voice notices sent for the month = 573
Hold voice reminder notices sent for the month = 187

Overdue text notices sent for the month = 1207
Overdue items eligible for renewal, text notices sent for the month = 227
Overdue items ineligible for renewal, text notices sent for the month = 3036
Overdue (text) items renewed successfully by patrons for the month = 18
Overdue (text) items unsuccessfully renewed by patrons for the month = 15
Overdue voice notices sent for the month = 258

[... additional statistics ...]

================================TOTALS================================
Hold text notices sent for the month = 1728
 Percentage of overall overdue (text) items where renewal was attempted:: 14.54%
 Percentage of overall overdue (text) items where renewal was successful:: 7.93%

[... additional totals ...]

============================TOTALS OF REGISTERED PATRON BY BRANCH================================
Daviess County Public Library has 13318 registered patrons for text notices
Daviess County Public Library has 5201 registered patrons for voice notices

============================TOTAL CALLS FOR EACH DAY================================
On 2025-10-31 there were 52 calls
On 2025-10-30 there were 50 calls
[... one per day for entire month ...]
Average daily call volume is:: 52

These patron phone numbers seem to have OPTED-OUT from SMS or MMS messages based on multiple
attempts to contact. Please verify and correct.

5551112222 :: 23307015004718 :: 145937 :: 3 :: SMS
5553334444 :: 23307015187364 :: 145442 :: 3 :: SMS
[... list continues ...]

These patron phone numbers seem to be invalid based on multiple attempts to contact. Please verify and
correct.

5555556666 :: 23307015232277 :: 47056 :: 3 :: SMS
5557778888 :: 23307014829529 :: 108801 :: 3 :: SMS
[... list continues ...]

...........................................FREQUENCY OF KEYWORD USAGE BY PATRONS (for the
month)...........................................

HELP was used 1 times.
HL was used 12 times.
MYBOOK was used 6 times.
OA was used 26 times.
OI was used 34 times.
OK was used 2 times.
[... full keyword list ...]

Total registered users = 18506
Total registered barcodes = 21943
Total registered users for text notices is 13307
Total registered users for voice notices is 5199

Daviess County Public Library signed up 108 patron(s) for voice notices the last month
Daviess County Public Library signed up 263 patron(s) for text notices the last month

..................................................*****...........................................
                     No Patron Initiated Cancellation of Notice Service
..................................................*****...........................................

The following are patron barcodes found to no longer be valid and have been removed from our system
automatically

XXXXXXXXXX2144
XXXXXXXXXX4217
XXXXXXXXXX5604
[... hundreds more ...]

The following are patrons have updated their expired or soon to expire library cards.

XXXXXXXXXX6127 XXXXXXXXXX9212 XXXXXXXXXX1877 XXXXXXXXXX4206
XXXXXXXXXX3824

The following are patrons that have been notified of their expired or soon to expire library cards.

XXXXXXXXXX2461
```

### **Known Quirks - Monthly Report**

**Format Inconsistencies:**
- Mix of structured data and free-form text
- No consistent delimiter or format
- Sections separated by ASCII art borders (`===`, `***`, dots)
- Some barcodes one-per-line, others space-separated

**Duplicate Statistics:**
- Some statistics appear twice (once in detail, once in totals)
- Totals section adds percentages

**Registered Patron Counts:**
- Sometimes shows conflicting numbers in different sections
- Appears to be Shoutbomb bug in report generation
- Use the specific "Total registered users for text/voice" lines as authoritative

**Barcode Redaction:**
- Barcodes partially redacted with `XXXXXXXXXX` + last digits
- Inconsistent: sometimes 4 digits shown, sometimes 2-6 digits
- Cannot be used to identify specific patrons (privacy protection)

**Section Separators:**
- Uses decorative separators: `===`, `***`, `.....`
- Not consistent delimiters
- Must parse by section headers/text patterns

**Keyword Usage:**
- Only shows keywords that were used (zero-count keywords omitted)
- "STOP" count indicates opt-outs initiated by patron text

**Call Volume:**
- "Calls" refers to inbound calls to automated phone system
- Does NOT include outbound notifications Shoutbomb sent
- Average calculated across all days in month

**Time Periods:**
- Report generated early in following month
- Covers complete previous calendar month
- Daily call volume shows every day individually

---

## CROSS-REFERENCE USAGE

**How These Reports Link to Other Data:**

| Report Type | Links To | Cross-Reference Keys | Purpose |
|-------------|----------|---------------------|---------|
| Daily Invalid Phone | Patron Lists (voice/text) | Phone number | Identify patrons to remove from exports |
| Daily Invalid Phone | Patron Master | Barcode, PatronID | Update patron records |
| Daily Voice Failure | Voice Patrons List | Phone number, Barcode | Verify patron still registered for voice |
| Daily Voice Failure | Notification Exports | Barcode | Match failed notification to original export |
| Voice Call Errors | Voice Patrons List | Phone number | Remove non-working numbers |
| Monthly Stats | All Exports | Cumulative validation | Verify export counts match Shoutbomb receipts |
| Monthly Stats - Invalid Barcodes | Patron Lists | Barcode (partial) | Clean up removed patrons |

---

## PROCESSING STRATEGIES

### **Daily Invalid Phone Report**

**Import Approach:**
1. Parse email body by section ("OPTED-OUT" vs "invalid")
2. Split each line by ` :: ` delimiter
3. Handle three format variations:
   - Standard: Phone :: Barcode :: PatronID :: DeliveryOptionID :: SMS
   - Deleted: Phone :: Barcode :: 3
   - No Barcode: Phone :: No associated barcode
4. Store with report date and section type

**Actions:**
- Cross-reference against patron lists exports
- Flag patron records in Polaris for staff review
- Consider automatic removal after X occurrences
- Track opt-outs separately (patron choice vs. technical failure)

### **Daily Voice Failure Report**

**Import Approach:**
1. Parse email body line by line
2. Split by `|` delimiter
3. Trim whitespace from each field
4. Store with report date

**Actions:**
- Cross-reference against morning voice notification export
- Verify patron still in voice_patrons list
- Alert staff about failed delivery (includes patron names - PII)
- Track failure rates by patron

### **Voice Call System Errors**

**Import Approach:**
1. Parse email body line by line
2. Split by ` :: ` delimiter
3. Extract phone from "Patron number [Phone]" format
4. Extract date and time from fields
5. Group by phone number (3 attempts per phone)

**Actions:**
- Flag phone numbers with 3 failures
- Recommend removal from voice_patrons list
- Track carrier error patterns
- Alert staff to disconnected numbers

### **Monthly Statistics Report**

**Import Approach:**
1. Parse email body by section headers
2. Extract key statistics using text pattern matching
3. Store as structured data:
   - Notification counts by type
   - Registered patron totals
   - Daily call volume array
   - Keyword usage counts
4. Parse failure lists (same as daily reports)
5. Extract removed barcodes list

**Actions:**
- Validate against sum of daily exports for month
- Compare registered patron counts to current patron lists
- Trend analysis: month-over-month changes
- Review keyword usage for patron behavior insights
- Process cumulative failure lists for bulk cleanup

---

## VALIDATION RULES

### **Daily Invalid Phone Report**

**Expected:**
- Sent every day at ~6:01am
- 0-50 entries typical (varies by library size and patron behavior)
- Format consistent across all entries

**Alerts:**
- Missing report for 2+ consecutive days
- Sudden spike in opt-outs (>50 in one day)
- Same phone appearing repeatedly (multiple days in a row)

### **Daily Voice Failure Report**

**Expected:**
- Sent every day at ~4:10pm
- 0-10 entries typical
- Only includes voice failures, not text

**Alerts:**
- Missing report when voice notifications were sent
- High failure rate (>20% of voice patrons)
- Same phone failing repeatedly

### **Voice Call System Errors**

**Expected:**
- Triggered after 3 failed attempts
- Not daily - only when failures occur
- Always shows 3 attempts for same phone

**Alerts:**
- Multiple patrons with carrier errors (Shoutbomb provider issue)
- Frequent emails (indicates systematic problem)

### **Monthly Statistics Report**

**Expected:**
- Sent 1st-2nd of month
- Large email (can be 15+ pages)
- Totals should match sum of daily activities

**Alerts:**
- Missing monthly report
- Registered patron counts drastically different from patron list exports
- Cumulative notification counts don't align with daily export totals

---

## KNOWN ISSUES & LIMITATIONS

### **Parsing Challenges**

**Unstructured Format:**
- Email body is plain text, not CSV or structured data
- Must use text pattern matching and section detection
- Format can vary slightly between emails

**Inconsistent Delimiters:**
- Daily reports use ` :: ` (space-colon-colon-space)
- Voice failures use `|` (pipe)
- System errors use ` :: ` but also ` ::: ` (triple colon)
- Monthly report uses no consistent delimiter

**Barcode Redaction:**
- Monthly report redacts most barcode digits
- Cannot definitively match to specific patrons
- Useful for counts only, not individual patron actions

### **Data Quality Issues**

**Deleted Patron Confusion:**
- When third field is just `3`, it's Branch ID not PatronID
- Indicates patron deleted but phone still in Shoutbomb
- Cannot determine original patron without additional lookup

**Duplicate Information:**
- Monthly report repeats many statistics
- Daily invalid phone entries may appear in monthly cumulative list
- Must deduplicate when processing both

**Timing Gaps:**
- Daily reports cover "previous day" or "this morning"
- Exact time boundaries unclear
- May miss some failures that occur during boundary period

### **Missing Information**

**No Success Confirmation:**
- Reports only contain failures
- Successful deliveries inferred by absence from failure reports
- No positive confirmation of delivery

**Limited Context:**
- Daily reports don't show what notification failed (hold? overdue?)
- Must cross-reference with notification exports to determine type
- System errors don't show notification content

**No Barcode in Some Cases:**
- "No associated barcode" entries cannot be matched to patrons
- Cannot determine if phone should be in system
- Manual investigation required

---

## SECURITY & PRIVACY CONSIDERATIONS

**Personally Identifiable Information (PII):**
- **Daily Voice Failure Report:** Contains patron names (LASTNAME, FIRSTNAME)
- **All Reports:** Contain phone numbers (can identify individuals)
- **Monthly Report:** Barcode redaction provides some privacy protection

**Data Handling:**
- Store these reports securely (contains PII)
- Limit access to authorized staff only
- Consider retention policy (how long to keep failure reports)
- Comply with library privacy policies

**Patron Opt-Outs:**
- Must honor STOP requests immediately
- Remove opted-out patrons from notification exports
- Document opt-out date and reason
- Do not re-add without explicit patron consent

---

## EMAIL ROUTING & AUTOMATION

**Recommended Email Processing:**

1. **Email Rules/Filters:**
   - Route by subject line to different folders:
     - "Invalid patron phone" â†’ Invalid_Phone folder
     - "Voice notices that were not delivered" â†’ Voice_Failures folder
     - "Calls that generated errors" â†’ System_Errors folder
     - "Shoutbomb Rpt" â†’ Monthly_Stats folder

2. **Automated Parsing:**
   - Daily: Parse invalid phone and voice failure reports
   - Triggered: Parse system errors as they arrive
   - Monthly: Parse statistics report (1st of month)

3. **Integration:**
   - Import parsed data into monitoring database
   - Cross-reference with patron list exports
   - Generate staff alerts for high-priority failures

4. **Archiving:**
   - Keep emails for audit trail
   - Store parsed data separately for analysis
   - Retain for at least 1 year

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| (Unknown) | Shoutbomb started sending daily invalid phone reports | Additional failure tracking |
| (Unknown) | Added voice call system errors for carrier failures | Detailed technical diagnostics |
| (Unknown) | Monthly report format updated | Changed section order and formatting |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Vendor Contact:** Shoutbomb support (support@shoutbomb.com)

**Email Recipients:** Configured in Shoutbomb admin panel

**Report Issues:** Contact Shoutbomb if reports missing or format changes

**Last Reviewed:** 2025-11-13

---

## AI/LLM INSTRUCTIONS

> When working with Shoutbomb reports, AI tools MUST:

1. **ALWAYS reference this document** before parsing report emails
2. **Identify report type** by subject line first
3. **Handle variable formats** - each report type has different structure
4. **Parse by section** for monthly report (use section headers as markers)
5. **Handle three format variations** in invalid phone reports:
   - Phone :: Barcode :: PatronID :: DeliveryOptionID :: SMS
   - Phone :: Barcode :: 3 (deleted patron)
   - Phone :: No associated barcode
6. **Respect PII** - voice failure reports contain patron names
7. **Expect missing reports** - some are triggered, not daily
8. **Validate delimiters** - different reports use different delimiters
9. **Deduplicate** - monthly report includes cumulative daily data
10. **If format doesn't match** - STOP and alert human

**Critical Reminders:**
- These are INCOMING reports FROM Shoutbomb TO library
- Format is email body text, not structured files
- No header rows, no consistent delimiters
- Must parse using text pattern matching
- Contains PII - handle securely
- Failure reports show problems only (no success confirmation)

**Common AI Mistakes to Avoid:**
1. Treating email body as CSV (it's not structured)
2. Assuming consistent delimiters across report types
3. Confusing Branch ID (`3`) with PatronID in deleted patron entries
4. Expecting all fields present in every line (variations exist)
5. Parsing monthly report as single structure (it has multiple distinct sections)
6. Not handling "No associated barcode" entries
7. As
---

## RELATED DOCUMENTATION

### Master Documentation
- **[SHOUTBOMB_DOCUMENTATION_INDEX.md](SHOUTBOMB_DOCUMENTATION_INDEX.md)** - Central index and system architecture

### Patron Lists
- **[SHOUTBOMB_VOICE_PATRONS.md](SHOUTBOMB_VOICE_PATRONS.md)** - Voice notification patrons (cross-reference for invalid phones)
- **[SHOUTBOMB_TEXT_PATRONS.md](SHOUTBOMB_TEXT_PATRONS.md)** - Text notification patrons (cross-reference for invalid phones)

### Notification Exports
- **[SHOUTBOMB_HOLDS_EXPORT.md](SHOUTBOMB_HOLDS_EXPORT.md)** - Hold ready notifications
- **[SHOUTBOMB_OVERDUE_EXPORT.md](SHOUTBOMB_OVERDUE_EXPORT.md)** - Overdue notifications
- **[SHOUTBOMB_RENEW_EXPORT.md](SHOUTBOMB_RENEW_EXPORT.md)** - Renewal reminders

### Validation
- **[POLARIS_PHONE_NOTICES.md](POLARIS_PHONE_NOTICES.md)** - Native Polaris phone notices export

### API Integration
- **[Polaris_Notification_Guide_PAPIClient.md](Polaris_Notification_Guide_PAPIClient.md)** - Laravel PAPIClient package guide

---

**Last Updated:** November 14, 2025  
**Document Version:** 2.0  
**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library
